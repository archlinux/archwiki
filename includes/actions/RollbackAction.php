<?php
/**
 * Edit rollback user interface
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA
 *
 * @file
 * @ingroup Actions
 */

use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\RollbackPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\Watchlist\WatchlistManager;

/**
 * User interface for the rollback action
 *
 * @ingroup Actions
 */
class RollbackAction extends FormAction {

	/** @var IContentHandlerFactory */
	private $contentHandlerFactory;

	/** @var RollbackPageFactory */
	private $rollbackPageFactory;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var WatchlistManager */
	private $watchlistManager;

	/** @var CommentFormatter */
	private $commentFormatter;

	/**
	 * @param Page $page
	 * @param IContextSource|null $context
	 * @param IContentHandlerFactory $contentHandlerFactory
	 * @param RollbackPageFactory $rollbackPageFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param WatchlistManager $watchlistManager
	 * @param CommentFormatter $commentFormatter
	 */
	public function __construct(
		Page $page,
		?IContextSource $context,
		IContentHandlerFactory $contentHandlerFactory,
		RollbackPageFactory $rollbackPageFactory,
		UserOptionsLookup $userOptionsLookup,
		WatchlistManager $watchlistManager,
		CommentFormatter $commentFormatter
	) {
		parent::__construct( $page, $context );
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->rollbackPageFactory = $rollbackPageFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->watchlistManager = $watchlistManager;
		$this->commentFormatter = $commentFormatter;
	}

	public function getName() {
		return 'rollback';
	}

	public function getRestriction() {
		return 'rollback';
	}

	protected function usesOOUI() {
		return true;
	}

	protected function getDescription() {
		return '';
	}

	public function doesWrites() {
		return true;
	}

	public function onSuccess() {
		return false;
	}

	public function onSubmit( $data ) {
		return false;
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setWrapperLegendMsg( 'confirm-rollback-top' );
		$form->setSubmitTextMsg( 'confirm-rollback-button' );
		$form->setTokenSalt( 'rollback' );

		$from = $this->getRequest()->getVal( 'from' );
		if ( $from === null ) {
			throw new BadRequestError( 'rollbackfailed', 'rollback-missingparam' );
		}
		foreach ( [ 'from', 'bot', 'hidediff', 'summary', 'token' ] as $param ) {
			$val = $this->getRequest()->getVal( $param );
			if ( $val !== null ) {
				$form->addHiddenField( $param, $val );
			}
		}
	}

	/**
	 * @throws ErrorPageError
	 * @throws ReadOnlyError
	 * @throws ThrottledError
	 */
	public function show() {
		$this->setHeaders();
		// This will throw exceptions if there's a problem
		$this->checkCanExecute( $this->getUser() );

		if ( !$this->userOptionsLookup->getOption( $this->getUser(), 'showrollbackconfirmation' ) ||
			$this->getRequest()->wasPosted()
		) {
			$this->handleRollbackRequest();
		} else {
			$this->showRollbackConfirmationForm();
		}
	}

	public function handleRollbackRequest() {
		$this->enableTransactionalTimelimit();
		$this->getOutput()->addModuleStyles( 'mediawiki.interface.helpers.styles' );

		$request = $this->getRequest();
		$user = $this->getUser();
		$from = $request->getVal( 'from' );
		$rev = $this->getWikiPage()->getRevisionRecord();
		if ( $from === null ) {
			throw new ErrorPageError( 'rollbackfailed', 'rollback-missingparam' );
		}
		if ( !$rev ) {
			throw new ErrorPageError( 'rollbackfailed', 'rollback-missingrevision' );
		}

		$revUser = $rev->getUser();
		$userText = $revUser ? $revUser->getName() : '';
		if ( $from !== $userText ) {
			throw new ErrorPageError( 'rollbackfailed', 'alreadyrolled', [
				$this->getTitle()->getPrefixedText(),
				wfEscapeWikiText( $from ),
				$userText
			] );
		}

		if ( !$user->matchEditToken( $request->getVal( 'token' ), 'rollback' ) ) {
			throw new ErrorPageError( 'sessionfailure-title', 'sessionfailure' );
		}

		// The revision has the user suppressed, so the rollback has empty 'from',
		// so the check above would succeed in that case.
		// T307278 - Also check if the user has rights to view suppressed usernames
		if ( !$revUser ) {
			if ( $this->getAuthority()->isAllowedAny( 'suppressrevision', 'viewsuppressed' ) ) {
				$revUser = $rev->getUser( RevisionRecord::RAW );
			} else {
				$userFactory = MediaWikiServices::getInstance()->getUserFactory();
				$revUser = $userFactory->newFromName( $this->context->msg( 'rev-deleted-user' )->plain() );
			}
		}

		$rollbackResult = $this->rollbackPageFactory
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable use of raw avoids null here
			->newRollbackPage( $this->getWikiPage(), $this->getAuthority(), $revUser )
			->setSummary( $request->getText( 'summary' ) )
			->markAsBot( $request->getBool( 'bot' ) )
			->rollbackIfAllowed();
		$data = $rollbackResult->getValue();

		if ( $rollbackResult->hasMessage( 'actionthrottledtext' ) ) {
			throw new ThrottledError;
		}

		if ( $rollbackResult->hasMessage( 'alreadyrolled' ) || $rollbackResult->hasMessage( 'cantrollback' ) ) {
			$this->getOutput()->setPageTitle( $this->msg( 'rollbackfailed' ) );
			$errArray = $rollbackResult->getErrors()[0];
			$this->getOutput()->addWikiMsgArray( $errArray['message'], $errArray['params'] );

			if ( isset( $data['current-revision-record'] ) ) {
				/** @var RevisionRecord $current */
				$current = $data['current-revision-record'];

				if ( $current->getComment() != null ) {
					$this->getOutput()->addWikiMsg(
						'editcomment',
						Message::rawParam(
							$this->commentFormatter
								->format( $current->getComment()->text )
						)
					);
				}
			}

			return;
		}

		# NOTE: Permission errors already handled by Action::checkExecute.
		if ( $rollbackResult->hasMessage( 'readonlytext' ) ) {
			throw new ReadOnlyError;
		}

		# XXX: Would be nice if ErrorPageError could take multiple errors, and/or a status object.
		#      Right now, we only show the first error
		foreach ( $rollbackResult->getErrors() as $error ) {
			throw new ErrorPageError( 'rollbackfailed', $error['message'], $error['params'] );
		}

		/** @var RevisionRecord $current */
		$current = $data['current-revision-record'];
		$target = $data['target-revision-record'];
		$newId = $data['newid'];
		$this->getOutput()->setPageTitle( $this->msg( 'actioncomplete' ) );
		$this->getOutput()->setRobotPolicy( 'noindex,nofollow' );

		$old = Linker::revUserTools( $current );
		$new = Linker::revUserTools( $target );

		$currentUser = $current->getUser( RevisionRecord::FOR_THIS_USER, $user );
		$targetUser = $target->getUser( RevisionRecord::FOR_THIS_USER, $user );
		$this->getOutput()->addHTML(
			$this->msg( 'rollback-success' )
				->rawParams( $old, $new )
				->params( $currentUser ? $currentUser->getName() : '' )
				->params( $targetUser ? $targetUser->getName() : '' )
				->parseAsBlock()
		);

		if ( $this->userOptionsLookup->getBoolOption( $user, 'watchrollback' ) ) {
			$this->watchlistManager->addWatchIgnoringRights( $user, $this->getTitle() );
		}

		$this->getOutput()->returnToMain( false, $this->getTitle() );

		if ( !$request->getBool( 'hidediff', false ) &&
			!$this->userOptionsLookup->getBoolOption( $this->getUser(), 'norollbackdiff' )
		) {
			$contentModel = $current->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )
				->getModel();
			$contentHandler = $this->contentHandlerFactory->getContentHandler( $contentModel );
			$de = $contentHandler->createDifferenceEngine(
				$this->getContext(),
				$current->getId(),
				$newId,
				0,
				true
			);
			$de->showDiff( '', '' );
		}
	}

	/**
	 * Enables transactional time limit for POST and GET requests to RollbackAction
	 * @throws ConfigException
	 */
	private function enableTransactionalTimelimit() {
		// If Rollbacks are made POST-only, use $this->useTransactionalTimeLimit()
		wfTransactionalTimeLimit();
		if ( !$this->getRequest()->wasPosted() ) {
			/**
			 * We apply the higher POST limits on GET requests
			 * to prevent logstash.wikimedia.org from being spammed
			 */
			$fname = __METHOD__;
			$trxLimits = $this->context->getConfig()->get( MainConfigNames::TrxProfilerLimits );
			$trxProfiler = Profiler::instance()->getTransactionProfiler();
			$trxProfiler->redefineExpectations( $trxLimits['POST'], $fname );
			DeferredUpdates::addCallableUpdate( static function () use ( $trxProfiler, $trxLimits, $fname
			) {
				$trxProfiler->redefineExpectations( $trxLimits['PostSend-POST'], $fname );
			} );
		}
	}

	private function showRollbackConfirmationForm() {
		$form = $this->getForm();
		if ( $form->show() ) {
			$this->onSuccess();
		}
	}

	protected function getFormFields() {
		return [
			'intro' => [
				'type' => 'info',
				'raw' => true,
				'default' => $this->msg( 'confirm-rollback-bottom' )->parse()
			]
		];
	}
}
