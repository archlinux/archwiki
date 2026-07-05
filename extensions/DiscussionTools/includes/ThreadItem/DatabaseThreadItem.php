<?php

namespace MediaWiki\Extension\DiscussionTools\ThreadItem;

use JsonSerializable;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Revision\RevisionRecord;

class DatabaseThreadItem implements JsonSerializable, ThreadItem {
	use ThreadItemTrait;

	/** @var self[] */
	private array $replies = [];

	public function __construct(
		private readonly ProperPageIdentity $page,
		private readonly RevisionRecord $rev,
		private readonly string $type,
		private readonly string $name,
		private readonly string $id,
		private readonly ?self $parent,
		private readonly bool|string $transcludedFrom,
		private readonly int $level,
	) {
	}

	public function getPage(): ProperPageIdentity {
		return $this->page;
	}

	public function getRevision(): RevisionRecord {
		return $this->rev;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @param self $reply Reply comment
	 */
	public function addReply( self $reply ): void {
		$this->replies[] = $reply;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @inheritDoc
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * @inheritDoc
	 * @return self|null
	 */
	public function getParent(): ?ThreadItem {
		return $this->parent;
	}

	/**
	 * @inheritDoc
	 * @return self[]
	 */
	public function getReplies(): array {
		return $this->replies;
	}

	/**
	 * @inheritDoc
	 */
	public function getTranscludedFrom() {
		return $this->transcludedFrom;
	}

	/**
	 * @inheritDoc
	 */
	public function getLevel(): int {
		return $this->level;
	}

	/**
	 * An item can generate the canonical permalink if it is not transcluded from another page,
	 * and it was found in the current revision of its page.
	 */
	public function isCanonicalPermalink(): bool {
		return $this->getRevision()->isCurrent() && !is_string( $this->getTranscludedFrom() );
	}
}
