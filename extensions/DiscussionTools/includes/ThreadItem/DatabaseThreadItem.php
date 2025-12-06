<?php

namespace MediaWiki\Extension\DiscussionTools\ThreadItem;

use JsonSerializable;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Revision\RevisionRecord;

class DatabaseThreadItem implements JsonSerializable, ThreadItem {
	use ThreadItemTrait;

	private ProperPageIdentity $page;
	private RevisionRecord $rev;
	private string $type;
	private string $name;
	private string $id;
	private ?self $parent;
	/** @var self[] */
	private array $replies = [];
	/** @var string|bool */
	private $transcludedFrom;
	private int $level;

	/**
	 * @param ProperPageIdentity $page
	 * @param RevisionRecord $rev
	 * @param string $type
	 * @param string $name
	 * @param string $id
	 * @param self|null $parent
	 * @param bool|string $transcludedFrom
	 * @param int $level
	 */
	public function __construct(
		ProperPageIdentity $page, RevisionRecord $rev,
		string $type, string $name, string $id, ?self $parent, $transcludedFrom, int $level
	) {
		$this->page = $page;
		$this->rev = $rev;
		$this->name = $name;
		$this->id = $id;
		$this->type = $type;
		$this->parent = $parent;
		$this->transcludedFrom = $transcludedFrom;
		$this->level = $level;
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
