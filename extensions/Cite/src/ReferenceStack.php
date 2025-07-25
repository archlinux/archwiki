<?php

namespace Cite;

use LogicException;
use MediaWiki\Parser\StripState;

/**
 * Encapsulates most of Cite state during parsing.  This includes metadata about each ref tag,
 * and a rollback stack to correct confusion caused by lost context when `{{#tag` is used.
 *
 * @license GPL-2.0-or-later
 */
class ReferenceStack {

	/**
	 * Data structure representing all <ref> tags parsed so far, indexed by group name (an empty
	 * string for the default group) and reference name.
	 *
	 * References without a name get a numeric index, starting from 0. Conflicts are avoided by
	 * disallowing numeric names (e.g. <ref name="1">) in {@see Validator::validateRef}.
	 *
	 * @var array<string,array<string|int,ReferenceStackItem>>
	 */
	private array $refs = [];

	/**
	 * Global, auto-incrementing sequence number for all <ref>, no matter which group, starting
	 * from 1. Reflects the total number of <ref>.
	 */
	private int $refSequence = 0;
	/**
	 * @var array<string,int> Auto-incrementing sequence numbers per group, starting from 1.
	 * Reflects the current number of <ref> in each group.
	 */
	private array $groupRefSequence = [];

	/**
	 * <ref> call stack
	 * Used to cleanup out of sequence ref calls created by #tag
	 * See description of function rollbackRef.
	 *
	 * @var (array{0: string, 1: ReferenceStackItem, 2: ?string, 3: array}|false)[] Non-false
	 *  entries are parameters for the {@see rollbackRef} function
	 */
	private array $refCallStack = [];

	private const ACTION_INCREMENT = 'increment';
	private const ACTION_NEW = 'new';

	/**
	 * Leave a mark in the stack which matches an invalid ref tag.
	 */
	public function pushInvalidRef(): void {
		$this->refCallStack[] = false;
	}

	/**
	 * Populate $this->refs and $this->refCallStack based on input and arguments to <ref>
	 *
	 * @param StripState $stripState
	 * @param ?string $text Content from the <ref> tag
	 * @param array $argv
	 * @param string $group
	 * @param ?string $name
	 * @param ?string $follow Guaranteed to not be a numeric string
	 * @param ?string $dir ref direction
	 * @param ?string $subrefDetails subreference details
	 *
	 * @return ?ReferenceStackItem ref to render at the site of usage, or null
	 * if no footnote marker should be rendered
	 */
	public function pushRef(
		StripState $stripState,
		?string $text,
		array $argv,
		string $group,
		?string $name,
		?string $follow,
		?string $dir,
		?string $subrefDetails = null
	): ?ReferenceStackItem {
		$this->refs[$group] ??= [];
		$this->groupRefSequence[$group] ??= 0;

		$ref = new ReferenceStackItem();
		$ref->count = 1;
		$ref->dir = $dir;
		$ref->group = $group;
		$ref->name = $name ?: null;
		$ref->text = $text;

		if ( $follow ) {
			if ( !isset( $this->refs[$group][$follow] ) ) {
				// Mark an incomplete follow="…" as such. This is valid e.g. in the Page:… namespace
				// on Wikisource.
				$ref->follow = $follow;
				$ref->globalId = $this->nextRefSequence();
				$this->refs[$group][$ref->globalId] = $ref;
				$this->refCallStack[] = [ self::ACTION_NEW, $ref, $text, $argv ];
			} elseif ( $text !== null ) {
				// We know the parent already, so just perform the follow="…" and bail out
				$this->resolveFollow( $group, $follow, $text );
			}
			// A follow="…" never gets its own footnote marker
			return null;
		}

		if ( $name && isset( $this->refs[$group][$name] ) ) {
			// A named <ref> is reused, possibly with more information than before
			$ref = &$this->refs[$group][$name];
			$ref->count++;

			if ( $ref->dir && $dir && $ref->dir !== $dir ) {
				$ref->warnings[] = [ 'cite_error_ref_conflicting_dir', $name ];
			}

			if ( $ref->text === null && $text !== null ) {
				// If no text was set before, use this text
				$ref->text = $text;
				// Use the dir parameter only from the full definition of a named ref tag
				$ref->dir = $dir;
			} else {
				if ( $text !== null
					// T205803 different strip markers might hide the same text
					&& $stripState->unstripBoth( $text )
					!== $stripState->unstripBoth( $ref->text )
				) {
					// Two <ref> with same group and name, but different content
					$ref->warnings[] = [ 'cite_error_references_duplicate_key', $name ];
				}
			}
			$action = self::ACTION_INCREMENT;
		} else {
			// First occurrence of a named <ref>, or an unnamed <ref>
			$ref->globalId = $this->nextRefSequence();
			$this->refs[$group][$name ?: $ref->globalId] = &$ref;
			$action = self::ACTION_NEW;
		}

		$ref->numberInGroup ??= ++$this->groupRefSequence[$group];

		if ( ( $subrefDetails ?? '' ) !== '' ) {
			$parentRef = $ref;
			// Turns out this is not a reused parent; undo parts of what happened above
			$parentRef->count--;

			// Make a clone of the sub-reference before we start manipulating the parent
			unset( $ref );
			$ref = clone $parentRef;

			$parentRef->subrefCount ??= 0;

			// FIXME: At the moment it's impossible to reuse sub-references in any way
			$ref->count = 1;
			$ref->globalId = $this->nextRefSequence();
			$ref->name = null;
			$ref->hasMainRef = $parentRef;
			$ref->subrefIndex = ++$parentRef->subrefCount;
			$ref->text = $subrefDetails;
			// No need to duplicate errors that are already shown on the parent
			$ref->warnings = [];
			$this->refs[$group][$ref->globalId] = $ref;
		}

		$this->refCallStack[] = [ $action, $ref, $text, $argv ];
		return $ref;
	}

	/**
	 * Undo the changes made by the last $count ref tags.  This is used when we discover that the
	 * last few tags were actually inside of a references tag.
	 *
	 * @param int $count Number of <ref> tags already parsed and replaced with strip-markers before
	 *  we realized we are actually inside {{#tag:references|…}}.
	 * @return array{0: ?string, 1: array}[] Refs to restore under the correct context, as a list
	 *  of [ $text, $argv ]
	 */
	public function rollbackRefs( int $count ): array {
		$redoStack = [];
		while ( $count-- && $this->refCallStack ) {
			$call = array_pop( $this->refCallStack );
			if ( $call ) {
				/** @var ReferenceStackItem $ref */
				$ref = $call[1];
				if ( $ref->hasMainRef ) {
					// Must drop the sub-ref completely; undoing and redoing the main re-creates it
					$this->rollbackRef( self::ACTION_NEW, $ref, null, [] );
					$call[1] = $ref->hasMainRef;
				}

				// @phan-suppress-next-line PhanParamTooFewUnpack
				$redoStack[] = $this->rollbackRef( ...$call );
			}
		}

		// Drop unused rollbacks, this group is finished.
		$this->refCallStack = [];

		return array_reverse( $redoStack );
	}

	/**
	 * Partially undoes the effect of calls to stack()
	 *
	 * The option to define <ref> within <references> makes the
	 * behavior of <ref> context dependent.  This is normally fine
	 * but certain operations (especially #tag) lead to out-of-order
	 * parser evaluation with the <ref> tags being processed before
	 * their containing <reference> element is read.  This leads to
	 * stack corruption that this function works to fix.
	 *
	 * This function is not a total rollback since some internal
	 * counters remain incremented.  Doing so prevents accidentally
	 * corrupting certain links.
	 *
	 * @param string $action
	 * @param ReferenceStackItem $ref
	 * @param ?string $text
	 * @param array $argv
	 * @return array{0: ?string, 1: array} [ $text, $argv ] Ref redo item.
	 */
	private function rollbackRef(
		string $action,
		ReferenceStackItem $ref,
		?string $text,
		array $argv
	): array {
		switch ( $action ) {
			case self::ACTION_NEW:
				// Rollback the addition of new elements to the stack
				$group = $ref->group;
				$i = array_search( $ref, $this->refs[$group], true );
				unset( $this->refs[$group][$i] );
				if ( !$this->refs[$group] ) {
					$this->popGroup( $group );
				} elseif ( isset( $this->groupRefSequence[$group] ) ) {
					$this->groupRefSequence[$group]--;
				}
				break;
			case self::ACTION_INCREMENT:
				// Rollback increase in named ref occurrences
				$ref->count--;
				break;
			default:
				throw new LogicException( "Unknown call stack action \"$action\"" );
		}
		return [ $text, $argv ];
	}

	/**
	 * Clear state for a single group.
	 *
	 * @param string $group
	 *
	 * @return array<string|int,ReferenceStackItem> The references from the removed group
	 */
	public function popGroup( string $group ): array {
		$refs = $this->getGroupRefs( $group );
		unset( $this->refs[$group] );
		unset( $this->groupRefSequence[$group] );
		return $refs;
	}

	/**
	 * Returns true if the group exists and contains references.
	 */
	public function hasGroup( string $group ): bool {
		return (bool)( $this->refs[$group] ?? null );
	}

	public function isKnown( ?string $group, ?string $name ): bool {
		return $name && isset( $this->refs[$group ?? Cite::DEFAULT_GROUP][$name] );
	}

	/**
	 * @return string[] List of group names that contain at least one reference
	 */
	public function getGroups(): array {
		$groups = [];
		foreach ( $this->refs as $group => $refs ) {
			if ( $refs ) {
				$groups[] = $group;
			}
		}
		return $groups;
	}

	/**
	 * Return all references for a group.
	 *
	 * @param string $group
	 *
	 * @return array<string|int,ReferenceStackItem>
	 */
	public function getGroupRefs( string $group ): array {
		return $this->refs[$group] ?? [];
	}

	private function resolveFollow( string $group, string $follow, string $text ): void {
		$previousRef =& $this->refs[$group][$follow];
		$previousRef->text ??= '';
		$previousRef->text .= " $text";
	}

	public function listDefinedRef( string $group, string $name, ?string $text ): ReferenceStackItem {
		$ref =& $this->refs[$group][$name];
		$ref ??= new ReferenceStackItem();
		if ( $ref->text === null ) {
			$ref->text = $text;
		} elseif ( $text !== null && $ref->text !== $text ) {
			// Two <ref> with same group and name, but different content
			$ref->warnings[] = [ 'cite_error_references_duplicate_key', $name ];
		}
		return $ref;
	}

	private function nextRefSequence(): int {
		return ++$this->refSequence;
	}

}
