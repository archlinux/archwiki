<?php

namespace MediaWiki\Extension\Gadgets\Special;

use InvalidArgumentException;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Extension\Gadgets\GadgetRepo;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MainConfigNames;
use MediaWiki\SpecialPage\SpecialPage;

class ExportPage extends ActionPage {

	public function __construct(
		SpecialGadgets $specialPage,
		private readonly GadgetRepo $gadgetRepo,
	) {
		parent::__construct( $specialPage );
	}

	/**
	 * Exports a gadget with its dependencies in a serialized form
	 * @param array $params
	 */
	public function execute( array $params ) {
		if ( !isset( $params[0] ) ) {
			$this->specialPage->getOutput()->setStatusCode( 400 );
			throw new ErrorPageError( 'error', 'gadgets-subpage-toofewparams' );
		}
		$gadget = $params[0];

		$output = $this->specialPage->getOutput();
		try {
			$g = $this->gadgetRepo->getGadget( $gadget );
		} catch ( InvalidArgumentException ) {
			$output->showErrorPage( 'error', 'gadgets-not-found', [ $gadget ] );
			return;
		}

		$output->setPageTitleMsg( $this->msg( 'gadgets-export-title' ) );
		$output->addWikiMsg( 'gadgets-export-text', $gadget, $g->getDefinition() );

		$exportList = "MediaWiki:gadget-$gadget\n";
		foreach ( $g->getScriptsAndStyles() as $page ) {
			$exportList .= "$page\n";
		}

		$htmlForm = HTMLForm::factory( 'ooui', [], $this->specialPage->getContext() );
		$htmlForm
			->setTitle( SpecialPage::getTitleFor( 'Export' ) )
			->addHiddenField( 'pages', $exportList )
			->addHiddenField( 'wpDownload', '1' )
			->addHiddenField( 'templates', '1' )
			->setAction( $this->specialPage->getConfig()->get( MainConfigNames::Script ) )
			->setMethod( 'get' )
			->setSubmitText( $this->msg( 'gadgets-export-download' )->text() )
			->prepareForm()
			->displayForm( false );
	}

}
