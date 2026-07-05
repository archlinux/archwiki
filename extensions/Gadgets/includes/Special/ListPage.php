<?php

namespace MediaWiki\Extension\Gadgets\Special;

use MediaWiki\Content\ContentHandler;
use MediaWiki\Extension\Gadgets\Gadget;
use MediaWiki\Extension\Gadgets\GadgetRepo;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Skin\SkinFactory;
use MediaWiki\Title\Title;

class ListPage extends ActionPage {

	public function __construct(
		SpecialGadgets $specialPage,
		private readonly GadgetRepo $gadgetRepo,
		private readonly Language $contentLanguage,
		private readonly SkinFactory $skinFactory,
	) {
		parent::__construct( $specialPage );
	}

	/**
	 * Displays form showing the list of installed gadgets
	 */
	public function execute( array $params ) {
		$output = $this->specialPage->getOutput();
		$output->setPageTitleMsg( $this->msg( 'gadgets-title' ) );
		$output->addWikiMsg( 'gadgets-pagetext' );

		$gadgets = $this->gadgetRepo->getStructuredList();
		if ( !$gadgets ) {
			return;
		}

		$output->disallowUserJs();
		$lang = $this->specialPage->getLanguage();
		$langSuffix = "";
		if ( !$lang->equals( $this->contentLanguage ) ) {
			$langSuffix = "/" . $lang->getCode();
		}

		$listOpen = false;

		$editDefinitionMessage = $this->specialPage->getUser()->isAllowed( 'editsitejs' )
			? 'edit'
			: 'viewsource';
		$editInterfaceMessage = $this->specialPage->getUser()->isAllowed( 'editinterface' )
			? 'gadgets-editdescription'
			: 'gadgets-viewdescription';
		$editInterfaceMessageSection = $this->specialPage->getUser()->isAllowed( 'editinterface' )
			? 'gadgets-editsectiontitle'
			: 'gadgets-viewsectiontitle';

		$linkRenderer = $this->specialPage->getLinkRenderer();
		foreach ( $gadgets as $section => $entries ) {
			if ( $section !== false && $section !== '' ) {
				if ( $listOpen ) {
					$output->addHTML( Html::closeElement( 'ul' ) . "\n" );
					$listOpen = false;
				}

				// H2 section heading
				$headingText = $this->msg( "gadget-section-$section" )->parse();
				$output->addHTML( Html::rawElement( 'h2', [], $headingText ) . "\n" );

				// Edit link for the section heading
				$title = Title::makeTitleSafe( NS_MEDIAWIKI, "Gadget-section-$section$langSuffix" );
				$linkTarget = $title
					? $linkRenderer->makeLink( $title, $this->msg( $editInterfaceMessageSection )->text(),
						[], [ 'action' => 'edit' ] )
					: htmlspecialchars( $section );
				$output->addHTML( Html::rawElement( 'p', [],
						$this->msg( 'parentheses' )->rawParams( $linkTarget )->escaped() ) . "\n" );
			}

			/**
			 * @var Gadget $gadget
			 */
			foreach ( $entries as $gadget ) {
				$name = $gadget->getName();
				$title = Title::makeTitleSafe( NS_MEDIAWIKI, "Gadget-{$name}$langSuffix" );
				if ( !$title ) {
					continue;
				}

				$links = [];
				$definitionTitle = $this->gadgetRepo->getGadgetDefinitionTitle( $name );
				if ( $definitionTitle ) {
					$links[] = $linkRenderer->makeLink(
						$definitionTitle,
						$this->msg( $editDefinitionMessage )->text(),
						[],
						[ 'action' => 'edit' ]
					);
				}
				$links[] = $linkRenderer->makeLink(
					$title,
					$this->msg( $editInterfaceMessage )->text(),
					[],
					[ 'action' => 'edit' ]
				);
				$links[] = $linkRenderer->makeLink(
					$this->specialPage->getPageTitle( "export/{$name}" ),
					$this->msg( 'gadgets-export' )->text()
				);

				$nameHtml = $this->msg( "gadget-{$name}" )->parse();

				if ( !$listOpen ) {
					$listOpen = true;
					$output->addHTML( Html::openElement( 'ul' ) );
				}

				$actionsHtml = '&#160;&#160;' .
					$this->msg( 'parentheses' )->rawParams( $lang->pipeList( $links ) )->escaped();
				$output->addHTML(
					Html::openElement( 'li', [ 'id' => $this->makeAnchor( $name ) ] ) .
					$nameHtml . $actionsHtml
				);
				// Whether the next portion of the list item contents needs
				// a line break between it and the next portion.
				// This is set to false after lists, but true after lines of text.
				$needLineBreakAfter = true;

				// Portion: Show files, dependencies, speers
				if ( $needLineBreakAfter ) {
					$output->addHTML( '<br />' );
				}
				$output->addHTML(
					$this->msg( 'gadgets-uses' )->escaped() .
					$this->msg( 'colon-separator' )->escaped()
				);
				$links = [];
				foreach ( $gadget->getPeers() as $peer ) {
					$links[] = Html::element(
						'a',
						[ 'href' => '#' . $this->makeAnchor( $peer ) ],
						$peer
					);
				}
				foreach ( $gadget->getScriptsAndStyles() as $codePage ) {
					$title = Title::newFromText( $codePage );
					if ( !$title ) {
						continue;
					}
					$links[] = $linkRenderer->makeLink( $title, $title->getText() );
				}
				$output->addHTML( $lang->commaList( $links ) );

				if ( $gadget->isPackaged() ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML( $this->msg( 'gadgets-packaged',
						$this->gadgetRepo->titleWithoutPrefix( $gadget->getScripts()[0], $gadget->getName() )
					)->parse() );
					$needLineBreakAfter = true;
				}

				// Portion: Legacy scripts
				if ( $gadget->getLegacyScripts() ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addModuleStyles( 'mediawiki.codex.messagebox.styles' );
					$output->addHTML( Html::errorBox(
						$this->msg( 'gadgets-legacy' )->parse(),
						'',
						'mw-gadget-legacy'
					) );
					$needLineBreakAfter = false;
				}

				if ( $gadget->requiresES6() ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML(
						$this->msg( 'gadgets-requires-es6' )->parse()
					);
					$needLineBreakAfter = true;
				}

				// Portion: Show required rights (optional)
				$rights = [];
				foreach ( $gadget->getRequiredRights() as $right ) {
					$rights[] = Html::element(
						'code',
						[ 'title' => $this->msg( "right-$right" )->plain() ],
						$right
					);
				}
				if ( $rights ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML(
						$this->msg( 'gadgets-required-rights', $lang->commaList( $rights ), count( $rights ) )->parse()
					);
					$needLineBreakAfter = true;
				}

				// Portion: Show required skins (optional)
				$requiredSkins = $gadget->getRequiredSkins();
				$skins = [];
				$validskins = $this->skinFactory->getInstalledSkins();
				foreach ( $requiredSkins as $skinid ) {
					if ( isset( $validskins[$skinid] ) ) {
						$skins[] = $this->msg( "skinname-$skinid" )->plain();
					} else {
						$skins[] = $skinid;
					}
				}
				if ( $skins ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML(
						$this->msg( 'gadgets-required-skins', $lang->commaList( $skins ) )
							->numParams( count( $skins ) )->parse()
					);
					$needLineBreakAfter = true;
				}

				// Portion: Show required actions (optional)
				$actions = [];
				foreach ( $gadget->getRequiredActions() as $action ) {
					$actions[] = Html::element( 'code', [], $action );
				}
				if ( $actions ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML(
						$this->msg( 'gadgets-required-actions', $lang->commaList( $actions ) )
							->numParams( count( $actions ) )->parse()
					);
					$needLineBreakAfter = true;
				}

				// Portion: Show required namespaces (optional)
				$namespaces = $gadget->getRequiredNamespaces();
				if ( $namespaces ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML(
						$this->msg(
							'gadgets-required-namespaces',
							$lang->commaList( array_map( function ( int $ns ) use ( $lang ) {
								return $ns == NS_MAIN
									? $this->msg( 'blanknamespace' )->text()
									: $lang->getFormattedNsText( $ns );
							}, $namespaces ) )
						)->numParams( count( $namespaces ) )->parse()
					);
					$needLineBreakAfter = true;
				}

				// Portion: Show required content models (optional)
				$contentModels = [];
				foreach ( $gadget->getRequiredContentModels() as $model ) {
					$contentModels[] = Html::element(
						'code',
						[ 'title' => ContentHandler::getLocalizedName( $model, $lang ) ],
						$model
					);
				}
				if ( $contentModels ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML(
						$this->msg( 'gadgets-required-contentmodels',
							$lang->commaList( $contentModels ),
							count( $contentModels )
						)->parse()
					);
					$needLineBreakAfter = true;
				}

				// Portion: Show required categories (optional)
				$categories = [];
				foreach ( $gadget->getRequiredCategories() as $category ) {
					$title = Title::makeTitleSafe( NS_CATEGORY, $category );
					$categories[] = $title
						? $linkRenderer->makeLink( $title, $category )
						: htmlspecialchars( $category );
				}
				if ( $categories ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML(
						$this->msg( 'gadgets-required-categories' )
							->rawParams( $lang->commaList( $categories ) )
							->numParams( count( $categories ) )->parse()
					);
					$needLineBreakAfter = true;
				}
				// Show if hidden
				if ( $gadget->isHidden() ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML( $this->msg( 'gadgets-hidden' )->parse() );
					$needLineBreakAfter = true;
				}

				// Show if supports URL load
				if ( $gadget->supportsUrlLoad() ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML( $this->msg( 'gadgets-supports-urlload' )->parse() );
					$needLineBreakAfter = true;
				}

				// Portion: Show on by default (optional)
				if ( $gadget->isOnByDefault() ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML( $this->msg( 'gadgets-default' )->parse() );
					$needLineBreakAfter = true;
				}

				// Show warnings
				$warnings = $this->gadgetRepo->validationWarnings( $gadget );

				if ( $warnings ) {
					$output->addModuleStyles( 'mediawiki.codex.messagebox.styles' );
					$output->addHTML( Html::warningBox( implode( '<br/>', array_map( static function ( $msg ) {
						return $msg->parse();
					}, $warnings ) ) ) );
					$needLineBreakAfter = false;
				}

				$output->addHTML( Html::closeElement( 'li' ) . "\n" );
			}
		}

		if ( $listOpen ) {
			$output->addHTML( Html::closeElement( 'ul' ) . "\n" );
		}
	}

	/**
	 * @param string $gadgetName
	 * @return string
	 */
	private function makeAnchor( $gadgetName ) {
		return 'gadget-' . Sanitizer::escapeIdForAttribute( $gadgetName );
	}

}
