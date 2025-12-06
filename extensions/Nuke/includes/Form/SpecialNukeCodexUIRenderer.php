<?php

namespace MediaWiki\Extension\Nuke\Form;

use Exception;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Extension\Nuke\NukeContext;
use MediaWiki\Extension\Nuke\SpecialNuke;
use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Html\Html;
use MediaWiki\Html\ListToggle;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use Wikimedia\Codex\Utility\Codex;

class SpecialNukeCodexUIRenderer extends SpecialNukeUIRenderer {

	/**
	 * The localized title of the current special page.
	 *
	 * @see {@link SpecialPage::getPageTitle}
	 */
	protected Title $pageTitle;

	public function __construct(
		NukeContext $context,
		SpecialNuke $specialNuke,
		protected readonly Codex $codex,
		private readonly RepoGroup $repoGroup,
		private readonly LinkRenderer $linkRenderer,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly RedirectLookup $redirectLookup,
	) {
		parent::__construct( $context );

		$this->pageTitle = $specialNuke->getPageTitle();
	}

	protected function getTargetField(): string {
		return $this->codex
			->field()
			->setId( "nuke-target" )
			->setLabel(
				$this->codex
					->Label()
					->setLabelText( $this->msg( 'nuke-userorip' ) )
					->build()
			)
			->setFields( [
				$this->codex
					->textInput()
					->setName( "target" )
					->setValue( $this->context->getTarget() )
					->build()
					->getHtml()
			] )
			->build()
			->getHtml();
	}

	protected function getPatternField(): string {
		return $this->codex
			->field()
			->setLabel(
				$this->codex
					->Label()
					->setLabelText( $this->msg( 'nuke-pattern' )->parse() )
					->build()
			)
			->setFields( [
				$this->codex
					->textInput()
					->setInputId( "nuke-pattern" )
					->setName( "pattern" )
					->setValue( $this->context->getPattern() )
					->build()
					->getHtml()
			] )
			->build()
			->getHtml();
	}

	protected function getNamespacesField(): string {
		$namespaces = $this->context->getNamespaces();
		return $this->codex
			->field()
			->setId( "nuke-namespace" )
			->setLabel( $this->codex
				->Label()
				->setLabelText( $this->msg( 'nuke-namespace' ) )
				->build()
			)
			->setFields( [
				$this->codex
					->textArea()
					->setTextAreaAttributes( [
						"rows" => 1,
						"class" => "ext-nuke-form-namespace-raw"
					] )
					->setName( "namespace" )
					->setValue(
						$namespaces != null ?
							implode(
								"\n",
								array_map(
									'strval',
									$namespaces
								)
							) :
							''
					)
					->build()
					->getHtml()
			] )
			->build()
			->getHtml();
	}

	protected function getLimitField(): string {
		return $this->codex
			->field()
			->setLabel( $this->codex
				->Label()
				->setLabelText( $this->msg( 'nuke-maxpages' ) )
				->build()
			)
			->setFields( [
				$this->codex
					->textInput()
					->setInputId( "nuke-limit" )
					->setName( "limit" )
					->setValue( strval( $this->context->getLimit() ) )
					->setType( 'number' )
					->build()
					->getHtml()
			] )
			->build()
			->getHtml();
	}

	protected function getDateRangeField(): string {
		$nukeMaxAge = $this->context->getNukeMaxAge();
		$nukeMaxAgeInDays = $this->context->getNukeMaxAgeInDays();
		$recentChangesMaxAgeInDays = $this->context->getRecentChangesMaxAgeInDays();
		$minDate = date( 'Y-m-d', time() - $nukeMaxAge );
		try {
			$dateFrom = $this->context->getDateFrom();
			// FIXME: Should be changed to catch DateMalformedStringException in PHP 8.3+.
			// See T358667 for the status of PHP 8.3.
		} catch ( Exception ) {
			$dateFrom = null;
		}
		if ( $dateFrom ) {
			$dateFrom = $dateFrom->format( 'Y-m-d' );
		} else {
			$dateFrom = $minDate;
		}
		try {
			$dateTo = $this->context->getDateTo();
			// FIXME: Should be changed to catch DateMalformedStringException in PHP 8.3+.
			// See T358667 for the status of PHP 8.3.
		} catch ( Exception ) {
			$dateTo = null;
		}
		if ( $dateTo ) {
			$dateTo = $dateTo->format( 'Y-m-d' );
		} else {
			$dateTo = "";
		}

		$fromDateField = $this->codex
			->field()
			->setLabel( $this->codex
				->label()
				->setLabelText( $this->msg( 'nuke-date-from' ) )
				->build()
			)
			->setFields( [
				$this->codex
					->textInput()
					->setInputId( "nuke-dateFrom" )
					->setName( 'dateFrom' )
					->setValue( $dateFrom )
					->setType( 'date' )
					->setInputAttributes( [
						'min' => $minDate
					] )
					->build()
					->getHtml()
			] )
			->setAttributes( [
				"class" => "ext-nuke-form-dateFrom"
			] )
			->build()
			->getHtml();
		$toDateField = $this->codex
			->field()
			->setLabel( $this->codex
				->label()
				->setLabelText( $this->msg( 'nuke-date-to' ) )
				->build()
			)
			->setFields( [
				$this->codex
					->textInput()
					->setInputId( "nuke-dateTo" )
					->setName( 'dateTo' )
					->setValue( $dateTo == null ? "" : $dateTo )
					->setType( 'date' )
					->setInputAttributes( [
						'min' => $minDate
					] )
					->build()
					->getHtml()
			] )
			->setAttributes( [
				"class" => "ext-nuke-form-dateTo"
			] )
			->build()
			->getHtml();
		$helperField = Html::rawElement(
			'p',
			[
				'class' => 'ext-nuke-form-dateHelperText'
			],
			$this->getDateRangeHelperText(
				$nukeMaxAgeInDays,
				$recentChangesMaxAgeInDays
			)->parse()
		);

		return HTML::rawElement(
			'div',
			[],
			HTML::rawElement(
				'div',
				[
					"class" => "ext-nuke-form-dateRange"
				],
				$fromDateField . $toDateField
			) . $helperField
		);
	}

	protected function getPageSizeRangeField(): string {
		$minSize = $this->codex
			->field()
			->setLabel( $this->codex
				->label()
				->setLabelText( $this->msg( 'nuke-minsize' ) )
				->build()
			)
			->setFields( [
				$this->codex
					->textInput()
					->setInputId( "nuke-minPageSize" )
					->setName( 'minPageSize' )
					->setValue( strval( $this->context->getMinPageSize() ) )
					->setType( 'number' )
					->build()
					->getHtml()
			] )
			->setAttributes( [
				"class" => "ext-nuke-form-minPageSize"
			] )
			->build()
			->getHtml();
		$maxSize = $this->codex
			->field()
			->setLabel( $this->codex
				->label()
				->setLabelText( $this->msg( 'nuke-maxsize' )->parse() )
				->build()
			)
			->setFields( [
				$this->codex
					->textInput()
					->setInputId( "nuke-maxPageSize" )
					->setName( 'maxPageSize' )
					->setValue( strval( $this->context->getMaxPageSize() ) )
					->setType( 'number' )
					->build()
					->getHtml()
			] )
			->setAttributes( [
				"class" => "ext-nuke-form-maxPageSize"
			] )
			->build()
			->getHtml();

		return HTML::rawElement(
			'div',
			[
				"class" => "ext-nuke-form-pageSizeRange"
			],
			$minSize . $maxSize
		);
	}

	/**
	 * Get the fields for associated pages
	 *
	 * @return string[] Fields for associated pages
	 */
	protected function getAssociatedPagesFields(): array {
		return [
			Html::rawElement(
				'p', [], $this->msg( 'nuke-associated' )->escaped()
			),
			$this->codex
				->Checkbox()
				->setInputId( "includeTalkPages" )
				->setName( "includeTalkPages" )
				->setLabel(
					$this->codex
						->Label()
						->setLabelText(
							$this->msg( 'nuke-associated-talk' )->parse()
						)
						->build()
				)
				->setValue( 'true' )
				->setChecked( $this->context->getIncludeTalkPages() )
				->build()
				->getHtml(),
			$this->codex
				->Checkbox()
				->setInputId( "includeRedirects" )
				->setName( "includeRedirects" )
				->setLabel(
					$this->codex
						->Label()
						->setLabelText(
							$this->msg( 'nuke-associated-redirect' )->parse()
						)
						->build()
				)
				->setValue( 'true' )
				->setChecked( $this->context->getIncludeRedirects() )
				->build()
				->getHtml()
		];
	}

	protected function getListButton(): string {
		return $this->codex
			->button()
			->setAttributes( [
				'name' => 'action',
				'value' => SpecialNuke::ACTION_LIST
			] )
			->setType( "submit" )
			->setLabel( $this->msg( 'nuke-submit-list' ) )
			->setSize( "medium" )
			->build()
			->getHtml();
	}

	protected function getContinueButton(): string {
		return $this->codex->button()
			->setAttributes( [
				'name' => 'action',
				'value' => SpecialNuke::ACTION_CONFIRM
			] )
			->setType( "submit" )
			->setLabel( $this->msg( 'nuke-submit-continue' ) )
			->setAction( "progressive" )
			->setWeight( "primary" )
			->setSize( "medium" )
			->build()
			->getHtml();
	}

	protected function getPromptForm( bool $canContinue = true ): string {
		$fields = [
			$this->getTargetField(),
			$this->getPatternField(),
			$this->getNamespacesField(),
			$this->getLimitField(),
			$this->getDateRangeField(),
			$this->getPageSizeRangeField(),
			...$this->getAssociatedPagesFields(),
			$this->getListButton()
		];

		// Show 'Continue' button only if we're not in the initial 'prompt' stage, and pages
		// are going to be listed, and the user's access status is allowed.
		if (
			$canContinue &&
			$this->context->getAction() !== SpecialNuke::ACTION_PROMPT &&
			$this->context->getNukeAccessStatus() === NukeContext::NUKE_ACCESS_GRANTED
		) {
			$fields[] = $this->getContinueButton();
		}

		return $this->codex
			->field()
			->setAttributes( [
				"class" => "ext-nuke-form"
			] )
			->setLabel(
				$this->codex
					->Label()
					->setLabelText( $this->msg( 'nuke' ) )
					->build()
			)
			->setFields( $fields )
			->setIsFieldset( true )
			->build()
			->getHtml();
	}

	protected function showValidationMessages(): void {
		$out = $this->getOutput();

		$validationResult = $this->context->validate();
		if ( $validationResult !== true ) {
			$out->addHTML(
				$this->codex
					->message()
					->setType( "error" )
					->setContentText( $validationResult )
					->setAttributes( [
						'class' => 'ext-nuke-form-error'
					] )
					->build()
					->getHtml()
			);
		}
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

		$out->addModuleStyles( [
			'codex-styles',
			'ext.nuke.codex.styles',
		] );
		$out->addModules( 'ext.nuke.codex' );
		$out->addHTML(
			$this->wrapForm( $this->getPromptForm() )
		);
		$this->showValidationMessages();
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

		$out->addModuleStyles( [
			'codex-styles',
			'ext.nuke.codex.styles',
			'mediawiki.interface.helpers.styles'
		] );
		$out->addModules( 'ext.nuke.codex' );
		$messageLabelOutput = "";

		if ( !$pageGroups ) {
			$body = $this->getPromptForm( false );
			$out->addHTML(
				$this->wrapForm( $body )
			);
			$messageLabelOutput .= $this->msg( "nuke-nopages-global" )->parse() . " ";
		} else {
			$body = $this->getPromptForm();
		}

		if ( $hasExcludedResults ) {
			$messageLabelOutput .= $this->msg( "nuke-associated-limited" )->parse() . " ";
		}

		for ( $i = 0; $i < count( $searchNotices ); $i++ ) {
			$messageLabelOutput .= $this->msg( $searchNotices[$i] )->parse() . " ";
		}

		if ( strlen( $messageLabelOutput ) != 0 ) {
			$out->addHTML(
				$this->codex
					->message()
					->setContentHtml(
						$this->codex
							->htmlSnippet()
							->setContent( $messageLabelOutput )
							->build()
					)
					->setType( 'warning' )
					->setAttributes( [
						'class' => 'ext-nuke-form-error'
					] )
					->build()
					->getHtml()
			);
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
		$this->showValidationMessages();
	}

	/**
	 * Get the page checkbox for the given page-actor tuple.
	 *
	 * @param array{0:Title,1:string|false,2?:string,3?:Title} $pageActorTuple
	 * @param bool $isAssociated Whether the page is associated with another page.
	 * @return string
	 * @throws \MWException
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

	protected function getDeleteReason(): string {
		$otherKey = $this->msg( 'deletereasonotherlist' )->inContentLanguage()->text();
		$list = $this->msg( 'deletereason-dropdown' )->inContentLanguage()->text();

		$options = [
			'other' => $otherKey
		];

		// From Html::listDropdownOptions
		$optgroup = false;
		foreach ( explode( "\n", $list ) as $option ) {
			$value = trim( $option );
			if ( $value == '' ) {
				continue;
			}
			if ( substr( $value, 0, 1 ) == '*' && substr( $value, 1, 1 ) != '*' ) {
				# A new group is starting...
				$value = trim( substr( $value, 1 ) );
				if ( $value !== '' &&
					// Do not use the value for 'other' as option group - T251351
					( $value !== $otherKey )
				) {
					$optgroup = $value;
				} else {
					$optgroup = false;
				}
			} elseif ( substr( $value, 0, 2 ) == '**' ) {
				# groupmember
				$opt = trim( substr( $value, 2 ) );
				if ( $optgroup === false ) {
					$options[$opt] = $opt;
				} else {
					$options[$optgroup][$opt] = $opt;
				}
			} else {
				# groupless reason list
				$optgroup = false;
				$options[$option] = $option;
			}
		}

		return $this->codex
			->field()
			->setLabel(
				$this->codex
					->Label()
					->setLabelText( $this->msg( 'deletecomment' ) )
					->build()
			)
			->setFields( [
				$this->codex
					->Select()
					->setId( 'wpDeleteReasonList' )
					->setAttributes( [
						'name' => 'wpDeleteReasonList'
					] )
					->setOptions(
						array_filter(
							$options,
							static function ( $v ) {
								return is_string( $v );
							}
						)
					)
					->setOptGroups(
						array_filter(
							$options,
							static function ( $v ) {
								return !is_string( $v );
							}
						)
					)
					->setSelectedOption( $otherKey )
					->build()
					->getHtml()
			] )
			->build()
			->getHtml();
	}

	protected function getDeleteComment(): string {
		return $this->codex
			->field()
			->setLabel(
				$this->codex
					->Label()
					->setLabelText( $this->msg( 'deleteotherreason' ) )
					->build()
			)
			->setFields( [
				$this->codex
					->textInput()
					->setInputAttributes( [
						'autofocus' => true,
						'maxlength' => CommentStore::COMMENT_CHARACTER_LIMIT
					] )
					->setInputId( "wpReason" )
					->setName( "wpReason" )
					->setValue( $this->context->getDeleteReason() )
					->build()
					->getHtml()
			] )
			->build()
			->getHtml();
	}

	protected function getDeleteButton(): string {
		return $this->codex
			->button()
			->setAttributes( [
				'name' => 'action',
				'value' => SpecialNuke::ACTION_DELETE
			] )
			->setType( "submit" )
			->setLabel( $this->msg( 'nuke-submit-delete' ) )
			->setAction( "destructive" )
			->setWeight( "primary" )
			->setSize( "medium" )
			->build()
			->getHtml();
	}

	/**
	 * @return string
	 */
	protected function getConfirmForm(): string {
		$fields = [
			$this->getDeleteReason(),
			$this->getDeleteComment(),
			$this->getDeleteButton(),
			Html::hidden( 'originalPageList', implode(
				SpecialNuke::PAGE_LIST_SEPARATOR,
				$this->context->getPages()
			) )
		];

		return $this->codex
			->field()
			->setAttributes( [
				"class" => "ext-nuke-form"
			] )
			->setLabel(
				$this->codex
					->Label()
					->setLabelText( $this->msg( 'nuke' ) )
					->build()
			)
			->setFields( $fields )
			->setIsFieldset( true )
			->build()
			->getHtml();
	}

	/** @inheritDoc */
	public function showConfirmForm(): void {
		$out = $this->getOutput();

		$out->addModuleStyles( [
			'codex-styles',
			'ext.nuke.codex.styles',
			'mediawiki.interface.helpers.styles'
		] );

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
				$this->getConfirmForm() .
				'<ul>' .
				implode( '', $pageList ) .
				'</ul>' .
				Html::hidden( 'originalPageList', implode(
					SpecialNuke::PAGE_LIST_SEPARATOR,
					$this->context->getOriginalPages()
				) )
			)
		);
	}

	/** @inheritDoc */
	public function showResultPage( array $deletedPageStatuses ): void {
		$out = $this->getOutput();

		$out->addModuleStyles( [
			'codex-styles',
			'ext.nuke.codex.styles'
		] );

		// Determine what pages weren't deleted.
		// Deselected pages will have a value of `false`, anything else should be either the
		// string "job" or a Status object.re
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
				$statusMessages = $status->getMessages();
				if ( count( $statusMessages ) > 0 ) {
					foreach ( $statusMessages as $message ) {
						$queued[] = $this->msg( $message )->parse();
					}
				} else {
					$queued[] = $this->msg(
						$status->isOK() ? 'nuke-deleted' : 'nuke-not-deleted',
						wfEscapeWikiText( $title->getPrefixedText() )
					)->parse();
				}

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

		$out->addHTML(
			Html::rawElement(
				'a',
				[
					'href' => $this->pageTitle->getLocalURL()
				],
				$this->codex
					->button()
					->setLabel( $this->msg( 'nuke-deletemore' ) )
					->setAction( "default" )
					->setWeight( "normal" )
					->setSize( "medium" )
					->setIconOnly( false )
					->build()
					->getHtml()
			)
		);
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
		$formType = $this->getRequestContext()->getRequest()->getText( 'nukeUI' );

		return Html::rawElement(
			'form',
			[
				'name' => 'massdelete',
				'action' => $this->pageTitle->getLocalURL(),
				'method' => 'POST',
				'enctype' => 'application/x-www-form-urlencoded',
				'class' => [ 'mw-htmlform', 'mw-htmlform-ooui' ],
			],
			$content .
			// Only add in form type if not submitting under the default type.
			( $formType ? Html::input( 'nukeUI', $formType, 'hidden' ) : '' ) .
			Html::input(
				'wpEditToken',
				$this->getRequestContext()->getUser()->getEditToken(),
				'hidden'
			)
		);
	}

	/**
	 * Render the page links. Returns a string in `Title (talk | history)` format.
	 *
	 * @param Title $title The title to render links of
	 * @return string
	 * @throws \MWException
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
			);
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
				(
					$talkPageText == "" ? "" :
						$talkPageText .
						$wordSeparator .
						$pipeSeparator
				) .
				$changesLink
			)->escaped();
	}

}
