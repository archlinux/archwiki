<?php
/**
 * Special:Gadgets, provides a preview of MediaWiki:Gadgets.
 *
 * @file
 * @ingroup SpecialPage
 * @author Daniel Kinzler, brightbyte.de
 * @copyright © 2007 Daniel Kinzler
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\Gadgets;

use Html;
use HTMLForm;
use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use Sanitizer;
use SpecialPage;
use Title;
use Xml;

class SpecialGadgets extends SpecialPage {
	public function __construct() {
		parent::__construct( 'Gadgets', '', true );
	}

	/**
	 * @param string|null $par Parameters passed to the page
	 */
	public function execute( $par ) {
		$parts = $par !== null ? explode( '/', $par ) : [];

		if ( count( $parts ) == 2 && $parts[0] == 'export' ) {
			$this->showExportForm( $parts[1] );
		} else {
			$this->showMainForm();
		}
	}

	/**
	 * @param string $gadgetName
	 * @return string
	 */
	private function makeAnchor( $gadgetName ) {
		return 'gadget-' . Sanitizer::escapeIdForAttribute( $gadgetName );
	}

	/**
	 * Displays form showing the list of installed gadgets
	 */
	public function showMainForm() {
		$output = $this->getOutput();
		$this->setHeaders();
		$this->addHelpLink( 'Extension:Gadgets' );
		$output->setPageTitle( $this->msg( 'gadgets-title' ) );
		$output->addWikiMsg( 'gadgets-pagetext' );

		$gadgets = GadgetRepo::singleton()->getStructuredList();
		if ( !$gadgets ) {
			return;
		}

		$services = MediaWikiServices::getInstance();

		$output->disallowUserJs();
		$lang = $this->getLanguage();
		$langSuffix = "";
		if ( !$lang->equals( $services->getContentLanguage() ) ) {
			$langSuffix = "/" . $lang->getCode();
		}

		$listOpen = false;

		$editDefinitionMessage = $this->getUser()->isAllowed( 'gadgets-definition-edit' )
			? 'edit'
			: 'viewsource';
		$editInterfaceMessage = $this->getUser()->isAllowed( 'editinterface' )
			? 'gadgets-editdescription'
			: 'gadgets-viewdescription';

		$linkRenderer = $this->getLinkRenderer();
		$skinFactory = $services->getSkinFactory();
		foreach ( $gadgets as $section => $entries ) {
			if ( $section !== false && $section !== '' ) {
				$t = Title::makeTitleSafe( NS_MEDIAWIKI, "Gadget-section-$section$langSuffix" );
				$lnkTarget = $t
					? $linkRenderer->makeLink( $t, $this->msg( $editInterfaceMessage )->text(),
						[], [ 'action' => 'edit' ] )
					: htmlspecialchars( $section );
				$lnk = "&#160; &#160; [$lnkTarget]";

				$ttext = $this->msg( "gadget-section-$section" )->parse();

				if ( $listOpen ) {
					$output->addHTML( Xml::closeElement( 'ul' ) . "\n" );
					$listOpen = false;
				}

				$output->addHTML( Html::rawElement( 'h2', [], $ttext . $lnk ) . "\n" );
			}

			/**
			 * @var $gadget Gadget
			 */
			foreach ( $entries as $gadget ) {
				$name = $gadget->getName();
				$t = Title::makeTitleSafe( NS_MEDIAWIKI, "Gadget-{$name}$langSuffix" );
				if ( !$t ) {
					continue;
				}

				$links = [];
				$definitionTitle = GadgetRepo::singleton()->getGadgetDefinitionTitle( $name );
				if ( $definitionTitle ) {
					$links[] = $linkRenderer->makeLink(
						$definitionTitle,
						$this->msg( $editDefinitionMessage )->text(),
						[],
						[ 'action' => 'edit' ]
					);
				}
				$links[] = $linkRenderer->makeLink(
					$t,
					$this->msg( $editInterfaceMessage )->text(),
					[],
					[ 'action' => 'edit' ]
				);
				$links[] = $linkRenderer->makeLink(
					$this->getPageTitle( "export/{$name}" ),
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
				$lnk = [];
				foreach ( $gadget->getPeers() as $peer ) {
					$lnk[] = Html::element(
						'a',
						[ 'href' => '#' . $this->makeAnchor( $peer ) ],
						$peer
					);
				}
				foreach ( $gadget->getScriptsAndStyles() as $codePage ) {
					$t = Title::newFromText( $codePage );
					if ( !$t ) {
						continue;
					}
					$lnk[] = $linkRenderer->makeLink( $t, $t->getText() );
				}
				$output->addHTML( $lang->commaList( $lnk ) );

				if ( $gadget->isPackaged() ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML( $this->msg( 'gadgets-packaged',
						GadgetRepo::singleton()->titleWithoutPrefix( $gadget->getScripts()[0] ) ) );
					$needLineBreakAfter = true;
				}

				// Portion: Legacy scripts
				if ( $gadget->getLegacyScripts() ) {
					if ( $needLineBreakAfter ) {
						$output->addHTML( '<br />' );
					}
					$output->addHTML( Html::rawElement(
						'span',
						[ 'class' => 'mw-gadget-legacy errorbox' ],
						$this->msg( 'gadgets-legacy' )->parse()
					) );
					$needLineBreakAfter = true;
				}

				// Portion: Show required rights (optional)
				$rights = [];
				foreach ( $gadget->getRequiredRights() as $right ) {
					$rights[] = '* ' . Html::element(
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
						$this->msg( 'gadgets-required-rights', implode( "\n", $rights ), count( $rights ) )->parse()
					);
					$needLineBreakAfter = false;
				}

				// Portion: Show required skins (optional)
				$requiredSkins = $gadget->getRequiredSkins();
				// $requiredSkins can be an array, or true (if all skins are supported)
				if ( is_array( $requiredSkins ) ) {
					$skins = [];
					$validskins = $skinFactory->getSkinNames();
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
				}

				// Portion: Show required actions (optional)
				$actions = $gadget->getRequiredActions();
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

				$output->addHTML( Html::closeElement( 'li' ) . "\n" );
			}
		}

		if ( $listOpen ) {
			$output->addHTML( Html::closeElement( 'ul' ) . "\n" );
		}
	}

	/**
	 * Exports a gadget with its dependencies in a serialized form
	 * @param string $gadget Name of gadget to export
	 */
	public function showExportForm( $gadget ) {
		global $wgScript;

		$this->addHelpLink( 'Extension:Gadgets' );
		$output = $this->getOutput();
		try {
			$g = GadgetRepo::singleton()->getGadget( $gadget );
		} catch ( InvalidArgumentException $e ) {
			$output->showErrorPage( 'error', 'gadgets-not-found', [ $gadget ] );
			return;
		}

		$this->setHeaders();
		$output->setPageTitle( $this->msg( 'gadgets-export-title' ) );
		$output->addWikiMsg( 'gadgets-export-text', $gadget, $g->getDefinition() );

		$exportList = "MediaWiki:gadget-$gadget\n";
		foreach ( $g->getScriptsAndStyles() as $page ) {
			$exportList .= "$page\n";
		}

		$htmlForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
		$htmlForm
			->addHiddenField( 'title', SpecialPage::getTitleFor( 'Export' )->getPrefixedDBKey() )
			->addHiddenField( 'pages', $exportList )
			->addHiddenField( 'wpDownload', '1' )
			->addHiddenField( 'templates', '1' )
			->setAction( $wgScript )
			->setMethod( 'get' )
			->setSubmitText( $this->msg( 'gadgets-export-download' )->text() )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'wiki';
	}
}
