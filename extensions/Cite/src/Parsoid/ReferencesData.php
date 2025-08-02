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

	public function inIndicatorContext(): bool {
		return in_array( 'indicator', $this->embeddedContentStack, true );
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

	public function addRef(
		RefGroup $group,
		string $refName,
		string $refDir,
		?string $details = null
	): RefGroupItem {
		$ref = new RefGroupItem();

		if ( $details === null ) {
			// FIXME: This doesn't count correctly when <ref follow=…> is used on the page
			$ref->numberInGroup = $group->getNextIndex();
			$ref->name = $refName ?: null;
		} else {
			$mainRef = $group->lookupRefByName( $refName ) ??
				// TODO: dir could be different for the main
				$this->addRef( $group, $refName, $refDir );

			$ref->numberInGroup = $mainRef->numberInGroup;
			$ref->subrefIndex = $group->getNextSubrefSequence( $refName );
		}

		$ref->dir = $refDir;
		$ref->group = $group->name;
		$ref->globalId = ++$this->refSequence;

		$group->refs[] = $ref;
		if ( $ref->name ) {
			$group->indexByName[$ref->name] = $ref;
		}

		return $ref;
	}

	/** @return array<string,RefGroup> */
	public function getRefGroups(): array {
		return $this->refGroups;
	}

}
