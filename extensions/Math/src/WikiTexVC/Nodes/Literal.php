<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseMethods;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\MathVariant;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\TexClass;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmn;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmpadded;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmstyle;
use MediaWiki\Extension\Math\WikiTexVC\TexUtil;

class Literal extends TexNode {
	private const CURLY_PATTERN = '/(?<start>[\\a-zA-Z\s]+)\{(?<arg>[^}]+)}/';

	/** @var string */
	private $arg;
	/** @var string[] */
	private $literals;
	/** @var string[] */
	private $extendedLiterals;

	public function __construct( string $arg ) {
		parent::__construct( $arg );
		$this->arg = $arg;
		$this->literals = array_keys( TexUtil::getInstance()->getBaseElements()['is_literal'] );
		$this->extendedLiterals = $this->literals;
		array_push( $this->extendedLiterals, '\\infty', '\\emptyset' );
	}

	/**
	 * Gets the arg of the literal or the part that is before
	 * a curly bracket if the expression contains one and matches
	 * {@link self::CURLY_PATTERN}.
	 *
	 * @return string
	 */
	private function getStart(): string {
		if ( preg_match( self::CURLY_PATTERN, $this->arg, $matches ) ) {
			return $matches['start'];
		}
		return $this->arg;
	}

	/**
	 * If the arg matches {@link self::CURLY_PATTERN}, return the
	 * inner content of the curlies.
	 * For example, for if the arg was a{b} this function returns b.
	 *
	 * @return string|null
	 */
	public function getArgFromCurlies(): ?string {
		if ( preg_match( self::CURLY_PATTERN, $this->arg, $matches ) ) {
			return $matches['arg'];
		}
		return null;
	}

	public function changeUnicodeFontInput( string $input, array &$state, array &$arguments ): string {
		$variant = MathVariant::removeMathVariantAttribute( $arguments );
		if ( $variant !== 'normal' ) {
			// If the variant is normal, we do not need to change the input.
			return MathVariant::translate(
				$input,
				$variant
			);
		}
		return $input;
	}

	/** @inheritDoc */
	public function toMMLTree( $arguments = [], &$state = [] ) {
		if ( $this->arg === " " ) {
			// Fixes https://gerrit.wikimedia.org/r/c/mediawiki/extensions/Math/+/961711
			// And they creation of empty mo elements.
			return null;
		}
		if ( isset( $state["intent-params"] ) ) {
			foreach ( $state["intent-params"] as $intparam ) {
				if ( $intparam == $this->arg ) {
					$arguments["arg"] = $intparam;
				}
			}
		}

		if ( isset( $state["intent-params-expl"] ) ) {
			$arguments["arg"] = $state["intent-params-expl"];
		}
		// handle comma as decimal separator https://www.php.net/manual/en/function.is-numeric.php#88041
		if ( ( is_numeric( $this->arg ) || is_numeric( str_replace( ',', '.', $this->arg ) ) )
			&& empty( $state['inHBox'] ) ) {
			if ( ( $arguments['mathvariant'] ?? '' ) === 'italic' ) {
				// If the mathvariant italic does not exist for numbers
				// https://github.com/w3c/mathml/issues/77#issuecomment-2993838911
				$arguments['style'] = trim( ( $arguments['style'] ?? '' ) . ' font-style: italic' );
			}
			$content = $this->changeUnicodeFontInput( $this->arg, $state, $arguments );
			return new MMLmn( "", $arguments, $content );
		}

		// is important to split and find chars within curly and differentiate, see tc 459
		$input = $this->getStart();
		$operatorContent = $this->getArgFromCurlies();
		if ( $operatorContent !== null ) {
			$operatorContent = [ 'foundOC' => $operatorContent ];
		}

		// This is rather a workaround:
		// Sometimes literals from WikiTexVC contain complete \\operatorname {asd} hinted as bug tex-2-mml.json
		if ( str_contains( $input, "\\operatorname" ) ) {
			return new MMLmi( "", [], $operatorContent["foundOC"] );
		}

		$inputP = $input;

		// Sieve for Operators
		$bm = new BaseMethods();
		$noStretchArgs = $arguments;
		// Delimiters and operators should not be stretchy by default when used as literals
		$noStretchArgs['stretchy'] ??= 'false';
		$ret = $bm->checkAndParseOperator( $inputP, $this, $noStretchArgs, $operatorContent, $state, false );
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
		$ret = $bm->checkAndParseDelimiter( $input, $this, $noStretchArgs, $operatorContent );
		if ( $ret ) {
			return $ret;
		}

		// Sieve for Makros
		$ret = BaseMethods::checkAndParse( $inputP, $arguments,
			array_merge( $operatorContent ?? [], $state ?? [] ),
			$this );
		if ( $ret ) {
			return $ret;
		}

		// Specific
		if ( !( empty( $state['inMatrix'] ) ) && trim( $this->arg ) === '\vline' ) {
			return $this->createVlineElement();
		}

		$content = $this->changeUnicodeFontInput( $input, $state, $arguments );
		if ( !( empty( $state['inHBox'] ) ) ) {
			// No mi, if literal is from HBox
			return $content;
		}
		// If falling through all sieves just creates an mi element

		return new MMLmi( "", $arguments, $content );
	}

	public function getArg(): string {
		return $this->arg;
	}

	public function setArg( string $arg ) {
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

	/** @inheritDoc */
	public function extractIdentifiers( $args = null ) {
		return $this->getLiteral( $this->literals, '/^([a-zA-Z\']|\\\\int)$/' );
	}

	/** @inheritDoc */
	public function extractSubscripts() {
		return $this->getLiteral( $this->extendedLiterals, '/^([0-9a-zA-Z+\',-])$/' );
	}

	/** @inheritDoc */
	public function getModIdent() {
		if ( $this->arg === '\\ ' ) {
			return [ '\\ ' ];
		}
		return $this->getLiteral( $this->literals, '/^([0-9a-zA-Z\'])$/' );
	}

	private function getLiteral( array $lit, string $regexp ): array {
		$s = trim( $this->arg );
		if ( preg_match( $regexp, $s ) || in_array( $s, $lit, true ) ) {
			return [ $s ];
		}
		return [];
	}

	public function createVlineElement(): MMLbase {
		return new MMLmrow( TexClass::ORD, [],
			new MMLmpadded( "", [ "depth" => "0", "height" => "0" ],
				new MMLmstyle( "", [ "mathsize" => "1.2em" ],
					new MMLmo( "", [ "fence" => "false", "stretchy" => "false" ], "|" )
				)
			)
		);
	}

	public function appendText( string $text ): void {
		$this->arg .= $text;
	}

}
