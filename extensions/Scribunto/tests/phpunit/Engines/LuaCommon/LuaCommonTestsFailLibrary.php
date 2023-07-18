<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MWException;

class LuaCommonTestsFailLibrary extends LibraryBase {
	public function __construct() {
		throw new MWException( 'deferLoad library that is never required was loaded anyway' );
	}

	public function register() {
	}
}
