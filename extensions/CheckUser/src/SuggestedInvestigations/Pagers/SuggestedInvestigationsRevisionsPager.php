<?php

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers;

use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Pager\ContributionsPager;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;

/**
 * A ContributionsPager that allows rendering arbitrary revision IDs
 */
class SuggestedInvestigationsRevisionsPager extends ContributionsPager {

	public function __construct(
		LinkRenderer $linkRenderer,
		LinkBatchFactory $linkBatchFactory,
		HookContainer $hookContainer,
		RevisionStore $revisionStore,
		NamespaceInfo $namespaceInfo,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory,
		IContextSource $context,
		private readonly array $revisionIds,
		array $options,
		?UserIdentity $target,
	) {
		parent::__construct(
			$linkRenderer,
			$linkBatchFactory,
			$hookContainer,
			$revisionStore,
			$namespaceInfo,
			$commentFormatter,
			$userFactory,
			$context,
			$options,
			$target
		);

		if ( $this->isArchive ) {
			$this->revisionIdField = 'ar_rev_id';
			$this->revisionParentIdField = 'ar_parent_id';
			$this->revisionTimestampField = 'ar_timestamp';
			$this->revisionLengthField = 'ar_len';
			$this->revisionDeletedField = 'ar_deleted';
			$this->revisionMinorField = 'ar_minor_edit';
			$this->userNameField = 'ar_user_text';
			$this->pageNamespaceField = 'ar_namespace';
			$this->pageTitleField = 'ar_title';
		}
	}

	/** @inheritDoc */
	protected function getRevisionQuery(): array {
		if ( $this->isArchive ) {
			return $this->revisionStore->newArchiveSelectQueryBuilder( $this->getDatabase() )
				->joinComment()
				->where( [ 'ar_rev_id' => $this->revisionIds ] )
				->getQueryInfo();
		} else {
			return $this->revisionStore->newSelectQueryBuilder( $this->getDatabase() )
				->joinComment()
				->joinPage()
				->select( [ 'page_is_new' ] )
				->where( [ 'rev_id' => $this->revisionIds ] )
				->getQueryInfo();
		}
	}

	/** @inheritDoc */
	public function getIndexField(): string {
		if ( $this->isArchive ) {
			return 'ar_timestamp';
		} else {
			return 'rev_timestamp';
		}
	}
}
