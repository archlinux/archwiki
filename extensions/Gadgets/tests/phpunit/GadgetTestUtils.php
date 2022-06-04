<?php

use MediaWiki\Extension\Gadgets\Gadget;
use MediaWiki\Extension\Gadgets\GadgetResourceLoaderModule;
use MediaWiki\Extension\Gadgets\MediaWikiGadgetsDefinitionRepo;
use Wikimedia\TestingAccessWrapper;

class GadgetTestUtils {
	/**
	 * @param string $line
	 * @return Gadget
	 */
	public static function makeGadget( $line ) {
		$repo = new MediaWikiGadgetsDefinitionRepo();
		return $repo->newFromDefinition( $line, 'misc' );
	}

	public static function makeGadgetModule( Gadget $g ) {
		$module = TestingAccessWrapper::newFromObject(
			new GadgetResourceLoaderModule( [ 'id' => null ] )
		);
		$module->gadget = $g;
		return $module;
	}
}
