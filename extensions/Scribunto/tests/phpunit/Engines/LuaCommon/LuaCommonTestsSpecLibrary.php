<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine;

class LuaCommonTestsSpecLibrary extends LibraryBase {
	public function __construct(
		LuaEngine $engine,
		private readonly string $message,
	) {
		parent::__construct( $engine );
	}

	/** @inheritDoc */
	public function register() {
		$lib = [];
		$opts = [
			'message' => $this->message,
		];

		return $this->getEngine()->registerInterface( __DIR__ . '/CommonTestsSpec-lib.lua', $lib, $opts );
	}
}
