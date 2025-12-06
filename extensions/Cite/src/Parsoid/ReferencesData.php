<?php
declare( strict_types = 1 );

namespace Cite\Parsoid;

use Wikimedia\Parsoid\NodeData\DataMwError;

/**
 * @license GPL-2.0-or-later
 */
class ReferencesData {

	/**
	 * Global, auto-incrementing sequence number for all <ref>, no matter which group, starting
	 * from 1. Reflects the total number of <ref>.
	 */
	private int $refSequence = 0;
	/** @var array<string,RefGroup> indexed by group name */
	private array $refGroups = [];
	/** @var array<string,list<DataMwError>> */
	public array $embeddedErrors = [];
	/** @var array<?string> */
	private array $embeddedContentStack = [];

	/**
	 * Ideally we would track this on the RefGroupItem, but that would require
	 * creating one before processing nested references and it would change the
	 * sequence numbers.
	 */
	public array $refLocks = [];

	/**
	 * The current group name while we are in <references>, no matter how deeply nested. Null when
	 * parsing <ref> outside of <references>. Warning, an empty string is a valid group name!
	 */
	public ?string $referencesGroup = null;
	private int $nestedRefsDepth = 0;

	/**
	 * True when we are inside of <references>, but not when we are in a deeper nested <ref>
	 */
	public function inReferenceList(): bool {
		return $this->referenceListGroup() !== null;
	}

	/**
	 * The default group name while we are inside of <references>. Null when we are outside of
	 * <references> or in a deeper nested <ref>
	 */
	public function referenceListGroup(): ?string {
		return $this->nestedRefsDepth > 0 ? null : $this->referencesGroup;
	}

	/**
	 * True when we are currently parsing <ref> that are embedded in some <…> tag
	 */
	public function inEmbeddedContent(): bool {
		return $this->inReferenceList() || $this->embeddedContentStack;
	}

	public function pushEmbeddedContentFlag( ?string $needle = null ): void {
		array_push( $this->embeddedContentStack, $needle );
	}

	public function popEmbeddedContentFlag(): void {
		array_pop( $this->embeddedContentStack );
	}

	public function peekForIndicatorContext(): bool {
		$last = array_key_last( $this->embeddedContentStack );
		return $last !== null && $this->embeddedContentStack[$last] === 'indicator';
	}

	public function incrementRefDepth(): void {
		$this->nestedRefsDepth++;
	}

	public function decrementRefDepth(): void {
		$this->nestedRefsDepth--;
	}

	public function getOrCreateRefGroup( string $groupName ): RefGroup {
		$this->refGroups[$groupName] ??= new RefGroup( $groupName );
		return $this->refGroups[$groupName];
	}

	public function lookupRefGroup( string $groupName ): ?RefGroup {
		return $this->refGroups[$groupName] ?? null;
	}

	public function removeRefGroup( string $groupName ): void {
		// '' is a valid group (the default group)
		unset( $this->refGroups[$groupName] );
	}

	public function isKnown( ?string $group, ?string $name ): bool {
		$refGroup = $this->lookupRefGroup( $group ?? '' );
		return $refGroup && $name && $refGroup->lookupRefByName( $name );
	}

	/**
	 * @param RefGroup $group Group to add the new reference to
	 * @param string $name
	 * @param string $dir Direction "ltr" or "rtl", or an empty string when not specified
	 * @param string|null $details Contents of the details="…" attribute, or null when not used
	 * @return RefGroupItem
	 */
	public function addRef(
		RefGroup $group,
		string $name,
		string $dir,
		?string $details = null
	): RefGroupItem {
		$ref = new RefGroupItem();

		if ( $details === null ) {
			// FIXME: This doesn't count correctly when <ref follow=…> is used on the page
			$ref->numberInGroup = $group->getNextIndex();
			$ref->name = $name ?: null;
		} else {
			$mainRef = $group->lookupRefByName( $name ) ??
				// TODO: dir could be different for the main
				$this->addRef( $group, $name, $dir );

			$ref->numberInGroup = $mainRef->numberInGroup;
			$ref->subrefIndex = $group->getNextSubrefSequence( $name );
		}

		$ref->dir = $dir;
		$ref->group = $group->name;
		$ref->globalId = ++$this->refSequence;

		$group->push( $ref );

		return $ref;
	}

	/** @return array<string,RefGroup> */
	public function getRefGroups(): array {
		return $this->refGroups;
	}

}
