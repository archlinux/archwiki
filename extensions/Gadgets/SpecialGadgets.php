<?php
/**
 * Special:Gadgets, provides a preview of MediaWiki:Gadgets.
 *
 * @file
 * @ingroup SpecialPage
 * @author Daniel Kinzler, brightbyte.de
 * @copyright © 2007 Daniel Kinzler
 * @license GNU General Public License 2.0 or later
 */

class SpecialGadgets extends SpecialPage {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct( 'Gadgets', '', true );
	}

	/**
	 * Main execution function
	 * @param $par array Parameters passed to the page
	 */
	function execute( $par ) {
		$parts = explode( '/', $par );

		if ( count( $parts ) == 2 && $parts[0] == 'export' ) {
			$this->showExportForm( $parts[1] );
		} else {
			$this->showMainForm();
		}
	}

	/**
	 * Displays form showing the list of installed gadgets
	 */
	public function showMainForm() {
		global $wgContLang;

		$output = $this->getOutput();
		$this->setHeaders();
		$output->setPagetitle( $this->msg( 'gadgets-title' ) );
		$output->addWikiMsg( 'gadgets-pagetext' );

		$gadgets = Gadget::loadStructuredList();
		if ( !$gadgets ) {
			return;
		}

		$lang = $this->getLanguage();
		$langSuffix = "";
		if ( $lang->getCode() != $wgContLang->getCode() ) {
			$langSuffix = "/" . $lang->getCode();
		}

		$listOpen = false;

		$editInterfaceMessage = $this->getUser()->isAllowed( 'editinterface' )
			? 'edit'
			: 'viewsource';

		foreach ( $gadgets as $section => $entries ) {
			if ( $section !== false && $section !== '' ) {
				$t = Title::makeTitleSafe( NS_MEDIAWIKI, "Gadget-section-$section$langSuffix" );
				$lnkTarget = $t
					? Linker::link( $t, $this->msg( $editInterfaceMessage )->escaped(), array(), array( 'action' => 'edit' ) )
					: htmlspecialchars( $section );
				$lnk =  "&#160; &#160; [$lnkTarget]";

				$ttext = $this->msg( "gadget-section-$section" )->parse();

				if ( $listOpen ) {
					$output->addHTML( Xml::closeElement( 'ul' ) . "\n" );
					$listOpen = false;
				}

				$output->addHTML( Html::rawElement( 'h2', array(), $ttext . $lnk ) . "\n" );
			}

			/**
			 * @var $gadget Gadget
			 */
			foreach ( $entries as $gadget ) {
				$t = Title::makeTitleSafe( NS_MEDIAWIKI, "Gadget-{$gadget->getName()}$langSuffix" );

				if ( !$t ) {
					continue;
				}

				$links = array();
				$links[] = Linker::link(
					$t,
					$this->msg( $editInterfaceMessage )->escaped(),
					array(),
					array( 'action' => 'edit' )
				);
				$links[] = Linker::link(
					$this->getPageTitle( "export/{$gadget->getName()}" ),
					$this->msg( 'gadgets-export' )->escaped()
				);

				$ttext = $this->msg( "gadget-{$gadget->getName()}" )->parse();

				if ( !$listOpen ) {
					$listOpen = true;
					$output->addHTML( Xml::openElement( 'ul' ) );
				}

				$lnk = '&#160;&#160;' . $this->msg( 'parentheses', $lang->pipeList( $links ) )->text();
				$output->addHTML( Xml::openElement( 'li' ) .
						$ttext . $lnk . "<br />" .
						$this->msg( 'gadgets-uses' )->escaped() .
						$this->msg( 'colon-separator' )->escaped()
				);

				$lnk = array();
				foreach ( $gadget->getScriptsAndStyles() as $codePage ) {
					$t = Title::makeTitleSafe( NS_MEDIAWIKI, $codePage );

					if ( !$t ) {
						continue;
					}

					$lnk[] = Linker::link( $t, htmlspecialchars( $t->getText() ) );
				}
				$output->addHTML( $lang->commaList( $lnk ) );

				$rights = array();
				foreach ( $gadget->getRequiredRights() as $right ) {
					$rights[] = '* ' . $this->msg( "right-$right" )->plain();
				}
				if ( count( $rights ) ) {
					$output->addHTML( '<br />' .
							$this->msg( 'gadgets-required-rights', implode( "\n", $rights ), count( $rights ) )->parse()
					);
				}

				$skins = array();
				$validskins = Skin::getSkinNames();
				foreach ( $gadget->getRequiredSkins() as $skinid ) {
					if ( isset( $validskins[$skinid] ) ) {
						$skins[] = $this->msg( "skinname-$skinid" )->plain();
					} else {
						$skins[] = $skinid;
					}
				}
				if ( count( $skins ) ) {
					$output->addHTML(
						'<br />' .
						$this->msg( 'gadgets-required-skins', $lang->commaList( $skins ) )
							->numParams( count( $skins ) )->parse()
					);
				}

				if ( $gadget->isOnByDefault() ) {
					$output->addHTML( '<br />' . $this->msg( 'gadgets-default' )->parse() );
				}

				$output->addHTML( Xml::closeElement( 'li' ) . "\n" );
			}
		}

		if ( $listOpen ) {
			$output->addHTML( Xml::closeElement( 'ul' ) . "\n" );
		}
	}

	/**
	 * Exports a gadget with its dependencies in a serialized form
	 * @param $gadget String Name of gadget to export
	 */
	public function showExportForm( $gadget ) {
		global $wgScript;

		$output = $this->getOutput();
		$gadgets = Gadget::loadList();
		if ( !isset( $gadgets[$gadget] ) ) {
			$output->showErrorPage( 'error', 'gadgets-not-found', array( $gadget ) );
			return;
		}

		/**
		 * @var $g Gadget
		 */
		$g = $gadgets[$gadget];
		$this->setHeaders();
		$output->setPagetitle( $this->msg( 'gadgets-export-title' ) );
		$output->addWikiMsg( 'gadgets-export-text', $gadget, $g->getDefinition() );

		$exportList = "MediaWiki:gadget-$gadget\n";
		foreach ( $g->getScriptsAndStyles() as $page ) {
			$exportList .= "MediaWiki:$page\n";
		}

		$output->addHTML( Html::openElement( 'form', array( 'method' => 'get', 'action' => $wgScript ) )
			. Html::hidden( 'title', SpecialPage::getTitleFor( 'Export' )->getPrefixedDBKey() )
			. Html::hidden( 'pages', $exportList )
			. Html::hidden( 'wpDownload', '1' )
			. Html::hidden( 'templates', '1' )
			. Xml::submitButton( $this->msg( 'gadgets-export-download' )->text() )
			. Html::closeElement( 'form' )
		);
	}
}
