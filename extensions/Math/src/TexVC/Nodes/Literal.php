<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

use MediaWiki\Extension\Math\TexVC\MMLmappings\BaseMethods;
use MediaWiki\Extension\Math\TexVC\MMLmappings\Util\MMLutil;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmpadded;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmstyle;
use MediaWiki\Extension\Math\TexVC\TexUtil;

class Literal extends TexNode {

	/** @var string */
	private $arg;
	private $literals;
	private $extendedLiterals;

	public function __construct( string $arg ) {
		parent::__construct( $arg );
		$this->arg = $arg;
		$this->literals = array_keys( TexUtil::getInstance()->getBaseElements()['is_literal'] );
		$this->extendedLiterals = $this->literals;
		array_push( $this->extendedLiterals,  '\\infty', '\\emptyset' );
	}

	public function renderMML( $arguments = [], $state = [] ) {
		if ( $this->arg === " " ) {
			// Fixes https://gerrit.wikimedia.org/r/c/mediawiki/extensions/Math/+/961711
			// And they creation of empty mo elements.
			return "";
		}
		if ( is_numeric( $this->arg ) ) {
			$mn = new MMLmn( "", $arguments );
			return $mn->encapsulateRaw( $this->arg );
		}
		// is important to split and find chars within curly and differentiate, see tc 459
		$foundOperatorContent = MMLutil::initalParseLiteralExpression( $this->arg );
		if ( !$foundOperatorContent ) {
			$input = $this->arg;
			$operatorContent = null;
		} else {
			$input = $foundOperatorContent[1][0];
			$operatorContent = $foundOperatorContent[2][0];
		}

		// This is rather a workaround:
		// Sometimes literals from TexVC contain complete \\operatorname {asd} hinted as bug tex-2-mml.json
		if ( str_contains( $input, "\\operatorname" ) ) {
			$mi = new MMLmi();
			return $mi->encapsulateRaw( $operatorContent );
		}

		$inputP = MMLutil::inputPreparation( $input );

		// Sieve for Operators
		$bm = new BaseMethods();
		$ret = $bm->checkAndParseOperator( $inputP, $this, $arguments, $operatorContent, $state, false );
		if ( $ret ) {
			return $ret;
		}
		// Sieve for mathchar07 chars
		$bm = new BaseMethods();
		$ret = $bm->checkAndParseMathCharacter( $inputP, $this, $arguments, $operatorContent, false );
		if ( $ret ) {
			return $ret;
		}

		// Sieve for Identifiers
		$ret = $bm->checkAndParseIdentifier( $inputP, $this, $arguments, $operatorContent, false );
		if ( $ret ) {
			return $ret;
		}
		// Sieve for Delimiters
		$ret = $bm->checkAndParseDelimiter( $input, $this, $arguments, $operatorContent );
		if ( $ret ) {
			return $ret;
		}

		// Sieve for Makros
		$ret = BaseMethods::checkAndParse( $inputP, $arguments, $operatorContent, $this, false );
		if ( $ret ) {
			return $ret;
		}

		// Specific
		if ( !( empty( $state['inMatrix'] ) ) && trim( $this->arg ) === '\vline' ) {
			return $this->createVlineElement();
		}

		if ( !( empty( $state['inHBox'] ) ) ) {
			// No mi, if literal is from HBox
			return $input;
		}

		// If falling through all sieves just create an MI element
		$mi = new MMLmi( "", $arguments );
		return $mi->encapsulateRaw( $input ); // $this->arg
	}

	/**
	 * @return string
	 */
	public function getArg(): string {
		return $this->arg;
	}

	public function setArg( $arg ) {
		$this->arg = $arg;
	}

	/**
	 * @return int[]|string[]
	 */
	public function getLiterals(): array {
		return $this->literals;
	}

	/**
	 * @return int[]|string[]
	 */
	public function getExtendedLiterals(): array {
		return $this->extendedLiterals;
	}

	public function extractIdentifiers( $args = null ) {
		return $this->getLiteral( $this->literals, '/^([a-zA-Z\']|\\\\int)$/' );
	}

	public function extractSubscripts() {
		return $this->getLiteral( $this->extendedLiterals, '/^([0-9a-zA-Z+\',-])$/' );
	}

	public function getModIdent() {
		if ( $this->arg === '\\ ' ) {
			return [ '\\ ' ];
		}
		return $this->getLiteral( $this->literals, '/^([0-9a-zA-Z\'])$/' );
	}

	private function getLiteral( $lit, $regexp ) {
		$s = trim( $this->arg );
		if ( preg_match( $regexp, $s ) == 1 ) {
			return [ $s ];
		} elseif ( in_array( $s, $lit, true ) ) {
			 return [ $s ];
		} else {
			return [];
		}
	}

	/**
	 * @return string
	 */
	public function createVlineElement(): string {
		$mrow = new MMLmrow();
		$mpAdded = new MMLmpadded( "", [ "depth" => "0", "height" => "0" ] );
		$mStyle = new MMLmstyle( "", [ "mathsize" => "1.2em" ] );
		$mo = new MMLmo( "", [ "fence" => "false", "stretchy" => "false" ] );
		return $mrow->encapsulateRaw( $mpAdded->encapsulateRaw(
			$mStyle->encapsulateRaw( $mo->encapsulateRaw( "|" ) ) ) );
	}

}
