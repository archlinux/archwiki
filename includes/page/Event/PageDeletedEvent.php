<?php

namespace MediaWiki\Page\Event;

use MediaWiki\Page\ExistingPageRecord;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\PageUpdateCauses;
use MediaWiki\User\UserIdentity;
use Wikimedia\Assert\Assert;

/**
 * Domain event representing page deletion.
 *
 * @see PageCreatedEvent
 *
 * @unstable until 1.45
 */
class PageDeletedEvent extends PageStateEvent {

	public const TYPE = 'PageDeleted';

	/**
	 * Whether the deleted revisions and log have been suppressed, so they
	 * are not visible in the regular deletion log.
	 */
	public const FLAG_SUPPRESSED = 'suppressed';

	private RevisionRecord $latestRevisionBefore;
	private string $reason;
	private int $archivedRevisionCount;

	public function __construct(
		ExistingPageRecord $pageRecordBefore,
		RevisionRecord $latestRevisionBefore,
		UserIdentity $performer,
		array $tags,
		array $flags,
		$timestamp,
		string $reason,
		int $archivedRevisionCount
	) {
		parent::__construct(
			PageUpdateCauses::CAUSE_DELETE,
			$pageRecordBefore,
			null,
			$performer,
			$tags,
			$flags,
			$timestamp
		);

		Assert::parameter(
			$pageRecordBefore->exists(),
			'$pageRecordBefore',
			'must represent an existing page'
		);

		$this->declareEventType( self::TYPE );
		$this->latestRevisionBefore = $latestRevisionBefore;
		$this->reason = $reason;
		$this->archivedRevisionCount = $archivedRevisionCount;
	}

	/**
	 * @inheritDoc
	 */
	public function getPageRecordBefore(): ExistingPageRecord {
		// Overwritten to guarantee that the return value is not null.
		// XXX: This may not work for a reconsolidation version of this event!
		// @phan-suppress-next-line PhanTypeMismatchReturnNullable
		return parent::getPageRecordBefore();
	}

	/**
	 * @inheritDoc
	 *
	 * @return null
	 */
	public function getPageRecordAfter(): ?ExistingPageRecord {
		return null;
	}

	/**
	 * Returns the page that was deleted, as it was before the deletion.
	 */
	public function getDeletedPage(): ProperPageIdentity {
		return $this->getPageRecordBefore();
	}

	/**
	 * Returns the page that was deleted, as it was before the deletion.
	 *
	 * @deprecated since 1.44, use getDeletedPage() instead.
	 */
	public function getPage(): ProperPageIdentity {
		return $this->getDeletedPage();
	}

	/**
	 * Returns the revision that was the page's latest revision when the
	 * page was deleted.
	 */
	public function getLatestRevisionBefore(): RevisionRecord {
		return $this->latestRevisionBefore;
	}

	/**
	 * Returns the reason for deletion, as supplied by the user.
	 */
	public function getReason(): string {
		return $this->reason;
	}

	/**
	 * Returns the number of revisions archived by the deletion.
	 */
	public function getArchivedRevisionCount(): int {
		return $this->archivedRevisionCount;
	}

	/**
	 * Whether the deleted revisions and log have been suppressed, so they
	 * are not visible in the regular deletion log.
	 */
	public function isSuppressed(): bool {
		return $this->hasFlag( self::FLAG_SUPPRESSED );
	}

}
