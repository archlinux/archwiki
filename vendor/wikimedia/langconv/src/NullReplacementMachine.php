<?php

namespace Wikimedia\LangConv;

/**
 * A replacement machine that leaves text untouched.
 */
class NullReplacementMachine extends ReplacementMachine {
	private $baseLanguage;
	private $codes = [];

	/**
	 * Create a NullReplacementMachine.
	 * @param string $baseLanguage A base language code
	 */
	public function __construct( string $baseLanguage ) {
		$this->baseLanguage = $baseLanguage;
		$this->codes[$baseLanguage] = $baseLanguage;
	}

	/**
	 * @inheritDoc
	 */
	public function getCodes() {
		return $this->codes;
	}

	/**
	 * @inheritDoc
	 */
	public function isValidCodePair( $destCode, $invertCode ) {
		return $destCode === $this->baseLanguage &&
			$invertCode === $this->baseLanguage;
	}

	/**
	 * @inheritDoc
	 */
	public function convert( $document, $s, $destCode, $invertCode ) {
		$result = $document->createDocumentFragment();
		$result->appendChild( $document->createTextNode( $s ) );
		return $result;
	}
}
