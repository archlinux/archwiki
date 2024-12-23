<?php

namespace MediaWiki\Extension\TitleBlacklist;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\MediaWikiServices;

class LuaTitleBlacklistLibrary extends LibraryBase {
	public function register() {
		$lib = [
			'test' => [ $this, 'test' ],
		];

		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.ext.TitleBlacklist.lua', $lib, []
		);
	}

	public function test( $action = null, $title = null ) {
		$this->checkType( 'mw.ext.TitleBlacklist.test', 1, $action, 'string' );
		$this->checkTypeOptional( 'mw.ext.TitleBlacklist.test', 2, $title, 'string', '' );
		$this->incrementExpensiveFunctionCount();
		if ( $title == '' ) {
			$page = $this->getParser()->getPage();
			if ( !$page ) {
				// Nothing to check
				return [ null ];
			}
			$title = MediaWikiServices::getInstance()->getTitleFormatter()->getPrefixedText( $page );
		}
		$entry = TitleBlacklist::singleton()->isBlacklisted( $title, $action );

		// check if not whitelisted
		$whitelist = TitleBlacklist::singleton()->isWhitelisted( $title, $action );
		if ( $whitelist ) {
			// page is whitelisted, don't continue and return a null object
			return [ null ];
		}

		if ( $entry ) {
			return [ [
				'params' => $entry->getParams(),
				'regex' => $entry->getRegex(),
				'raw' => $entry->getRaw(),
				'version' => $entry->getFormatVersion(),
				'message' => $entry->getErrorMessage( $action ),
				'custommessage' => $entry->getCustomMessage()
			] ];
		}
		return [ null ];
	}

}
