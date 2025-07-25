<?php

namespace MediaWiki\CheckUser\CheckUser;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserLogPager;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Pager\ContribsPager;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LBFactory;

class SpecialCheckUserLog extends SpecialPage {
	/**
	 * @var array an array of nullable string/integer options.
	 */
	protected array $opts;

	private IReadableDatabase $dbr;

	private LinkBatchFactory $linkBatchFactory;
	private PermissionManager $permissionManager;
	private CommentStore $commentStore;
	private CommentFormatter $commentFormatter;
	private CheckUserLogService $checkUserLogService;
	private UserFactory $userFactory;
	private ActorStore $actorStore;

	public function __construct(
		LinkBatchFactory $linkBatchFactory,
		PermissionManager $permissionManager,
		CommentStore $commentStore,
		CommentFormatter $commentFormatter,
		CheckUserLogService $checkUserLogService,
		UserFactory $userFactory,
		ActorStore $actorStore,
		LBFactory $lbFactory
	) {
		parent::__construct( 'CheckUserLog', 'checkuser-log' );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->permissionManager = $permissionManager;
		$this->commentStore = $commentStore;
		$this->commentFormatter = $commentFormatter;
		$this->checkUserLogService = $checkUserLogService;
		$this->userFactory = $userFactory;
		$this->actorStore = $actorStore;
		$this->dbr = $lbFactory->getReplicaDatabase();
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->addHelpLink( 'Help:Extension:CheckUser' );
		$this->checkPermissions();

		// Blocked users are not allowed to run checkuser queries (bug T157883)
		$block = $this->getUser()->getBlock();
		if ( $block && $block->isSitewide() ) {
			throw new UserBlockedError( $block );
		}

		$out = $this->getOutput();
		$out->addModules( [ 'ext.checkUser' ] );
		$out->addModuleStyles( [
			'ext.checkUser.styles',
			'mediawiki.interface.helpers.styles'
		] );
		$request = $this->getRequest();

		$this->opts = [];

		// Normalise target parameter and ignore if not valid (T217713)
		// It must be valid when making a link to Special:CheckUserLog/<user>.
		// Do not normalize an empty target, as that means "everything" (T265606)
		$this->opts['target'] = trim( $request->getVal( 'cuSearch', $par ?? '' ) );
		if ( $this->opts['target'] !== '' ) {
			$userTitle = Title::makeTitleSafe( NS_USER, $this->opts['target'] );
			$this->opts['target'] = $userTitle ? $userTitle->getText() : '';
		}

		$this->opts['initiator'] = trim( $request->getVal( 'cuInitiator', '' ) );

		$this->opts['reason'] = trim( $request->getVal( 'cuReasonSearch', '' ) );

		// From SpecialContributions.php
		$skip = $request->getText( 'offset' ) || $request->getText( 'dir' ) === 'prev';
		# Offset overrides year/month selection
		if ( !$skip ) {
			$this->opts['year'] = $request->getIntOrNull( 'year' );
			$this->opts['month'] = $request->getIntOrNull( 'month' );

			$this->opts['start'] = $request->getVal( 'start' );
			$this->opts['end'] = $request->getVal( 'end' );
		}

		$this->opts = ContribsPager::processDateFilter( $this->opts );

		$this->addSubtitle();

		$this->displaySearchForm();

		$errorMessageKey = null;

		if (
			$this->opts['target'] !== '' &&
			$this->checkUserLogService->verifyTarget( $this->opts['target'] ) === false
		) {
			$errorMessageKey = 'checkuser-target-nonexistent';
		}
		if ( $this->opts['initiator'] !== '' && $this->verifyInitiator( $this->opts['initiator'] ) === false ) {
			$errorMessageKey = 'checkuser-initiator-nonexistent';
		}

		if ( $errorMessageKey !== null ) {
			// Invalid target was input so show an error message and stop from here
			$out->addHTML(
				Html::errorBox(
					$out->msg( $errorMessageKey )->parse()
				)
			);
			return;
		}

		$pager = new CheckUserLogPager(
			$this->getContext(),
			$this->opts,
			$this->linkBatchFactory,
			$this->commentStore,
			$this->commentFormatter,
			$this->checkUserLogService,
			$this->userFactory,
			$this->actorStore
		);

		$out->addHTML(
			$pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar()
		);
	}

	/**
	 * Add subtitle links to the page
	 */
	private function addSubtitle(): void {
		if ( $this->permissionManager->userHasRight( $this->getUser(), 'checkuser' ) ) {
			$links = [
				$this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUser' ),
					$this->msg( 'checkuser-showmain' )->text()
				),
				$this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'Investigate' ),
					$this->msg( 'checkuser-show-investigate' )->text()
				),
			];

			if ( $this->opts['target'] ) {
				$links[] = $this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUser', $this->opts['target'] ),
					$this->msg( 'checkuser-check-this-user' )->text()
				);

				$links[] = $this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'Investigate' ),
					$this->msg( 'checkuser-investigate-this-user' )->text(),
					[],
					[ 'targets' => $this->opts['target'] ]
				);
			}

			$this->getOutput()->addSubtitle( Html::rawElement(
					'span',
					[ "class" => "mw-checkuser-links-no-parentheses" ],
					Html::openElement( 'span' ) .
					implode(
						Html::closeElement( 'span' ) . Html::openElement( 'span' ),
						$links
					) .
					Html::closeElement( 'span' )
				)
			);
		}
	}

	/**
	 * Use an HTMLForm to create and output the search form used on this page.
	 */
	protected function displaySearchForm() {
		$fields = [
			'target' => [
				'type' => 'user',
				// validation in execute() currently
				'exists' => false,
				'ipallowed' => true,
				'name' => 'cuSearch',
				'size' => 40,
				'label-message' => 'checkuser-log-search-target',
				'default' => $this->opts['target'],
				'id' => 'mw-target-user-or-ip'
			],
			'initiator' => [
				'type' => 'user',
				// validation in execute() currently
				'exists' => false,
				'ipallowed' => true,
				'name' => 'cuInitiator',
				'size' => 40,
				'label-message' => 'checkuser-log-search-initiator',
				'default' => $this->opts['initiator']
			],
			'reason' => [
				'type' => 'text',
				'name' => 'cuReasonSearch',
				'size' => 40,
				'label-message' => 'checkuser-log-search-reason',
				'default' => $this->opts['reason'],
				'help-message' => 'checkuser-log-search-reason-help'
			],
			'start' => [
				'type' => 'date',
				'default' => '',
				'id' => 'mw-date-start',
				'label' => $this->msg( 'date-range-from' )->text(),
				'name' => 'start'
			],
			'end' => [
				'type' => 'date',
				'default' => '',
				'id' => 'mw-date-end',
				'label' => $this->msg( 'date-range-to' )->text(),
				'name' => 'end'
			]
		];

		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() );
		$form->setMethod( 'get' )
			->setWrapperLegendMsg( 'checkuser-search' )
			->setSubmitTextMsg( 'checkuser-search-submit' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Verify if the initiator is valid.
	 *
	 * This is defined by a user having a valid actor ID.
	 * Any user without an actor ID cannot be a valid initiator
	 * as making a check causes an actor ID to be created.
	 *
	 * @param string $initiator The name of the initiator that is to be verified
	 * @return bool|int
	 */
	private function verifyInitiator( string $initiator ) {
		return $this->actorStore->findActorIdByName( $initiator, $this->dbr ) ?? false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'changes';
	}
}
