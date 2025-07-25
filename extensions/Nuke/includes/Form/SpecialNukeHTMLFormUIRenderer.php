<?php

namespace MediaWiki\Extension\Nuke\Form;

use HtmlArmor;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Exception\MWException;
use MediaWiki\Extension\Nuke\Form\HTMLForm\NukeDateTimeField;
use MediaWiki\Extension\Nuke\NukeContext;
use MediaWiki\Extension\Nuke\SpecialNuke;
use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Html\Html;
use MediaWiki\Html\ListToggle;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use OOUI\FieldsetLayout;
use OOUI\FormLayout;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;
use OOUI\PanelLayout;
use OOUI\Widget;

class SpecialNukeHTMLFormUIRenderer extends SpecialNukeUIRenderer {

	/**
	 * The localized title of the current special page.
	 *
	 * @see {@link SpecialPage::getPageTitle}
	 */
	protected Title $pageTitle;

	private RepoGroup $repoGroup;
	private LinkRenderer $linkRenderer;
	private NamespaceInfo $namespaceInfo;
	private RedirectLookup $redirectLookup;

	/** @inheritDoc */
	public function __construct(
		NukeContext $context,
		SpecialNuke $specialNuke,
		RepoGroup $repoGroup,
		LinkRenderer $linkRenderer,
		NamespaceInfo $namespaceInfo,
		RedirectLookup $redirectLookup
	) {
		parent::__construct( $context );

		$this->pageTitle = $specialNuke->getPageTitle();

		// MediaWiki services
		$this->repoGroup = $repoGroup;
		$this->linkRenderer = $linkRenderer;
		$this->namespaceInfo = $namespaceInfo;
		$this->redirectLookup = $redirectLookup;
	}

	/**
	 * Get the prompt form to be shown to the user. Should appear when both asking for initial
	 * data or showing the page list.
	 *
	 * @param bool $canContinue Whether the form should show a 'Continue' button.
	 * @return string
	 */
	protected function getPromptForm( bool $canContinue = true ): string {
		$this->getOutput()->addModuleStyles( [ 'ext.nuke.styles' ] );

		$nukeMaxAge = $this->context->getNukeMaxAge();
		$nukeMaxAgeInDays = $this->context->getNukeMaxAgeInDays();
		$recentChangesMaxAgeInDays = $this->context->getRecentChangesMaxAgeInDays();

		$formDescriptor = [
			'nuke-target' => [
				'id' => 'nuke-target',
				'default' => $this->context->getTarget(),
				'label' => $this->msg( 'nuke-userorip' )->text(),
				'type' => 'user',
				'ipallowed' => true,
				'name' => 'target',
				'autofocus' => true,
				'autocomplete' => 'off'
			],
			'nuke-pattern' => [
				'id' => 'nuke-pattern',
				'label' => $this->msg( 'nuke-pattern' )->text(),
				'maxLength' => 40,
				'type' => 'text',
				'name' => 'pattern'
			],
			'namespace' => [
				'id' => 'nuke-namespace',
				'type' => 'namespacesmultiselect',
				'label' => $this->msg( 'nuke-namespace' )->text(),
				'help-messages' => [
					new HtmlArmor( '<noscript>' ),
					'nuke-namespace-noscript',
					new HtmlArmor( '</noscript>' )
				],
				'help-inline' => true,
				'exists' => true,
				'all' => 'all',
				'name' => 'namespace'
			],
			'limit' => [
				'id' => 'nuke-limit',
				'maxLength' => 7,
				'default' => 500,
				'label' => $this->msg( 'nuke-maxpages' )->text(),
				'type' => 'int',
				'name' => 'limit'
			],
			'dateFrom' => [
				'id' => 'nuke-dateFrom',
				'class' => NukeDateTimeField::class,
				'cssclass' => 'ext-nuke-promptForm-dateFrom',
				'inline' => true,
				'label' => $this->msg( 'nuke-date-from' )->text(),
				'maxAge' => $nukeMaxAge,
			],
			'dateTo' => [
				'id' => 'nuke-dateTo',
				'class' => NukeDateTimeField::class,
				'cssclass' => 'ext-nuke-promptForm-dateTo',
				'inline' => true,
				'label' => $this->msg( 'nuke-date-to' )->text(),
				'maxAge' => $nukeMaxAge
			],
			'info' => [
				'type' => 'info',
				'default' => $this->getDateRangeHelperText(
					$nukeMaxAgeInDays,
					$recentChangesMaxAgeInDays ),
				'cssclass' => 'ext-nuke-promptForm-dateHelperText',
			],
			'minPageSize' => [
				'id' => 'nuke-minPageSize',
				'maxLength' => 7,
				'cssclass' => 'ext-nuke-promptForm-minPageSize',
				'label' => $this->msg( 'nuke-minsize' )->text(),
				'type' => 'int',
				'name' => 'minPageSize'
			],
			'maxPageSize' => [
				'id' => 'nuke-maxPageSize',
				'maxLength' => 7,
				'cssclass' => 'ext-nuke-promptForm-maxPageSize',
				'label' => $this->msg( 'nuke-maxsize' )->text(),
				'type' => 'int',
				'name' => 'maxPageSize',
			]
		];
		$formDescriptor['associated'] = [
			'type' => 'info',
			'default' => $this->msg( 'nuke-associated' )->escaped(),
			'raw' => true,
		];
		$formDescriptor['includeTalkPages'] = [
			'type' => 'check',
			'label' => $this->msg( 'nuke-associated-talk' )->text(),
			'name' => 'includeTalkPages',
		];
		$formDescriptor['includeRedirects'] = [
			'type' => 'check',
			'label' => $this->msg( 'nuke-associated-redirect' )->text(),
			'name' => 'includeRedirects',
		];

		$promptForm = HTMLForm::factory(
			'ooui', $formDescriptor, $this->getRequestContext()
		)
			->setFormIdentifier( 'massdelete' )
			// Suppressing default submit button to manually control button order.
			->suppressDefaultSubmit()
			->addButton( [
				'label-message' => 'nuke-submit-list',
				'name' => 'action',
				'value' => SpecialNuke::ACTION_LIST
			] );

		// Show 'Continue' button only if we're not in the initial 'prompt' stage, and pages
		// are going to be listed, and the user's access status is allowed.
		if (
			$canContinue &&
			$this->context->getAction() !== SpecialNuke::ACTION_PROMPT &&
			$this->context->getNukeAccessStatus() === NukeContext::NUKE_ACCESS_GRANTED
		) {
			$promptForm->addButton( [
				'classes' => [ 'mw-htmlform-submit' ],
				'label-message' => 'nuke-submit-continue',
				'name' => 'action',
				'value' => SpecialNuke::ACTION_CONFIRM,
				'flags' => [ 'primary', 'progressive' ],
			] );
		}

		$validationResult = $this->context->validate();
		if ( $validationResult !== true ) {
			$promptForm->addFooterHtml( strval(
				new MessageWidget( [
					'classes' => [ 'ext-nuke-promptform-error' ],
					'type' => 'error',
					'label' => $validationResult
				] )
			) );
		}

		$promptForm->prepareForm();

		return $this->getFormFieldsetHtml( $promptForm );
	}

	/** @inheritDoc */
	public function showPromptForm(): void {
		$out = $this->getOutput();

		if ( $this->context->willUseTemporaryAccounts() ) {
			$out->addWikiMsg( 'nuke-tools-tempaccount' );
		} else {
			$out->addWikiMsg( 'nuke-tools' );
		}

		$accessStatus = $this->context->getNukeAccessStatus();

		$this->outputAccessStatusHeader( $out, $accessStatus );

		$out->enableOOUI();
		$out->addHTML(
			$this->wrapForm( $this->getPromptForm() )
		);
	}

	/** @inheritDoc */
	public function showListForm( array $pageGroups, bool $hasExcludedResults, array $searchNotices ): void {
		$target = $this->context->getTarget();
		$out = $this->getOutput();

		if ( $this->context->willUseTemporaryAccounts() ) {
			$out->addWikiMsg( 'nuke-tools-tempaccount' );
		} else {
			$out->addWikiMsg( 'nuke-tools' );
		}

		$accessStatus = $this->context->getNukeAccessStatus();

		$this->outputAccessStatusHeader( $out, $accessStatus );

		$out->addModuleStyles( [ 'ext.nuke.styles', 'mediawiki.interface.helpers.styles' ] );
		$out->enableOOUI();
		$messageLabelOutput = "";

		if ( !$pageGroups ) {
			$body = $this->getPromptForm( false );
			$out->addHTML(
				$this->wrapForm( $body )
			);
			$messageLabelOutput .= $this->msg( "nuke-nopages-global" )->text() . " ";
		} else {
			$body = $this->getPromptForm();
		}

		if ( $hasExcludedResults ) {
			$messageLabelOutput .= $this->msg( "nuke-associated-limited" )->text() . " ";
		}

		for ( $i = 0; $i < count( $searchNotices ); $i++ ) {
			$messageLabelOutput .= $this->msg( $searchNotices[$i] )->text() . " ";
		}

		if ( strlen( $messageLabelOutput ) != 0 ) {
			$out->addHTML( new MessageWidget( [
				'type' => 'warning',
				'classes' => [ 'ext-nuke-promptform-error' ],
				'label' => $messageLabelOutput,
			] ) );
		}

		if ( !$pageGroups ) {
			return;
		}

		$body .=
			// Select: All, None, Invert
			( new ListToggle( $out ) )->getHTML() .
			'<ul>';

		$titles = [];

		foreach ( $pageGroups as $pages ) {
			$body .= '<li>';

			$body .= $this->getPageCheckbox( $pages[0] );
			$titles[] = $pages[0][0]->getPrefixedDBkey();

			if ( count( $pages ) > 1 ) {
				// There are associated pages. Show them in a sub-list.
				$body .= "<ul>\n";
				foreach ( array_slice( $pages, 1 ) as $page ) {
					$body .= '<li>' .
						$this->getPageCheckbox( $page, true ) .
						'</li>';
					$titles[] = $page[0]->getPrefixedDBkey();
				}
				$body .= "</ul>\n";
			}

			$body .= "</li>\n";
		}

		$body .=
			"</ul>\n" .
			Html::hidden( 'originalPageList', implode(
				SpecialNuke::PAGE_LIST_SEPARATOR,
				$titles
			) ) .
			Html::hidden( 'listedTarget', $target );

		$out->addHTML(
			$this->wrapForm( $body )
		);
	}

	/**
	 * Get the page checkbox for the given page-actor tuple.
	 *
	 * @param array{0:Title,1:string|false,2?:string,3?:Title} $pageActorTuple
	 * @param bool $isAssociated Whether the page is associated with another page.
	 * @return string
	 * @throws MWException
	 */
	protected function getPageCheckbox( array $pageActorTuple, bool $isAssociated = false ): string {
		[ $title, $userName ] = $pageActorTuple;

		$localRepo = $this->repoGroup->getLocalRepo();

		$image = $title->inNamespace( NS_FILE ) ? $localRepo->newFile( $title ) : false;
		$thumb = $image && $image->exists() ?
			$image->transform( [ 'width' => 120, 'height' => 120 ], 0 ) :
			false;

		$wordSeparator = $this->msg( 'word-separator' )->escaped();
		$userNameText = ' <span class="mw-changeslist-separator"></span> '
			. $this->msg( 'nuke-editby', $userName )->parse();

		$name = $isAssociated ?
			'associatedPages[]' :
			'pages[]';
		$attribs = [
			'value' => $title->getPrefixedDBkey()
		];
		$html = Html::check(
				$name,
				true,
				$attribs
			) . "\u{00A0}" .
			( $thumb ? $thumb->toHtml( [ 'desc-link' => true ] ) : '' ) .
			$this->getPageLinksHtml( $title ) .
			$wordSeparator .
			"<span class='ext-nuke-italicize'>" . $userNameText . "</span>";

		$redirect = $this->redirectLookup->getRedirectTarget( $title );
		if ( $redirect ) {
			$html .= ' <span class="mw-changeslist-separator"></span> ' .
				$this->msg(
					'nuke-redirectsto',
					$redirect->getText()
				)->parse();
		}

		if ( $isAssociated || $redirect ) {
			$html = "<span class='ext-nuke-italicize'>$html</span>";
		}

		return $html;
	}

	/** @inheritDoc */
	public function showConfirmForm(): void {
		$out = $this->getOutput();

		$out->enableOOUI();
		$out->addModuleStyles( [ 'ext.nuke.styles', 'mediawiki.interface.helpers.styles' ] );

		$otherKey = 'other';
		$options = Html::listDropdownOptions(
			$this->msg( 'deletereason-dropdown' )->inContentLanguage()->text(),
			[ $otherKey => $this->msg( 'deletereasonotherlist' )->inContentLanguage()->text() ]
		);

		$formDescriptor = [
			'wpDeleteReasonList' => [
				'id' => 'wpDeleteReasonList',
				'name' => 'wpDeleteReasonList',
				'type' => 'select',
				'label' => $this->msg( 'deletecomment' )->text(),
				'align' => 'top',
				'options' => $options,
				'default' => $otherKey
			],
			'wpReason' => [
				'id' => 'wpReason',
				'name' => 'wpReason',
				'type' => 'text',
				'label' => $this->msg( 'deleteotherreason' )->text(),
				'align' => 'top',
				'maxLength' => CommentStore::COMMENT_CHARACTER_LIMIT,
				'default' => $this->context->getDeleteReason(),
				'autofocus' => true
			]
		];

		$reasonForm = HTMLForm::factory(
			'ooui', $formDescriptor, $this->getRequestContext()
		)
			->setFormIdentifier( 'massdelete-reason' )
			->addHiddenField( 'action', SpecialNuke::ACTION_DELETE )
			->addHiddenField( 'originalPageList', implode(
				SpecialNuke::PAGE_LIST_SEPARATOR,
				$this->context->getOriginalPages()
			) )
			->setSubmitTextMsg( 'nuke-submit-delete' )
			->setSubmitDestructive()
			->prepareForm();

		$pageList = [];

		$associatedPages = $this->context->getAssociatedPages();
		foreach ( $this->context->getAllPages() as $page ) {
			$title = Title::newFromText( $page );

			$pageList[] = '<li>' .
				$this->getPageLinksHtml( $title ) .
				Html::hidden(
					in_array( $page, $associatedPages ) ?
						'associatedPages[]' :
						'pages[]',
					$title->getPrefixedDBkey()
				) .
				'</li>';
		}

		$out->addWikiMsg( 'nuke-tools-confirm', count( $pageList ) );
		$out->addHTML(
			$this->wrapForm(
				$this->getFormFieldsetHtml( $reasonForm ) .
				'<ul>' .
				implode( '', $pageList ) .
				'</ul>'
			)
		);
	}

	/** @inheritDoc */
	public function showResultPage( array $deletedPageStatuses ): void {
		$out = $this->getOutput();

		$out->addModuleStyles( [ 'ext.nuke.styles', 'mediawiki.interface.helpers.styles' ] );

		// Determine what pages weren't deleted.
		// Deselected pages will have a value of `false`, anything else should be either the
		// string "job" or a Status object.
		$pageStatuses = array_fill_keys( $this->context->getOriginalPages(), false );
		foreach ( $deletedPageStatuses as $page => $value ) {
			$pageStatuses[ $page ] = $value;
		}

		$queuedCount = count( $deletedPageStatuses );
		$skippedCount = count( $pageStatuses ) - $queuedCount;

		$queued = [];
		$skipped = [];

		foreach ( $pageStatuses as $page => $status ) {
			$title = Title::newFromText( $page );
			if ( $status === false ) {
				$skipped[] = $this->getPageLinksHtml( $title );
			} elseif ( $status === 'job' ) {
				$queued[] = $this->msg(
					'nuke-deletion-queued',
					wfEscapeWikiText( $title->getPrefixedText() )
				)->parse();
			} else {
				$queued[] = $this->msg(
					$status->isOK() ? 'nuke-deleted' : 'nuke-not-deleted',
					wfEscapeWikiText( $title->getPrefixedText() )
				)->parse();
				if ( !$status->isOK() ) {
					// Reduce the queuedCount by 1 if it turns out that on of the Status objects
					// is not OK.
					$queuedCount--;
				}
			}
		}

		// Show the main summary, regardless of whether we deleted pages or not.
		$target = $this->context->getTarget();
		if ( $target ) {
			$out->addWikiMsg( 'nuke-delete-summary-user', $queuedCount, $target );
		} else {
			$out->addWikiMsg( 'nuke-delete-summary', $queuedCount );
		}
		if ( $queuedCount ) {
			$out->addHTML(
				"<ul>\n<li>" .
				implode( "</li>\n<li>", $queued ) .
				"</li>\n</ul>\n"
			);
		}
		if ( $skippedCount ) {
			$out->addWikiMsg( 'nuke-skipped-summary', $skippedCount );
			$out->addHTML(
				"<ul>\n<li>" .
				implode( "</li>\n<li>", $skipped ) .
				"</li>\n</ul>\n"
			);
		}
		$out->addWikiMsg( 'nuke-delete-more' );
	}

	/**
	 * Wraps HTML within <form> tags. Should be used in displaying the initial prompt
	 * form and the page list.
	 *
	 * Implementation derived from {@link \MediaWiki\HTMLForm\OOUIHTMLForm::wrapForm}
	 *
	 * @param string $content The HTML content to add inside the <form> tags.
	 * @return string
	 */
	protected function wrapForm( string $content ): string {
		// From \MediaWiki\HTMLForm\OOUIHTMLForm::wrapForm
		$form = new FormLayout( [
			'name' => 'massdelete',
			'action' => $this->pageTitle->getLocalURL(),
			'method' => 'POST',
			'enctype' => 'application/x-www-form-urlencoded',
			'classes' => [ 'mw-htmlform', 'mw-htmlform-ooui' ],
			'content' => new HtmlSnippet( $content ),
		] );
		return strval( $form );
	}

	/**
	 * Get the HTML for the given form, wrapped inside a fieldset and OOUI HTMLForm wrapper.
	 * This exists because there's no way to suppress the <form> element inside HTMLForm,
	 * which is required to let the design of the Special:Nuke form run properly without
	 * JavaScript.
	 *
	 * @param HTMLForm $form
	 * @return string
	 * @throws \OOUI\Exception
	 */
	protected function getFormFieldsetHtml( HTMLForm $form ): string {
		$out = $this->getOutput();
		// Partly from \MediaWiki\HTMLForm\HTMLForm::getHTML
		$out->getMetadata()->setPreventClickjacking( true );
		$out->getOutput()->addModules( 'mediawiki.htmlform' );
		$out->getOutput()->addModuleStyles( 'mediawiki.htmlform.styles' );

		// Only used for validation.
		$form->setSubmitCallback( static function () {
			return true;
		} );

		$submitResult = $form->trySubmit();
		$html = $form->getHeaderHtml()
			. $form->getBody()
			. $form->getHiddenFields()
			. $form->getErrorsOrWarnings( $submitResult, 'error' )
			. $form->getButtons()
			. $form->getFooterHtml();

		// Partly from \MediaWiki\HTMLForm\OOUIHTMLForm::wrapForm
		return strval( new PanelLayout( [
			'classes' => [ 'mw-htmlform-ooui-wrapper' ],
			'expanded' => false,
			'padded' => true,
			'framed' => true,
			'content' => new FieldsetLayout( [
				'label' => $this->msg( 'nuke' )->text(),
				'items' => [
					new Widget( [
						'content' => new HtmlSnippet( $html )
					] ),
				],
			] ),
		] ) );
	}

	/**
	 * Render the page links. Returns a string in `Title (talk | history)` format.
	 *
	 * @param Title $title The title to render links of
	 * @return string
	 * @throws MWException
	 */
	protected function getPageLinksHtml( Title $title ): string {
		$linkRenderer = $this->linkRenderer;

		$wordSeparator = $this->msg( 'word-separator' )->escaped();
		$pipeSeparator = $this->msg( 'pipe-separator' )->escaped();

		$talkPageText = $this->namespaceInfo->isTalk( $title->getNamespace() ) ?
			'' :
			$linkRenderer->makeLink(
				$this->namespaceInfo->getTalkPage( $title ),
				$this->msg( 'sp-contributions-talk' )->text()
			) .
			$wordSeparator .
			$pipeSeparator .
			$wordSeparator;
		$changesLink = $linkRenderer->makeKnownLink(
			$title,
			$this->msg( 'nuke-viewchanges' )->text(),
			[],
			[ 'action' => 'history' ]
		);

		$query = $title->isRedirect() ? [ 'redirect' => 'no' ] : [];
		$attributes = $title->isRedirect() ? [ 'class' => 'ext-nuke-italicize' ] : [];

		return $linkRenderer->makeKnownLink( $title, null, $attributes, $query ) .
			$wordSeparator .
			$this->msg( 'parentheses' )->rawParams(
				$talkPageText .
				$changesLink
			)->escaped();
	}

	/**
	 * Output the access status header, stating:
	 * If the user has access to delete pages
	 * If so, the instructions for deleting pages
	 * If not, the reason why the user does not have access
	 *
	 * @param OutputPage $out
	 * @param int $accessStatus
	 * @return void
	 */
	protected function outputAccessStatusHeader( OutputPage $out, int $accessStatus ) {
		switch ( $accessStatus ) {
			case NukeContext::NUKE_ACCESS_GRANTED:
				// the user has normal access, give them the tools prompt
				$out->addWikiMsg( 'nuke-tools-prompt' );
				break;
			case NukeContext::NUKE_ACCESS_NO_PERMISSION:
				// tell the user that they don't have the permission
				$out->addWikiMsg( 'nuke-tools-notice-noperm' );
				break;
			case NukeContext::NUKE_ACCESS_BLOCKED:
				// tell the user that they are blocked
				$out->addWikiMsg( 'nuke-tools-notice-blocked' );
				break;
			case NukeContext::NUKE_ACCESS_INTERNAL_ERROR:
			default:
				// it's either internal error
				// or a new case we don't support in the code yet
				// in both cases we want to tell the user
				// that there's been an error
				$out->addWikiMsg( 'nuke-tools-notice-error' );
				break;
		}

		// if $accessStatus isn't normal, then tell the user that their access is restricted
		if ( $accessStatus !== NukeContext::NUKE_ACCESS_GRANTED ) {
			$out->addWikiMsg( 'nuke-tools-prompt-restricted' );
		}
	}

	/**
	 * @param float $nukeMaxAgeInDays
	 * @param float $recentChangesMaxAgeInDays
	 * @return string
	 */
	private function getDateRangeHelperText( float $nukeMaxAgeInDays, float $recentChangesMaxAgeInDays ): string {
		$nukeMaxAgeDisplay = ( $nukeMaxAgeInDays > 0 ) ? $nukeMaxAgeInDays : 90;
		if ( $nukeMaxAgeInDays !== $recentChangesMaxAgeInDays ) {
			$recentChangesMaxAgeDisplay = ( $recentChangesMaxAgeInDays > 0 ) ? $recentChangesMaxAgeInDays : 30;
			return $this->msg( 'nuke-daterange-helper-text-max-age-different' )
				->params( [ $nukeMaxAgeDisplay,
					$recentChangesMaxAgeDisplay ] )
				->text();
		}
		return $this->msg( 'nuke-daterange-helper-text-max-age-same' )->params(
			[ $nukeMaxAgeDisplay ]
		)->text();
	}

}
