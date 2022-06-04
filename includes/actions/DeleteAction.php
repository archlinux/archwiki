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
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\DeletePage;
use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\Watchlist\WatchlistManager;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * Handle page deletion
 *
 * @ingroup Actions
 */
class DeleteAction extends FormlessAction {

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

	/** @var WatchlistManager */
	protected $watchlistManager;

	/** @var LinkRenderer */
	protected $linkRenderer;

	/** @var BacklinkCacheFactory */
	private $backlinkCacheFactory;

	/** @var ReadOnlyMode */
	protected $readOnlyMode;

	/** @var UserOptionsLookup */
	protected $userOptionsLookup;

	/** @var DeletePageFactory */
	private $deletePageFactory;

	/** @var int */
	private $deleteRevisionsLimit;

	/**
	 * @inheritDoc
	 */
	public function __construct( Page $page, IContextSource $context = null ) {
		parent::__construct( $page, $context );
		$services = MediaWikiServices::getInstance();
		$this->watchlistManager = $services->getWatchlistManager();
		$this->linkRenderer = $services->getLinkRenderer();
		$this->backlinkCacheFactory = $services->getBacklinkCacheFactory();
		$this->readOnlyMode = $services->getReadOnlyMode();
		$this->userOptionsLookup = $services->getUserOptionsLookup();
		$this->deletePageFactory = $services->getDeletePageFactory();
		$this->deleteRevisionsLimit = $services->getMainConfig()->get( 'DeleteRevisionsLimit' );
	}

	public function getName() {
		return 'delete';
	}

	public function onView() {
		return null;
	}

	public function show() {
		$this->useTransactionalTimeLimit();
		$this->addHelpLink( 'Help:Sysop deleting and undeleting' );
		$this->tempDelete();
	}

	protected function tempDelete() {
		$article = $this->getArticle();
		$title = $this->getTitle();
		$context = $this->getContext();
		$user = $context->getUser();
		$request = $context->getRequest();
		$outputPage = $context->getOutput();

		$this->runExecuteChecks();
		$this->prepareOutput( $context->msg( 'delete-confirm', $title->getPrefixedText() ) );

		# Better double-check that it hasn't been deleted yet!
		$article->getPage()->loadPageData(
			$request->wasPosted() ? WikiPage::READ_LATEST : WikiPage::READ_NORMAL
		);
		if ( !$article->getPage()->exists() ) {
			$outputPage->setPageTitle( $context->msg( 'cannotdelete-title', $title->getPrefixedText() ) );
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
		$status = $deletePage
			->setSuppress( $suppress )
			->deleteIfAllowed( $this->getDeleteReason() );

		if ( $status->isGood() ) {
			$deleted = $this->getTitle()->getPrefixedText();

			$outputPage->setPageTitle( $this->msg( 'actioncomplete' ) );
			$outputPage->setRobotPolicy( 'noindex,nofollow' );

			if ( !$deletePage->deletionsWereScheduled()[DeletePage::PAGE_BASE] ) {
				$loglink = '[[Special:Log/delete|' . $this->msg( 'deletionlog' )->text() . ']]';
				$outputPage->addWikiMsg( 'deletedtext', wfEscapeWikiText( $deleted ), $loglink );
				$this->getHookRunner()->onArticleDeleteAfterSuccess( $this->getTitle(), $outputPage );
			} else {
				$outputPage->addWikiMsg( 'delete-scheduled', wfEscapeWikiText( $deleted ) );
			}

			$outputPage->returnToMain();
		} else {
			// This branch is also executed when the status is OK but not good, meaning the page couldn't be found (e.g.
			// because it was deleted in another request)
			$outputPage->setPageTitle( $this->msg( 'cannotdelete-title', $this->getTitle()->getPrefixedText() ) );

			$outputPage->wrapWikiTextAsInterface(
				'error mw-error-cannotdelete',
				Status::wrap( $status )->getWikiText( false, false, $context->getLanguage() )
			);
			$this->showLogEntries();
		}

		$this->watchlistManager->setWatch( $request->getCheck( 'wpWatch' ), $context->getAuthority(), $title );
	}

	private function showHistoryWarnings(): void {
		$context = $this->getContext();
		$title = $this->getTitle();

		// The following can use the real revision count as this is only being shown for users
		// that can delete this page.
		// This, as a side-effect, also makes sure that the following query isn't being run for
		// pages with a larger history, unless the user has the 'bigdelete' right
		// (and is about to delete this page).
		$dbr = wfGetDB( DB_REPLICA );
		$revisions = (int)$dbr->selectField(
			'revision',
			'COUNT(rev_page)',
			[ 'rev_page' => $title->getArticleID() ],
			__METHOD__
		);

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
		$title = $this->getTitle();
		$outputPage = $this->getOutput();

		$backlinkCache = $this->backlinkCacheFactory->getBacklinkCache( $title );
		if ( $backlinkCache->hasLinks( 'pagelinks' ) || $backlinkCache->hasLinks( 'templatelinks' ) ) {
			$outputPage->addHtml(
				Html::warningBox(
					$outputPage->msg( 'deleting-backlinks-warning' )->parse(),
					'plainlinks'
				)
			);
		}

		$subpageQueryLimit = 51;
		$subpages = $title->getSubpages( $subpageQueryLimit );
		$subpageCount = count( $subpages );
		if ( $subpageCount > 0 ) {
			$outputPage->addHtml(
				Html::warningBox(
					$outputPage->msg( 'deleting-subpages-warning', Message::numParam( $subpageCount ) )->parse(),
					'plainlinks'
				)
			);
		}

		$outputPage->addWikiMsg( 'confirmdeletetext' );
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

		// FIXME: Replace (or at least rename) this hook
		$this->getHookRunner()->onArticleConfirmDelete( $this->getArticle(), $outputPage, $reason );

		$this->showForm( $reason );
		$this->showEditReasonsLinks();
		$this->showLogEntries();
	}

	protected function showEditReasonsLinks(): void {
		if ( $this->getContext()->getAuthority()->isAllowed( 'editinterface' ) ) {
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
		return $this->getContext()->getAuthority()->isAllowed( 'suppressrevision' );
	}

	/**
	 * @param string $reason
	 */
	protected function showForm( string $reason ): void {
		$outputPage = $this->getOutput();
		$user = $this->getUser();
		$title = $this->getTitle();

		$checkWatch = $this->userOptionsLookup->getBoolOption( $user, 'watchdeletion' ) ||
			$this->watchlistManager->isWatched( $user, $title );

		$fields = [];

		$dropDownReason = $this->getFormMsg( self::MSG_REASON_DROPDOWN )->inContentLanguage()->text();
		// Add additional specific reasons for suppress
		if ( $this->isSuppressionAllowed() ) {
			$dropDownReason .= "\n" . $this->getFormMsg( self::MSG_REASON_DROPDOWN_SUPPRESS )
					->inContentLanguage()->text();
		}

		$options = Xml::listDropDownOptions(
			$dropDownReason,
			[ 'other' => $this->getFormMsg( self::MSG_REASON_DROPDOWN_OTHER )->inContentLanguage()->text() ]
		);
		$options = Xml::listDropDownOptionsOoui( $options );

		$fields[] = new OOUI\FieldLayout(
			new OOUI\DropdownInputWidget( [
				'name' => 'wpDeleteReasonList',
				'inputId' => 'wpDeleteReasonList',
				'tabIndex' => 1,
				'infusable' => true,
				'value' => '',
				'options' => $options,
			] ),
			[
				'label' => $this->getFormMsg( self::MSG_COMMENT )->text(),
				'align' => 'top',
			]
		);

		// HTML maxlength uses "UTF-16 code units", which means that characters outside BMP
		// (e.g. emojis) count for two each. This limit is overridden in JS to instead count
		// Unicode codepoints.
		$fields[] = new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'name' => 'wpReason',
				'inputId' => 'wpReason',
				'tabIndex' => 2,
				'maxLength' => CommentStore::COMMENT_CHARACTER_LIMIT,
				'infusable' => true,
				'value' => $reason,
				'autofocus' => true,
			] ),
			[
				'label' => $this->getFormMsg( self::MSG_REASON_OTHER )->text(),
				'align' => 'top',
			]
		);

		if ( $user->isRegistered() ) {
			$fields[] = new OOUI\FieldLayout(
				new OOUI\CheckboxInputWidget( [
					'name' => 'wpWatch',
					'inputId' => 'wpWatch',
					'tabIndex' => 3,
					'selected' => $checkWatch,
				] ),
				[
					'label' => $this->msg( 'watchthis' )->text(),
					'align' => 'inline',
					'infusable' => true,
				]
			);
		}
		if ( $this->isSuppressionAllowed() ) {
			$fields[] = new OOUI\FieldLayout(
				new OOUI\CheckboxInputWidget( [
					'name' => 'wpSuppress',
					'inputId' => 'wpSuppress',
					'tabIndex' => 4,
					'selected' => false,
				] ),
				[
					'label' => $this->msg( 'revdelete-suppress' )->text(),
					'align' => 'inline',
					'infusable' => true,
				]
			);
		}

		$fields[] = new OOUI\FieldLayout(
			new OOUI\ButtonInputWidget( [
				'name' => 'wpConfirmB',
				'inputId' => 'wpConfirmB',
				'tabIndex' => 5,
				'value' => $this->getFormMsg( self::MSG_SUBMIT )->text(),
				'label' => $this->getFormMsg( self::MSG_SUBMIT )->text(),
				'flags' => [ 'primary', 'destructive' ],
				'type' => 'submit',
			] ),
			[
				'align' => 'top',
			]
		);

		$fieldset = new OOUI\FieldsetLayout( [
			'label' => $this->getFormMsg( self::MSG_LEGEND )->text(),
			'id' => 'mw-delete-table',
			'items' => $fields,
		] );

		$form = new OOUI\FormLayout( [
			'method' => 'post',
			'action' => $this->getFormAction(),
			'id' => 'deleteconfirm',
		] );
		$form->appendContent(
			$fieldset,
			new OOUI\HtmlSnippet(
				Html::hidden( 'wpEditToken', $user->getEditToken( [ 'delete', $title->getPrefixedText() ] ) )
			)
		);

		$outputPage->addHTML(
			new OOUI\PanelLayout( [
				'classes' => [ 'deletepage-wrapper' ],
				'expanded' => false,
				'padded' => true,
				'framed' => true,
				'content' => $form,
			] )
		);
	}

	/**
	 * @todo Should use Action::checkCanExecute instead
	 */
	protected function runExecuteChecks(): void {
		$permissionStatus = PermissionStatus::newEmpty();
		if ( !$this->getContext()->getAuthority()->definitelyCan( 'delete', $this->getTitle(), $permissionStatus ) ) {
			throw new PermissionsError( 'delete', $permissionStatus );
		}

		if ( $this->readOnlyMode->isReadOnly() ) {
			throw new ReadOnlyError;
		}
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

	/**
	 * @param Message $pageTitle
	 */
	protected function prepareOutput( Message $pageTitle ): void {
		$outputPage = $this->getOutput();
		$outputPage->setPageTitle( $pageTitle );
		$outputPage->addBacklinkSubtitle( $this->getTitle() );
		$outputPage->setRobotPolicy( 'noindex,nofollow' );
	}

	protected function prepareOutputForForm(): void {
		$outputPage = $this->getOutput();
		$outputPage->addModules( 'mediawiki.action.delete' );
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
		$res = $dbr->selectRowCount(
			'revision',
			'*',
			[
				'rev_page' => $this->getTitle()->getArticleID(),
				$dbr->bitAnd( 'rev_deleted', RevisionRecord::DELETED_USER ) . ' = 0'
			],
			__METHOD__,
			[ 'LIMIT' => 2 ]
		);
		return $res > 1;
	}

	public function doesWrites() {
		return true;
	}
}
