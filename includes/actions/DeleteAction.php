<?php
/**
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

use MediaWiki\Cache\BacklinkCacheFactory;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\DeletePage;
use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\Watchlist\WatchlistManager;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\ReadOnlyMode;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * Handle page deletion
 *
 * @ingroup Actions
 */
class DeleteAction extends FormAction {

	/**
	 * Constants used to localize form fields
	 */
	protected const MSG_REASON_DROPDOWN = 'reason-dropdown';
	protected const MSG_REASON_DROPDOWN_SUPPRESS = 'reason-dropdown-suppress';
	protected const MSG_REASON_DROPDOWN_OTHER = 'reason-dropdown-other';
	protected const MSG_COMMENT = 'comment';
	protected const MSG_REASON_OTHER = 'reason-other';
	protected const MSG_SUBMIT = 'submit';
	protected const MSG_LEGEND = 'legend';
	protected const MSG_EDIT_REASONS = 'edit-reasons';
	protected const MSG_EDIT_REASONS_SUPPRESS = 'edit-reasons-suppress';

	protected WatchlistManager $watchlistManager;
	protected LinkRenderer $linkRenderer;
	private BacklinkCacheFactory $backlinkCacheFactory;
	protected ReadOnlyMode $readOnlyMode;
	protected UserOptionsLookup $userOptionsLookup;
	private DeletePageFactory $deletePageFactory;
	private int $deleteRevisionsLimit;
	private NamespaceInfo $namespaceInfo;
	private TitleFormatter $titleFormatter;
	private TitleFactory $titleFactory;

	private IConnectionProvider $dbProvider;

	/**
	 * @inheritDoc
	 */
	public function __construct( Article $article, IContextSource $context ) {
		parent::__construct( $article, $context );
		$services = MediaWikiServices::getInstance();
		$this->watchlistManager = $services->getWatchlistManager();
		$this->linkRenderer = $services->getLinkRenderer();
		$this->backlinkCacheFactory = $services->getBacklinkCacheFactory();
		$this->readOnlyMode = $services->getReadOnlyMode();
		$this->userOptionsLookup = $services->getUserOptionsLookup();
		$this->deletePageFactory = $services->getDeletePageFactory();
		$this->deleteRevisionsLimit = $services->getMainConfig()->get( MainConfigNames::DeleteRevisionsLimit );
		$this->namespaceInfo = $services->getNamespaceInfo();
		$this->titleFormatter = $services->getTitleFormatter();
		$this->titleFactory = $services->getTitleFactory();
		$this->dbProvider = $services->getDBLoadBalancerFactory();
	}

	public function getName() {
		return 'delete';
	}

	public function onSubmit( $data ) {
		return false;
	}

	public function onSuccess() {
		return false;
	}

	protected function usesOOUI() {
		return true;
	}

	protected function getPageTitle() {
		$title = $this->getTitle();
		return $this->msg( 'delete-confirm' )->plaintextParams( $title->getPrefixedText() );
	}

	public function getRestriction() {
		return 'delete';
	}

	protected function alterForm( HTMLForm $form ) {
		$title = $this->getTitle();
		$form
			->setAction( $this->getFormAction() )
			->setWrapperLegendMsg( $this->getFormMsg( self::MSG_LEGEND ) )
			->setWrapperAttributes( [ 'id' => 'mw-delete-table' ] )
			->suppressDefaultSubmit()
			->setId( 'deleteconfirm' )
			->setTokenSalt( [ 'delete', $title->getPrefixedText() ] );
	}

	public function show() {
		$this->setHeaders();
		$this->useTransactionalTimeLimit();
		$this->addHelpLink( 'Help:Sysop deleting and undeleting' );

		// This will throw exceptions if there's a problem
		$this->checkCanExecute( $this->getUser() );

		$this->tempDelete();
	}

	protected function tempDelete() {
		$article = $this->getArticle();
		$title = $this->getTitle();
		$context = $this->getContext();
		$user = $context->getUser();
		$request = $context->getRequest();
		$outputPage = $context->getOutput();

		# Better double-check that it hasn't been deleted yet!
		$article->getPage()->loadPageData(
			$request->wasPosted() ? WikiPage::READ_LATEST : WikiPage::READ_NORMAL
		);
		if ( !$article->getPage()->exists() ) {
			$outputPage->setPageTitleMsg(
				$context->msg( 'cannotdelete-title' )->plaintextParams( $title->getPrefixedText() )
			);
			$outputPage->wrapWikiMsg( "<div class=\"error mw-error-cannotdelete\">\n$1\n</div>",
				[ 'cannotdelete', wfEscapeWikiText( $title->getPrefixedText() ) ]
			);
			$this->showLogEntries();

			return;
		}

		$token = $request->getVal( 'wpEditToken' );
		if ( !$request->wasPosted() || !$user->matchEditToken( $token, [ 'delete', $title->getPrefixedText() ] ) ) {
			$this->tempConfirmDelete();
			return;
		}

		# Flag to hide all contents of the archived revisions
		$suppress = $request->getCheck( 'wpSuppress' ) &&
			$context->getAuthority()->isAllowed( 'suppressrevision' );

		$context = $this->getContext();
		$deletePage = $this->deletePageFactory->newDeletePage(
			$this->getWikiPage(),
			$context->getAuthority()
		);
		$shouldDeleteTalk = $request->getCheck( 'wpDeleteTalk' ) &&
			$deletePage->canProbablyDeleteAssociatedTalk()->isGood();
		$deletePage->setDeleteAssociatedTalk( $shouldDeleteTalk );
		$status = $deletePage
			->setSuppress( $suppress )
			->deleteIfAllowed( $this->getDeleteReason() );

		if ( $status->isOK() ) {
			$outputPage->setPageTitleMsg( $this->msg( 'actioncomplete' ) );
			$outputPage->setRobotPolicy( 'noindex,nofollow' );

			if ( !$status->isGood() ) {
				// If the page (and/or its talk) couldn't be found (e.g. because it was deleted in another request),
				// let the user know.
				$outputPage->addHTML(
					Html::warningBox(
						$outputPage->parseAsContent(
							Status::wrap( $status )->getWikiText(
								false,
								false,
								$context->getLanguage()
							)
						)
					)
				);
			}

			$this->showSuccessMessages(
				$deletePage->getSuccessfulDeletionsIDs(),
				$deletePage->deletionsWereScheduled()
			);

			if ( !$status->isGood() ) {
				$this->showLogEntries();
			}
			$outputPage->returnToMain();
		} else {
			$outputPage->setPageTitleMsg(
				$this->msg( 'cannotdelete-title' )->plaintextParams( $this->getTitle()->getPrefixedText() )
			);

			$outputPage->wrapWikiTextAsInterface(
				'error mw-error-cannotdelete',
				Status::wrap( $status )->getWikiText( false, false, $context->getLanguage() )
			);
			$this->showLogEntries();
		}

		$this->watchlistManager->setWatch( $request->getCheck( 'wpWatch' ), $context->getAuthority(), $title );
	}

	/**
	 * Display success messages
	 *
	 * @param array $deleted
	 * @param array $scheduled
	 * @return void
	 */
	private function showSuccessMessages( array $deleted, array $scheduled ): void {
		$outputPage = $this->getContext()->getOutput();
		$loglink = '[[Special:Log/delete|' . $this->msg( 'deletionlog' )->text() . ']]';
		$pageBaseDisplayTitle = wfEscapeWikiText( $this->getTitle()->getPrefixedText() );
		$pageTalkDisplayTitle = wfEscapeWikiText( $this->titleFormatter->getPrefixedText(
			$this->namespaceInfo->getTalkPage( $this->getTitle() )
		) );

		$deletedTalk = $deleted[DeletePage::PAGE_TALK] ?? false;
		$deletedBase = $deleted[DeletePage::PAGE_BASE];
		$scheduledTalk = $scheduled[DeletePage::PAGE_TALK] ?? false;
		$scheduledBase = $scheduled[DeletePage::PAGE_BASE];

		if ( $deletedBase && $deletedTalk ) {
			$outputPage->addWikiMsg( 'deleted-page-and-talkpage',
				$pageBaseDisplayTitle,
				$pageTalkDisplayTitle,
				$loglink );
		} elseif ( $deletedBase ) {
			$outputPage->addWikiMsg( 'deletedtext', $pageBaseDisplayTitle, $loglink );
		} elseif ( $deletedTalk ) {
			$outputPage->addWikiMsg( 'deletedtext', $pageTalkDisplayTitle, $loglink );
		}

		// run hook if article was deleted
		if ( $deletedBase ) {
			$this->getHookRunner()->onArticleDeleteAfterSuccess( $this->getTitle(), $outputPage );
		}

		if ( $scheduledBase ) {
			$outputPage->addWikiMsg( 'delete-scheduled', $pageBaseDisplayTitle );
		}

		if ( $scheduledTalk ) {
			$outputPage->addWikiMsg( 'delete-scheduled', $pageTalkDisplayTitle );
		}
	}

	private function showHistoryWarnings(): void {
		$context = $this->getContext();
		$title = $this->getTitle();

		// The following can use the real revision count as this is only being shown for users
		// that can delete this page.
		// This, as a side-effect, also makes sure that the following query isn't being run for
		// pages with a larger history, unless the user has the 'bigdelete' right
		// (and is about to delete this page).
		$revisions = (int)$this->dbProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( 'COUNT(rev_page)' )
			->from( 'revision' )
			->where( [ 'rev_page' => $title->getArticleID() ] )
			->caller( __METHOD__ )
			->fetchField();

		// @todo i18n issue/patchwork message
		$context->getOutput()->addHTML(
			'<strong class="mw-delete-warning-revisions">' .
			$context->msg( 'historywarning' )->numParams( $revisions )->parse() .
			$context->msg( 'word-separator' )->escaped() . $this->linkRenderer->makeKnownLink(
				$title,
				$context->msg( 'history' )->text(),
				[],
				[ 'action' => 'history' ] ) .
			'</strong>'
		);

		if ( $title->isBigDeletion() ) {
			$context->getOutput()->wrapWikiMsg( "<div class='error'>\n$1\n</div>\n",
				[
					'delete-warning-toobig',
					$context->getLanguage()->formatNum( $this->deleteRevisionsLimit )
				]
			);
		}
	}

	protected function showFormWarnings(): void {
		$this->showBacklinksWarning();
		$this->showSubpagesWarnings();
	}

	private function showBacklinksWarning(): void {
		$backlinkCache = $this->backlinkCacheFactory->getBacklinkCache( $this->getTitle() );
		if ( $backlinkCache->hasLinks( 'pagelinks' ) || $backlinkCache->hasLinks( 'templatelinks' ) ) {
			$this->getOutput()->addHTML(
				Html::warningBox(
					$this->msg( 'deleting-backlinks-warning' )->parse(),
					'plainlinks'
				)
			);
		}
	}

	protected function showSubpagesWarnings(): void {
		$title = $this->getTitle();
		$subpageCount = count( $title->getSubpages( 51 ) );
		if ( $subpageCount ) {
			$this->getOutput()->addHTML(
				Html::warningBox(
					$this->msg( 'deleting-subpages-warning' )->numParams( $subpageCount )->parse(),
					'plainlinks'
				)
			);
		}

		if ( !$title->isTalkPage() ) {
			$talkPageTitle = $this->titleFactory->newFromLinkTarget( $this->namespaceInfo->getTalkPage( $title ) );
			$subpageCount = count( $talkPageTitle->getSubpages( 51 ) );
			if ( $subpageCount ) {
				$this->getOutput()->addHTML(
					Html::warningBox(
						$this->msg( 'deleting-talkpage-subpages-warning' )->numParams( $subpageCount )->parse(),
						'plainlinks'
					)
				);
			}
		}
	}

	private function tempConfirmDelete(): void {
		$this->prepareOutputForForm();
		$ctx = $this->getContext();
		$outputPage = $ctx->getOutput();

		$reason = $this->getDefaultReason();

		// If the page has a history, insert a warning
		if ( $this->pageHasHistory() ) {
			$this->showHistoryWarnings();
		}
		$this->showFormWarnings();

		$outputPage->addWikiMsg( 'confirmdeletetext' );

		// FIXME: Replace (or at least rename) this hook
		$this->getHookRunner()->onArticleConfirmDelete( $this->getArticle(), $outputPage, $reason );

		$form = $this->getForm();
		if ( $form->show() ) {
			$this->onSuccess();
		}

		$this->showEditReasonsLinks();
		$this->showLogEntries();
	}

	protected function showEditReasonsLinks(): void {
		if ( $this->getAuthority()->isAllowed( 'editinterface' ) ) {
			$link = '';
			if ( $this->isSuppressionAllowed() ) {
				$link .= $this->linkRenderer->makeKnownLink(
					$this->getFormMsg( self::MSG_REASON_DROPDOWN_SUPPRESS )->inContentLanguage()->getTitle(),
					$this->getFormMsg( self::MSG_EDIT_REASONS_SUPPRESS )->text(),
					[],
					[ 'action' => 'edit' ]
				);
				$link .= $this->msg( 'pipe-separator' )->escaped();
			}
			$link .= $this->linkRenderer->makeKnownLink(
				$this->getFormMsg( self::MSG_REASON_DROPDOWN )->inContentLanguage()->getTitle(),
				$this->getFormMsg( self::MSG_EDIT_REASONS )->text(),
				[],
				[ 'action' => 'edit' ]
			);
			$this->getOutput()->addHTML( '<p class="mw-delete-editreasons">' . $link . '</p>' );
		}
	}

	/**
	 * @return bool
	 */
	protected function isSuppressionAllowed(): bool {
		return $this->getAuthority()->isAllowed( 'suppressrevision' );
	}

	/**
	 * @return array
	 */
	protected function getFormFields(): array {
		$user = $this->getUser();
		$title = $this->getTitle();

		$fields = [];

		$dropDownReason = $this->getFormMsg( self::MSG_REASON_DROPDOWN )->inContentLanguage()->text();
		// Add additional specific reasons for suppress
		if ( $this->isSuppressionAllowed() ) {
			$dropDownReason .= "\n" . $this->getFormMsg( self::MSG_REASON_DROPDOWN_SUPPRESS )
					->inContentLanguage()->text();
		}

		$options = Xml::listDropDownOptions(
			$dropDownReason,
			[ 'other' => $this->getFormMsg( self::MSG_REASON_DROPDOWN_OTHER )->text() ]
		);

		$fields['DeleteReasonList'] = [
			'type' => 'select',
			'id' => 'wpDeleteReasonList',
			'tabindex' => 1,
			'infusable' => true,
			'options' => $options,
			'label' => $this->getFormMsg( self::MSG_COMMENT )->text(),
		];

		// HTML maxlength uses "UTF-16 code units", which means that characters outside BMP
		// (e.g. emojis) count for two each. This limit is overridden in JS to instead count
		// Unicode codepoints.
		$fields['Reason'] = [
			'type' => 'text',
			'id' => 'wpReason',
			'tabindex' => 2,
			'maxlength' => CommentStore::COMMENT_CHARACTER_LIMIT,
			'infusable' => true,
			'default' => $this->getDefaultReason(),
			'autofocus' => true,
			'label' => $this->getFormMsg( self::MSG_REASON_OTHER )->text(),
		];

		$delPage = $this->deletePageFactory->newDeletePage( $this->getWikiPage(), $this->getAuthority() );
		if ( $delPage->canProbablyDeleteAssociatedTalk()->isGood() ) {
			$fields['DeleteTalk'] = [
				'type' => 'check',
				'id' => 'wpDeleteTalk',
				'tabIndex' => 3,
				'default' => false,
				'label-message' => 'deletepage-deletetalk',
			];
		}

		if ( $user->isRegistered() ) {
			$checkWatch = $this->userOptionsLookup->getBoolOption( $user, 'watchdeletion' ) ||
				$this->watchlistManager->isWatched( $user, $title );
			$fields['Watch'] = [
				'type' => 'check',
				'id' => 'wpWatch',
				'tabindex' => 4,
				'default' => $checkWatch,
				'label-message' => 'watchthis',
			];
		}
		if ( $this->isSuppressionAllowed() ) {
			$fields['Suppress'] = [
				'type' => 'check',
				'id' => 'wpSuppress',
				'tabindex' => 5,
				'default' => false,
				'label-message' => 'revdelete-suppress',
			];
		}

		$fields['ConfirmB'] = [
			'type' => 'submit',
			'id' => 'wpConfirmB',
			'tabindex' => 6,
			'buttonlabel' => $this->getFormMsg( self::MSG_SUBMIT )->text(),
			'flags' => [ 'primary', 'destructive' ],
		];

		return $fields;
	}

	/**
	 * @return string
	 */
	protected function getDeleteReason(): string {
		$deleteReasonList = $this->getRequest()->getText( 'wpDeleteReasonList', 'other' );
		$deleteReason = $this->getRequest()->getText( 'wpReason' );

		if ( $deleteReasonList === 'other' ) {
			return $deleteReason;
		} elseif ( $deleteReason !== '' ) {
			// Entry from drop down menu + additional comment
			$colonseparator = $this->msg( 'colon-separator' )->inContentLanguage()->text();
			return $deleteReasonList . $colonseparator . $deleteReason;
		} else {
			return $deleteReasonList;
		}
	}

	/**
	 * Show deletion log fragments pertaining to the current page
	 */
	protected function showLogEntries(): void {
		$deleteLogPage = new LogPage( 'delete' );
		$outputPage = $this->getContext()->getOutput();
		$outputPage->addHTML( Xml::element( 'h2', null, $deleteLogPage->getName()->text() ) );
		LogEventsList::showLogExtract( $outputPage, 'delete', $this->getTitle() );
	}

	protected function prepareOutputForForm(): void {
		$outputPage = $this->getOutput();
		$outputPage->addModules( 'mediawiki.misc-authed-ooui' );
		$outputPage->addModuleStyles( 'mediawiki.action.styles' );
		$outputPage->enableOOUI();
	}

	/**
	 * @return string[]
	 */
	protected function getFormMessages(): array {
		return [
			self::MSG_REASON_DROPDOWN => 'deletereason-dropdown',
			self::MSG_REASON_DROPDOWN_SUPPRESS => 'deletereason-dropdown-suppress',
			self::MSG_REASON_DROPDOWN_OTHER => 'deletereasonotherlist',
			self::MSG_COMMENT => 'deletecomment',
			self::MSG_REASON_OTHER => 'deleteotherreason',
			self::MSG_SUBMIT => 'deletepage-submit',
			self::MSG_LEGEND => 'delete-legend',
			self::MSG_EDIT_REASONS => 'delete-edit-reasonlist',
			self::MSG_EDIT_REASONS_SUPPRESS => 'delete-edit-reasonlist-suppress',
		];
	}

	/**
	 * @param string $field One of the self::MSG_* constants
	 * @return Message
	 */
	protected function getFormMsg( string $field ): Message {
		$messages = $this->getFormMessages();
		if ( !isset( $messages[$field] ) ) {
			throw new InvalidArgumentException( "Invalid field $field" );
		}
		return $this->msg( $messages[$field] );
	}

	/**
	 * @return string
	 */
	protected function getFormAction(): string {
		return $this->getTitle()->getLocalURL( 'action=delete' );
	}

	/**
	 * Default reason to be used for the deletion form
	 *
	 * @return string
	 */
	protected function getDefaultReason(): string {
		$requestReason = $this->getRequest()->getText( 'wpReason' );
		if ( $requestReason ) {
			return $requestReason;
		}

		try {
			return $this->getArticle()->getPage()->getAutoDeleteReason();
		} catch ( TimeoutException $e ) {
			throw $e;
		} catch ( Exception $e ) {
			# if a page is horribly broken, we still want to be able to
			# delete it. So be lenient about errors here.
			# For example, WMF logs show MWException thrown from
			# ContentHandler::checkModelID().
			MWExceptionHandler::logException( $e );
			return '';
		}
	}

	/**
	 * Determines whether a page has a history of more than one revision.
	 * @fixme We should use WikiPage::isNew() here, but it doesn't work right for undeleted pages (T289008)
	 * @return bool
	 */
	private function pageHasHistory(): bool {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'revision' )
			->where( [ 'rev_page' => $this->getTitle()->getArticleID() ] )
			->andWhere(
				[ $dbr->bitAnd( 'rev_deleted', RevisionRecord::DELETED_USER ) . ' = 0' ]
			)->limit( 2 )
			->caller( __METHOD__ )
			->fetchRowCount();

		return $res > 1;
	}
}
