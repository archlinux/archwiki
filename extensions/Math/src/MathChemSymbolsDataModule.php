<?php

namespace MediaWiki\Extension\Math;

use MediaWiki\ResourceLoader as RL;

/**
 * Resource loader module providing extra data from the server to Chem.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */
class MathChemSymbolsDataModule extends RL\Module {

	/** @inheritDoc */
	protected $targets = [ 'desktop', 'mobile' ];

	public function getScript( RL\Context $context ) {
		return 've.ui.MWChemDialog.static.setSymbols(' .
				file_get_contents( __DIR__ . '/../modules/ve-math/chemSymbols.json' ) .
				');';
	}

	public function getDependencies( RL\Context $context = null ) {
		return [
			'ext.math.visualEditor',
		];
	}

	public function enableModuleContentVersion() {
		return true;
	}
}
