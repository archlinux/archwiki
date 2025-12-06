<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;

class LuaCommonTestsSpecLibrary extends LibraryBase {
	protected string $message;

	/**
	 * @param MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine $engine
	 * @param string $message A string received from ObjectFactory
	 */
	public function __construct( $engine, $message ) {
		parent::__construct( $engine );
		$this->message = $message;
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
