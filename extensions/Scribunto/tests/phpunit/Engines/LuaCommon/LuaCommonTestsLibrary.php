<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;

class LuaCommonTestsLibrary extends LibraryBase {
	/** @inheritDoc */
	public function register() {
		$lib = [
			'test' => [ $this, 'test' ],
		];
		$opts = [
			'test' => 'Test option',
		];

		return $this->getEngine()->registerInterface( __DIR__ . '/CommonTests-lib.lua', $lib, $opts );
	}

	/** @inheritDoc */
	public function test() {
		return [ 'Test function' ];
	}
}
