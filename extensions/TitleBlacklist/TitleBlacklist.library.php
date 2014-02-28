<?php

class Scribunto_LuaTitleBlacklistLibrary extends Scribunto_LuaLibraryBase {
	public function register() {
		$lib = array(
			'test' => array( $this, 'test' ),
		);

		$this->getEngine()->registerInterface( __DIR__ . '/mw.ext.TitleBlacklist.lua', $lib, array() );
	}

	public function test( $action = null, $title = null ) {
		$this->checkType( 'mw.ext.TitleBlacklist.test', 1, $action, 'string' );
		$this->checkTypeOptional( 'mw.ext.TitleBlacklist.test', 2, $title, 'string', '' );
		$this->incrementExpensiveFunctionCount();
		if ( $title == '' ) {
			$title = $this->getParser()->mTitle->getPrefixedText();
		}
		$entry = TitleBlacklist::singleton()->isBlacklisted( $title, $action );
		if ( $entry ) {
			return array( array(
				'params' => $entry->getParams(),
				'regex' => $entry->getRegex(),
				'raw' => $entry->getRaw(),
				'version' => $entry->getFormatVersion(),
				'message' => $entry->getErrorMessage( $action ),
				'custommessage' => $entry->getCustomMessage()
			) );
		}
		return array( null );
	}

}
