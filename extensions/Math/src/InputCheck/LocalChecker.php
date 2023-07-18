<?php

namespace MediaWiki\Extension\Math\InputCheck;

use Exception;
use MediaWiki\Extension\Math\TexVC\Nodes\TexArray;
use MediaWiki\Extension\Math\TexVC\TexVC;
use Message;

class LocalChecker extends BaseChecker {

	private const VALID_TYPES = [ 'tex', 'inline-tex', 'chem' ];
	private ?Message $error = null;
	private ?TexArray $parseTree = null;

	/**
	 * @param string $tex the TeX input string to be checked
	 * @param string $type the input type
	 */
	public function __construct( $tex = '', string $type = 'tex' ) {
		if ( !in_array( $type, self::VALID_TYPES ) ) {
			$this->error = $this->errorObjectToMessage(
				(object)[ "error" => "Unsupported type passed to LocalChecker: " . $type ], "LocalCheck" );
			return;
		}
		parent::__construct( $tex );
		$options = $type === 'chem' ? [ "usemhchem" => true ] : null;
		try {
			$result = ( new TexVC() )->check( $tex, $options );
		} catch ( Exception $e ) { // @codeCoverageIgnoreStart
			// This is impossible since errors are thrown only if the option debug would be set.
			$this->error = Message::newFromKey( 'math_failure' );
			return;
			// @codeCoverageIgnoreEnd
		}
		if ( $result['status'] === '+' ) {
			$this->isValid = true;
			$this->validTeX = $result['output'];
			$this->parseTree = $result['input'];
		} else {
			$this->error = $this->errorObjectToMessage(
				(object)[ "error" => (object)$result["error"] ],
				"LocalCheck" );
		}
	}

	/**
	 * Returns the string of the last error.
	 * @return ?Message
	 */
	public function getError(): ?Message {
		return $this->error;
	}

	public function getParseTree(): ?TexArray {
		return $this->parseTree;
	}
}
