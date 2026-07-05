<?php

namespace MediaWiki\Extension\DiscussionTools\ThreadItem;

use DateTimeImmutable;
use DateTimeZone;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Revision\RevisionRecord;

class DatabaseCommentItem extends DatabaseThreadItem implements CommentItem {
	use CommentItemTrait {
		getHeading as protected traitGetHeading;
		getSubscribableHeading as protected traitGetSubscribableHeading;
	}

	/**
	 * @param ProperPageIdentity $page
	 * @param RevisionRecord $rev
	 * @param string $name
	 * @param string $id
	 * @param DatabaseThreadItem|null $parent
	 * @param bool|string $transcludedFrom
	 * @param int $level
	 * @param string $timestamp
	 * @param string $author
	 */
	public function __construct(
		ProperPageIdentity $page, RevisionRecord $rev,
		string $name, string $id, ?DatabaseThreadItem $parent, $transcludedFrom, int $level,
		private readonly string $timestamp,
		private readonly string $author,
	) {
		parent::__construct( $page, $rev, 'comment', $name, $id, $parent, $transcludedFrom, $level );
	}

	/**
	 * @inheritDoc
	 */
	public function getAuthor(): string {
		return $this->author;
	}

	/**
	 * @inheritDoc
	 */
	public function getTimestamp(): DateTimeImmutable {
		return new DateTimeImmutable( $this->timestamp, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * @inheritDoc CommentItemTrait::getHeading
	 * @suppress PhanTypeMismatchReturnSuperType
	 */
	public function getHeading(): DatabaseHeadingItem {
		return $this->traitGetHeading();
	}

	/**
	 * @inheritDoc CommentItemTrait::getSubscribableHeading
	 */
	public function getSubscribableHeading(): ?DatabaseHeadingItem {
		return $this->traitGetSubscribableHeading();
	}
}
