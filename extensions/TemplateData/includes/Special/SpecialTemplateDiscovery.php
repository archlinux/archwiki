<?php
namespace MediaWiki\Extension\TemplateData\Special;

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\UnlistedSpecialPage;

/**
 * @license GPL-2.0-or-later
 */
class SpecialTemplateDiscovery extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'TemplateDiscovery' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$output = $this->getOutput();
		$this->setHeaders();
		if ( $this->getConfig()->get( 'TemplateDataEnableDiscovery' )
			|| $output->getRequest()->getBool( 'enablediscovery' )
		) {
			$output->addHTML(
				Html::element( 'div', [ 'id' => 'ext-TemplateData-SpecialTemplateSearch-widget' ] )
			);
			$output->addModules( 'ext.templateData.templateDiscovery' );
		} else {
			$output->addWikiTextAsInterface( $this->msg( 'templatedata-template-discovery-disabled' )->text() );
		}
	}

}
