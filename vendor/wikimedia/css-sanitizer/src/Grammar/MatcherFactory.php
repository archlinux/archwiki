<?php
/**
 * @file
 * @license https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */

namespace Wikimedia\CSS\Grammar;

use Closure;
use Wikimedia\CSS\Objects\ComponentValueList;
use Wikimedia\CSS\Objects\Token;
use Wikimedia\CSS\Parser\Parser;
use Wikimedia\CSS\Sanitizer\PropertySanitizer;

/**
 * Factory for predefined Grammar matchers
 * @note For security, the attr() and var() functions are not supported,
 * although as a limited exception var() is allowed for color attributes
 * in `::colorFuncs()`.
 */
class MatcherFactory {
	/** @var MatcherFactory|null */
	private static $instance = null;

	/** @var (Matcher|Matcher[])[] Cache of constructed matchers */
	protected $cache = [];

	/** @var string[] length units */
	protected static $lengthUnits = [
		// Font-relative units
		'em', 'rem', 'ex', 'rex',
		'cap', 'rcap', 'ch', 'rch',
		'ic', 'ric', 'lh', 'rlh',
		// Viewport-relative units
		'vw', 'svw', 'lvw', 'dvw',
		'vh', 'svh', 'lvh', 'dvh',
		'vi', 'svi', 'lvi', 'dvi',
		'vb', 'svb', 'lvb', 'dvb',
		'vmin', 'svmin', 'lvmin', 'dvmin',
		'vmax', 'svmax', 'lvmax', 'dvmax',
		// Absolute units
		'cm', 'mm', 'Q', 'in', 'pc', 'pt', 'px',
	];

	/** @var string[] angle units */
	protected static $angleUnits = [ 'deg', 'grad', 'rad', 'turn' ];

	/** @var string[] time units */
	protected static $timeUnits = [ 's', 'ms' ];

	/** @var string[] frequency units */
	protected static $frequencyUnits = [ 'Hz', 'kHz' ];

	/** @var string[] resolution units */
	protected static $resolutionUnits = [ 'dpi', 'dpcm', 'dppx', 'x' ];

	/**
	 * Return a static instance of the factory
	 * @return MatcherFactory
	 */
	public static function singleton() {
		if ( !self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Matcher for optional whitespace
	 * @return Matcher
	 */
	public function optionalWhitespace() {
		return $this->cache[__METHOD__]
			??= new WhitespaceMatcher( [ 'significant' => false ] );
	}

	/**
	 * Matcher for required whitespace
	 * @return Matcher
	 */
	public function significantWhitespace() {
		return $this->cache[__METHOD__]
			??= new WhitespaceMatcher( [ 'significant' => true ] );
	}

	/**
	 * Matcher for a comma
	 * @return Matcher
	 */
	public function comma() {
		return $this->cache[__METHOD__]
			??= new TokenMatcher( Token::T_COMMA );
	}

	/**
	 * Matcher for an arbitrary identifier
	 * @return Matcher
	 */
	public function ident() {
		return $this->cache[__METHOD__]
			??= new TokenMatcher( Token::T_IDENT );
	}

	/**
	 * Matcher for a <custom-ident>
	 *
	 * Note this doesn't implement the semantic restriction about assigning
	 * meaning to various idents in a complex value, as CSS Sanitizer doesn't
	 * deal with semantics on that level.
	 *
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#custom-idents
	 * @param string[] $exclude Additional values to exclude, all-lowercase.
	 * @return Matcher
	 */
	public function customIdent( array $exclude = [] ) {
		$exclude = array_merge( [
			// https://www.w3.org/TR/2024/WD-css-values-4-20240312/#common-keywords
			'initial', 'inherit', 'unset', 'default',
			// https://www.w3.org/TR/2022/CR-css-cascade-4-20220113/#all-shorthand
			'revert'
		], $exclude );
		return new TokenMatcher( Token::T_IDENT, static function ( Token $t ) use ( $exclude ) {
			return !in_array( strtolower( $t->value() ), $exclude, true );
		} );
	}

	/**
	 * Matcher for a string
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#strings
	 * @warning If the string will be used as a URL, use self::urlstring() instead.
	 * @return Matcher
	 */
	public function string() {
		return $this->cache[__METHOD__]
			??= new TokenMatcher( Token::T_STRING );
	}

	/**
	 * Matcher for a string containing a URL
	 * @param string $type Type of resource referenced, e.g. "image" or "audio".
	 *  Not used here, but might be used by a subclass to validate the URL more strictly.
	 * @return Matcher
	 */
	public function urlstring( $type ) {
		return $this->string();
	}

	/**
	 * Matcher for a URL
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#urls
	 * @param string $type Type of resource referenced, e.g. "image" or "audio".
	 *  Not used here, but might be used by a subclass to validate the URL more strictly.
	 * @return Matcher
	 */
	public function url( $type ) {
		return $this->cache[__METHOD__]
			??= new UrlMatcher();
	}

	/**
	 * CSS-wide value keywords
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#common-keywords
	 * @return Matcher
	 */
	public function cssWideKeywords() {
		return $this->cache[__METHOD__]
			??= new KeywordMatcher( [
				// https://www.w3.org/TR/2024/WD-css-values-4-20240312/#common-keywords
				'initial', 'inherit', 'unset',
				// added by https://www.w3.org/TR/2022/CR-css-cascade-4-20220113/#all-shorthand
				'revert'
			] );
	}

	/**
	 * Matcher for a calculation <calc-sum>
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#calc-syntax
	 * @return Matcher
	 */
	protected function calcSum() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$ows = $this->optionalWhitespace();
			$ws = $this->significantWhitespace();

			// Definitions are recursive. This will be used by reference and later
			// will be replaced.
			$calcValue = new NothingMatcher();

			$calcProduct = new Juxtaposition( [
				&$calcValue,
				Quantifier::star(
					new Juxtaposition( [ $ows, new DelimMatcher( [ '*', '/' ] ), $ows, &$calcValue ] )
				),
			] );

			$calcSum = new Juxtaposition( [
				$ows,
				$calcProduct,
				Quantifier::star( new Juxtaposition( [
					$ws, new DelimMatcher( [ '+', '-' ] ), $ws, $calcProduct
				] ) ),
				$ows,
			] );
			// Save it to the cache before it is fully resolved, so that we can call
			// number() etc. This allows things like calc( sin( 1 ) ) since sin() is
			// a math function on the same level as calc() itself.
			$this->cache[__METHOD__] = $calcSum;

			$calcKeyword = new KeywordMatcher( [ 'e', 'pi', 'infinity', '-infinity', 'NaN' ] );
			// calc() forces values to be numeric so it is safe to allow custom props here
			$customProp = new FunctionMatcher( 'var', new Juxtaposition( [
				new CustomPropertyMatcher(),
				Quantifier::optional( $calcSum ),
			], true ) );

			// Complete the recursive rule <calc-value>
			$calcValue = new Alternative( [
				$this->number(),
				$this->dimension(),
				$this->percentage(),
				$calcKeyword,
				$customProp,
				new BlockMatcher( Token::T_LEFT_PAREN, $calcSum )
			] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Create a function which returns true if the name passed to it is a
	 * function with the specified number or type of arguments.
	 *
	 * @param int|string $argType
	 * @return Closure
	 */
	private function makeFuncNameChecker( $argType ) {
		$funcArgs = [
			'calc' => 1,
			'min' => '#',
			'max' => '#',
			'clamp' => 'clamp',
			'round' => 'round',
			'mod' => 2,
			'rem' => 2,
			'sin' => 1,
			'cos' => 1,
			'tan' => 1,
			'asin' => 1,
			'acos' => 1,
			'atan' => 1,
			'atan2' => 2,
			'pow' => 2,
			'sqrt' => 1,
			'hypot' => '#',
			'log' => 'log',
			'exp' => 1,
			'abs' => 1,
			'sign' => 1,
		];
		return static function ( $name ) use ( $funcArgs, $argType ) {
			// phpcs:ignore Generic.ControlStructures.DisallowYodaConditions
			return $funcArgs[ strtolower( $name ) ] ?? null === $argType;
		};
	}

	/**
	 * Match either a math function such as calc() or a specified value type.
	 *
	 * Note: CSS Values Level 4 is much more permissive than Level 3. Checking
	 * of types such as length is deferred until runtime, and non-integer return
	 * values in an integer context are rounded instead of being rejected at
	 * parse time.
	 *
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#calc-syntax
	 * @return Matcher
	 */
	protected function mathFunction( Matcher $typeMatcher ) {
		$calcSum = $this->calcSum();

		$matchers = [ $typeMatcher ];

		// Functions with one argument
		$matchers[] = new FunctionMatcher(
			$this->makeFuncNameChecker( 1 ),
			$calcSum
		);

		// Functions with two arguments
		$matchers[] = new FunctionMatcher(
			$this->makeFuncNameChecker( 2 ),
			new Juxtaposition( [ $calcSum, $calcSum ], true )
		);

		// Functions with N arguments separated by commas
		$matchers[] = new FunctionMatcher(
			$this->makeFuncNameChecker( '#' ),
			Quantifier::hash( $calcSum )
		);

		// clamp
		$clampArg = new Alternative( [
			$calcSum,
			new KeywordMatcher( 'none' )
		] );
		$matchers[] = new FunctionMatcher(
			$this->makeFuncNameChecker( 'clamp' ),
			new Juxtaposition( [ $clampArg, $calcSum, $clampArg ], true )
		);

		// round
		$roundingStrategy = new KeywordMatcher( [ 'nearest', 'up', 'down', 'to-zero' ] );
		$matchers[] = new FunctionMatcher(
			$this->makeFuncNameChecker( 'round' ),
			new Juxtaposition(
				[
					Quantifier::optional( $roundingStrategy ),
					$calcSum,
					Quantifier::optional( $calcSum )
				],
				true
			)
		);

		// log
		$matchers[] = new FunctionMatcher(
			$this->makeFuncNameChecker( 'log' ),
			new Juxtaposition(
				[
					$calcSum,
					Quantifier::optional( $calcSum )
				],
				true
			)
		);
		return new Alternative( $matchers );
	}

	/**
	 * Matcher for an integer value, without math functions
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#integers
	 * @return Matcher
	 */
	protected function rawInteger() {
		return $this->cache[__METHOD__]
			??= new TokenMatcher( Token::T_NUMBER, static function ( Token $t ) {
				// The spec says it must match /^[+-]\d+$/, but the tokenizer
				// should have marked any other number token as a 'number'
				// anyway so let's not bother checking.
				return $t->typeFlag() === 'integer';
			} );
	}

	/**
	 * @return TokenMatcher
	 */
	public function colorHex(): TokenMatcher {
		return $this->cache[__METHOD__]
			??= new TokenMatcher( Token::T_HASH, static function ( Token $t ) {
				return preg_match( '/^([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $t->value() );
			} );
	}

	/**
	 * Matcher for an integer value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#integers
	 * @return Matcher
	 */
	public function integer() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction( $this->rawInteger() );
	}

	/**
	 * Matcher for a real number, without math functions
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#numbers
	 * @return Matcher
	 */
	public function rawNumber() {
		return $this->cache[__METHOD__]
			??= new TokenMatcher( Token::T_NUMBER );
	}

	/**
	 * Matcher for a real number
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#numbers
	 * @return Matcher
	 */
	public function number() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction( $this->rawNumber() );
	}

	/**
	 * Ratio values
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#ratios
	 * @return Matcher
	 */
	public function ratio() {
		return $this->cache[__METHOD__]
			// <ratio> = <number [0,∞]> [ / <number [0,∞]> ]?
			??= new Alternative( [
				$this->rawNumber(),
				new Juxtaposition( [
					$this->rawNumber(),
					$this->optionalWhitespace(),
					new DelimMatcher( [ '/' ] ),
					$this->optionalWhitespace(),
					$this->rawNumber(),
				] ),
			] );
	}

	/**
	 * Matcher for a percentage value, without math functions
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#percentages
	 * @return Matcher
	 */
	public function rawPercentage() {
		return $this->cache[__METHOD__]
			??= new TokenMatcher( Token::T_PERCENTAGE );
	}

	/**
	 * Matcher for a percentage value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#percentages
	 * @return Matcher
	 */
	public function percentage() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction( $this->rawPercentage() );
	}

	/**
	 * Matcher for a length-percentage value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#typedef-length-percentage
	 * @return Matcher
	 */
	public function lengthPercentage() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction(
				new Alternative( [ $this->rawLength(), $this->rawPercentage() ] )
			);
	}

	/**
	 * Matcher for a frequency-percentage value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#typedef-frequency-percentage
	 * @return Matcher
	 */
	public function frequencyPercentage() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction(
				new Alternative( [ $this->rawFrequency(), $this->rawPercentage() ] )
			);
	}

	/**
	 * Matcher for an angle-percentage value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#typedef-angle-percentage
	 * @return Matcher
	 */
	public function anglePercentage() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction(
				new Alternative( [ $this->rawAngle(), $this->rawPercentage() ] )
			);
	}

	/**
	 * Matcher for a time-percentage value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#typedef-time-percentage
	 * @return Matcher
	 */
	public function timePercentage() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction(
				new Alternative( [ $this->rawTime(), $this->rawPercentage() ] )
			);
	}

	/**
	 * A convenience method for matching <number>|<percentage>.
	 *
	 * In CSS Values 3 there was a <number-percentage> production, but this was
	 * removed in CSS Values 4 with the note "<number> and <percentage> can't
	 * be combined in calc()". Things that previously used <number-percentage>
	 * were updated to use <number>|<percentage>. So, following Values 4, we
	 * will return a matcher for <number>|<percentage> here.
	 *
	 * Note that calc(1 + 50%) is still allowed at parse time since <calc-value>
	 * can now be either <number> or <percentage>.
	 *
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#percentages
	 * @return Matcher
	 */
	public function numberPercentage() {
		return $this->cache[__METHOD__]
			??= new Alternative( [ $this->number(), $this->percentage() ] );
	}

	/**
	 * Matcher for a dimension value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#dimensions
	 * @return Matcher
	 */
	public function dimension() {
		return $this->cache[__METHOD__]
			??= new TokenMatcher( Token::T_DIMENSION );
	}

	/**
	 * Matches the number 0
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#zero-value
	 * @return Matcher
	 */
	public function zero() {
		return $this->cache[__METHOD__]
			??= new TokenMatcher( Token::T_NUMBER, static function ( Token $t ) {
				return $t->value() === 0 || $t->value() === 0.0;
			} );
	}

	/**
	 * Matcher for a length value, without math functions
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#lengths
	 * @return Matcher
	 */
	protected function rawLength() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$unitsRe = '/^(' . implode( '|', self::$lengthUnits ) . ')$/i';

			$this->cache[__METHOD__] = new Alternative( [
				$this->zero(),
				new TokenMatcher( Token::T_DIMENSION, static function ( Token $t ) use ( $unitsRe ) {
					return preg_match( $unitsRe, $t->unit() );
				} ),
			] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Matcher for a length value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#lengths
	 * @return Matcher
	 */
	public function length() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction( $this->rawLength() );
	}

	/**
	 * Matcher for an angle value, without math functions
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#angles
	 * @return Matcher
	 */
	protected function rawAngle() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$unitsRe = '/^(' . implode( '|', self::$angleUnits ) . ')$/i';

			$this->cache[__METHOD__] = new TokenMatcher( Token::T_DIMENSION,
				static function ( Token $t ) use ( $unitsRe ) {
					return preg_match( $unitsRe, $t->unit() );
				}
			);
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Matcher for an angle value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#angles
	 * @return Matcher
	 */
	public function angle() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction( $this->rawAngle() );
	}

	/**
	 * Matcher for a duration (time) value, without math functions
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#time
	 * @return Matcher
	 */
	protected function rawTime() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$unitsRe = '/^(' . implode( '|', self::$timeUnits ) . ')$/i';

			$this->cache[__METHOD__] = new TokenMatcher( Token::T_DIMENSION,
				static function ( Token $t ) use ( $unitsRe ) {
					return preg_match( $unitsRe, $t->unit() );
				}
			);
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Matcher for a duration (time) value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#time
	 * @return Matcher
	 */
	public function time() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction( $this->rawTime() );
	}

	/**
	 * Matcher for a frequency value, without math functions
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#frequency
	 * @return Matcher
	 */
	protected function rawFrequency() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$unitsRe = '/^(' . implode( '|', self::$frequencyUnits ) . ')$/i';

			$this->cache[__METHOD__] = new TokenMatcher( Token::T_DIMENSION,
				static function ( Token $t ) use ( $unitsRe ) {
					return preg_match( $unitsRe, $t->unit() );
				}
			);
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Matcher for a frequency value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#frequency
	 * @return Matcher
	 */
	public function frequency() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction( $this->rawFrequency() );
	}

	/**
	 * Matcher for a raw resolution value, without math functions
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#resolution
	 * @return Matcher
	 */
	protected function rawResolution() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$unitsRe = '/^(' . implode( '|', self::$resolutionUnits ) . ')$/i';
			$this->cache[__METHOD__] = new TokenMatcher( Token::T_DIMENSION,
				static function ( Token $t ) use ( $unitsRe ) {
					return preg_match( $unitsRe, $t->unit() );
				}
			);
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Matcher for a resolution value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#resolution
	 * @return Matcher
	 */
	public function resolution() {
		return $this->cache[__METHOD__]
			??= $this->mathFunction( $this->rawResolution() );
	}

	/**
	 * Matcher for either a given value type or a var() with the value type
	 * as fallback.
	 *
	 * @param Matcher $fallback
	 * @return Matcher
	 */
	private function rawOrCustomProp( Matcher $fallback ): Matcher {
		$withCustomProp = new FunctionMatcher( 'var', new Juxtaposition( [
			new CustomPropertyMatcher(),
			Quantifier::optional( $fallback ),
		], true ) );
		return new Alternative( [ $fallback, $withCustomProp ] );
	}

	/**
	 * Matchers for color functions
	 * @return Matcher[]
	 */
	protected function colorFuncs() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			// Color functions require arguments to be numeric so allowing var() is safe
			$n = $this->rawOrCustomProp( $this->number() );
			$p = $this->rawOrCustomProp( $this->percentage() );
			$hue = new Alternative( [ $this->angle(), $n ] );

			$none = new KeywordMatcher( 'none' );
			$nPNone = new Alternative( [ $this->rawOrCustomProp( $this->numberPercentage() ), $none ] );
			$hueNone = new Alternative( [ $hue, $none ] );

			$colorSpaceParams = new Alternative( [
				new Juxtaposition( [
					new KeywordMatcher( [
						'srgb', 'srgb-linear', 'display-p3', 'a98-rgb',
						'prophoto-rgb', 'rec2020'
					] ),
					Quantifier::count( $nPNone, 3, 3 ),
				] ),
				new Juxtaposition( [
					new KeywordMatcher( [ 'xyz', 'xyz-d50', 'xyz-d65' ] ),
					Quantifier::count( $nPNone, 3, 3 ),
				] ),
			] );

			$optionalAlpha = Quantifier::optional( new Juxtaposition(
				[ new DelimMatcher( '/' ), new Alternative( [ $nPNone, $none ] ) ]
			) );
			$optionalLegacyAlpha = Quantifier::optional( $nPNone );

			$rgb = new Alternative( [
				new Juxtaposition( [ Quantifier::hash( $p, 3, 3 ), $optionalLegacyAlpha ], true ),
				new Juxtaposition( [ Quantifier::hash( $n, 3, 3 ), $optionalLegacyAlpha ], true ),
				new Juxtaposition( [ $nPNone, $nPNone, $nPNone, $optionalAlpha ] ),
			] );
			$hsl = new Alternative( [
				new Juxtaposition( [ $hue, $p, $p, $optionalLegacyAlpha ], true ),
				new Juxtaposition( [ $hueNone, $nPNone, $nPNone, $optionalAlpha ] ),
			] );
			$hwb = new Juxtaposition( [ $hueNone, $nPNone, $nPNone, $optionalAlpha ] );
			$lab = new Juxtaposition( [ $nPNone, $nPNone, $nPNone, $optionalAlpha ] );
			$lch = new Juxtaposition( [ $nPNone, $nPNone, $hueNone, $optionalAlpha ] );
			$color = new Juxtaposition( [ $colorSpaceParams, $optionalAlpha ] );

			$this->cache[__METHOD__] = [
				new FunctionMatcher( 'rgb', $rgb ),
				new FunctionMatcher( 'rgba', $rgb ),
				new FunctionMatcher( 'hsl', $hsl ),
				new FunctionMatcher( 'hsla', $hsl ),
				new FunctionMatcher( 'hwb', $hwb ),
				new FunctionMatcher( 'lab', $lab ),
				new FunctionMatcher( 'lch', $lch ),
				new FunctionMatcher( 'oklab', $lab ),
				new FunctionMatcher( 'oklch', $lch ),
				new FunctionMatcher( 'color', $color )
			];
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Matcher for a color value, *not* including a custom property reference.
	 *
	 * Because custom properties can lead to unexpected behavior (generally
	 * a bad thing for security) when concatenated together, this matcher
	 * should be used for CSS rules which allow value concatenation.
	 * For example, `border-color` allows up to 4 `var(...)` expressions to
	 * potentially be concatenated.
	 *
	 * @see https://www.w3.org/TR/2022/CR-css-variables-1-20220616/#custom-property
	 * @return Matcher
	 */
	public function safeColor() {
		return $this->cache[__METHOD__]
			??= new Alternative( array_merge( [
				$this->colorWords(),
				$this->colorHex(),
			], $this->colorFuncs() ) );
	}

	/**
	 * Matcher for a color value, including a possible custom property
	 * reference and light-dark color function.
	 *
	 * Follows:
	 * * https://www.w3.org/TR/2025/CRD-css-color-4-20250424/
	 * * https://www.w3.org/TR/css-variables-1/
	 * * https://www.w3.org/TR/2024/WD-css-color-5-20240229/#funcdef-light-dark
	 *
	 * @return Matcher
	 */
	public function color() {
		return $this->cache[__METHOD__]
			??= new Alternative( [
				$this->safeColor(),
				new FunctionMatcher( 'var', new Juxtaposition( [
					new CustomPropertyMatcher(),
					Quantifier::optional( $this->safeColor() ),
				], true ) ),
				new FunctionMatcher( 'light-dark', new Juxtaposition( [
					$this->safeColor(),
					$this->safeColor(),
				], true ) ),
			] );
	}

	/**
	 * Matcher for an image value
	 * @see https://www.w3.org/TR/2023/CRD-css-images-3-20231218/#image-values
	 * @return Matcher
	 */
	public function image() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			// https://www.w3.org/TR/2023/CRD-css-images-3-20231218/#gradients
			$c = $this->comma();
			$colorStop = new Juxtaposition( [
				$this->color(),
				Quantifier::optional( $this->lengthPercentage() ),
			] );
			$colorStopList = new Juxtaposition( [
				$colorStop,
				Quantifier::hash( new Juxtaposition( [
					Quantifier::optional( $this->lengthPercentage() ),
					$colorStop
				], true ) ),
			], true );
			$atPosition = new Juxtaposition( [ new KeywordMatcher( 'at' ), $this->position() ] );

			$linearGradient = new Juxtaposition( [
				Quantifier::optional( new Juxtaposition( [
					new Alternative( [
						new Alternative( [
							$this->zero(),
							$this->angle(),
						] ),
						new Juxtaposition( [ new KeywordMatcher( 'to' ), UnorderedGroup::someOf( [
							new KeywordMatcher( [ 'left', 'right' ] ),
							new KeywordMatcher( [ 'top', 'bottom' ] ),
						] ) ] )
					] ),
					$c
				] ) ),
				$colorStopList,
			] );
			$radialGradient = new Juxtaposition( [
				Quantifier::optional( new Juxtaposition( [
					new Alternative( [
						new Juxtaposition( [
							new Alternative( [
								UnorderedGroup::someOf( [ new KeywordMatcher( 'circle' ), $this->length() ] ),
								UnorderedGroup::someOf( [
									new KeywordMatcher( 'ellipse' ),
									Quantifier::count( $this->lengthPercentage(), 2, 2 )
								] ),
								UnorderedGroup::someOf( [
									new KeywordMatcher( [ 'circle', 'ellipse' ] ),
									new KeywordMatcher( [
										'closest-corner', 'closest-side', 'farthest-corner', 'farthest-side',
									] ),
								] ),
							] ),
							Quantifier::optional( $atPosition ),
						] ),
						$atPosition
					] ),
					$c
				] ) ),
				$colorStopList,
			] );

			// Putting it all together
			$this->cache[__METHOD__] = new Alternative( [
				$this->url( 'image' ),
				new FunctionMatcher( 'linear-gradient', $linearGradient ),
				new FunctionMatcher( 'radial-gradient', $radialGradient ),
				new FunctionMatcher( 'repeating-linear-gradient', $linearGradient ),
				new FunctionMatcher( 'repeating-radial-gradient', $radialGradient ),
			] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Matcher for a position value
	 * @see https://www.w3.org/TR/2024/WD-css-values-4-20240312/#typedef-position
	 * @return Matcher
	 */
	public function position() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$lp = $this->lengthPercentage();

			$this->cache[__METHOD__] = new Alternative( [
				new KeywordMatcher( [ 'left', 'center', 'right', 'top', 'bottom' ] ),
				$lp,
				UnorderedGroup::allOf( [
					new KeywordMatcher( [ 'left', 'center', 'right' ] ),
					new KeywordMatcher( [ 'top', 'center', 'bottom' ] )
				] ),
				new Juxtaposition( [
					new Alternative( [
						new KeywordMatcher( [ 'left', 'center', 'right' ] ),
						$lp
					] ),
					new Alternative( [
						new KeywordMatcher( [ 'top', 'center', 'bottom' ] ),
						$lp
					] ),
				] ),
				UnorderedGroup::allOf( [
					new Juxtaposition( [
						new KeywordMatcher( [ 'left', 'right' ] ),
						$lp
					] ),
					new Juxtaposition( [
						new KeywordMatcher( [ 'top', 'bottom' ] ),
						$lp
					] ),
				] )
			] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Matcher for a bg-position value
	 * @see https://www.w3.org/TR/2024/CRD-css-backgrounds-3-20240311/#typedef-bg-position
	 * @return Matcher
	 */
	public function bgPosition() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$lp = $this->lengthPercentage();
			$olp = Quantifier::optional( $lp );
			$center = new KeywordMatcher( 'center' );
			$leftRight = new KeywordMatcher( [ 'left', 'right' ] );
			$topBottom = new KeywordMatcher( [ 'top', 'bottom' ] );

			$this->cache[__METHOD__] = new Alternative( [
				new Alternative( [ $center, $leftRight, $topBottom, $lp ] ),
				new Juxtaposition( [
					new Alternative( [ $center, $leftRight, $lp ] ),
					new Alternative( [ $center, $topBottom, $lp ] ),
				] ),
				UnorderedGroup::allOf( [
					new Alternative( [ $center, new Juxtaposition( [ $leftRight, $olp ] ) ] ),
					new Alternative( [ $center, new Juxtaposition( [ $topBottom, $olp ] ) ] ),
				] ),
			] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Matcher for a CSS media query
	 * @see https://www.w3.org/TR/2017/CR-mediaqueries-4-20170905/#mq-syntax
	 * Level 5 accessibility queries are also supported
	 * @see https://drafts.csswg.org/mediaqueries-5/#mf-user-preferences
	 * @param bool $strict Only allow defined query types
	 * @return Matcher
	 */
	public function cssMediaQuery( $strict = true ) {
		$key = __METHOD__ . ':' . ( $strict ? 'strict' : 'unstrict' );
		if ( !isset( $this->cache[$key] ) ) {
			if ( $strict ) {
				$generalEnclosed = new NothingMatcher();

				$mediaType = new KeywordMatcher( [
					'all', 'print', 'screen', 'speech',
					// deprecated
					'tty', 'tv', 'projection', 'handheld', 'braille', 'embossed', 'aural'
				] );

				$rangeFeatures = [
					'width', 'height', 'aspect-ratio', 'resolution', 'color', 'color-index', 'monochrome',
					// deprecated
					'device-width', 'device-height', 'device-aspect-ratio'
				];
				$discreteFeatures = [
					'orientation', 'scan', 'grid', 'update', 'overflow-block', 'overflow-inline', 'color-gamut',
					'pointer', 'hover', 'any-pointer', 'any-hover', 'scripting', 'prefers-color-scheme',
					'prefers-reduced-motion', 'prefers-reduced-transparency',
					'prefers-contrast', 'forced-colors'
				];
				$mfName = new KeywordMatcher( array_merge(
					$rangeFeatures,
					array_map( static function ( $f ) {
						return "min-$f";
					}, $rangeFeatures ),
					array_map( static function ( $f ) {
						return "max-$f";
					}, $rangeFeatures ),
					$discreteFeatures
				) );
			} else {
				$anythingPlus = new AnythingMatcher( [ 'quantifier' => '+' ] );
				$generalEnclosed = new Alternative( [
					new FunctionMatcher( null, $anythingPlus ),
					new BlockMatcher( Token::T_LEFT_PAREN,
						new Juxtaposition( [ $this->ident(), $anythingPlus ] )
					),
				] );
				$mediaType = $this->ident();
				$mfName = $this->ident();
			}

			$posInt = $this->mathFunction(
				new TokenMatcher( Token::T_NUMBER, static function ( Token $t ) {
					return $t->typeFlag() === 'integer' && preg_match( '/^\+?\d+$/', $t->representation() );
				} )
			);
			$eq = new DelimMatcher( '=' );
			$oeq = Quantifier::optional( new Juxtaposition( [ new NoWhitespace, $eq ] ) );
			$ltgteq = Quantifier::optional( new Alternative( [
				$eq,
				new Juxtaposition( [ new DelimMatcher( [ '<', '>' ] ), $oeq ] ),
			] ) );
			$lteq = new Juxtaposition( [ new DelimMatcher( '<' ), $oeq ] );
			$gteq = new Juxtaposition( [ new DelimMatcher( '>' ), $oeq ] );
			$mfValue = new Alternative( [
				$this->number(),
				$this->dimension(),
				$this->ident(),
				new KeywordMatcher( [ 'light', 'dark' ] ),
				new Juxtaposition( [ $posInt, new DelimMatcher( '/' ), $posInt ] ),
			] );

			// temporary
			$mediaInParens = new NothingMatcher();
			$mediaNot = new Juxtaposition( [ new KeywordMatcher( 'not' ), &$mediaInParens ] );
			$mediaAnd = new Juxtaposition( [ new KeywordMatcher( 'and' ), &$mediaInParens ] );
			$mediaOr = new Juxtaposition( [ new KeywordMatcher( 'or' ), &$mediaInParens ] );
			$mediaCondition = new Alternative( [
				$mediaNot,
				new Juxtaposition( [
					&$mediaInParens,
					new Alternative( [
						Quantifier::star( $mediaAnd ),
						Quantifier::star( $mediaOr ),
					] )
				] ),
			] );
			$mediaConditionWithoutOr = new Alternative( [
				$mediaNot,
				new Juxtaposition( [ &$mediaInParens, Quantifier::star( $mediaAnd ) ] ),
			] );
			$mediaFeature = new BlockMatcher( Token::T_LEFT_PAREN, new Alternative( [
				// <mf-plain>
				new Juxtaposition( [ $mfName, new TokenMatcher( Token::T_COLON ), $mfValue ] ),
				// <mf-boolean>
				$mfName,
				// <mf-range>, 1st alternative
				new Juxtaposition( [ $mfName, $ltgteq, $mfValue ] ),
				// <mf-range>, 2nd alternative
				new Juxtaposition( [ $mfValue, $ltgteq, $mfName ] ),
				// <mf-range>, 3rd alt
				new Juxtaposition( [ $mfValue, $lteq, $mfName, $lteq, $mfValue ] ),
				// <mf-range>, 4th alt
				new Juxtaposition( [ $mfValue, $gteq, $mfName, $gteq, $mfValue ] ),
			] ) );
			$mediaInParens = new Alternative( [
				new BlockMatcher( Token::T_LEFT_PAREN, $mediaCondition ),
				$mediaFeature,
				$generalEnclosed,
			] );

			$this->cache[$key] = new Alternative( [
				$mediaCondition,
				new Juxtaposition( [
					Quantifier::optional( new KeywordMatcher( [ 'not', 'only' ] ) ),
					$mediaType,
					Quantifier::optional( new Juxtaposition( [
						new KeywordMatcher( 'and' ),
						$mediaConditionWithoutOr,
					] ) )
				] )
			] );
		}

		return $this->cache[$key];
	}

	/**
	 * Matcher for a CSS media query list
	 * @see https://www.w3.org/TR/2017/CR-mediaqueries-4-20170905/#mq-syntax
	 * @param bool $strict Only allow defined query types
	 * @return Matcher
	 */
	public function cssMediaQueryList( $strict = true ) {
		$key = __METHOD__ . ':' . ( $strict ? 'strict' : 'unstrict' );
		return $this->cache[$key]
			??= Quantifier::hash( $this->cssMediaQuery( $strict ), 0, INF );
	}

	/**
	 * Matcher for a "supports-condition"
	 * @see https://www.w3.org/TR/2013/CR-css3-conditional-20130404/#supports_condition
	 * @param PropertySanitizer|null $declarationSanitizer Check declarations against this Sanitizer
	 * @param bool $strict Only accept defined syntax. Default true.
	 * @return Matcher
	 */
	public function cssSupportsCondition(
		?PropertySanitizer $declarationSanitizer = null, $strict = true
	) {
		$ws = $this->significantWhitespace();
		$anythingPlus = new AnythingMatcher( [ 'quantifier' => '+' ] );

		if ( $strict ) {
			$generalEnclosed = new NothingMatcher();
		} else {
			$generalEnclosed = new Alternative( [
				new FunctionMatcher( null, $anythingPlus ),
				new BlockMatcher( Token::T_LEFT_PAREN, new Juxtaposition( [ $this->ident(), $anythingPlus ] ) ),
			] );
		}

		// temp
		$supportsConditionBlock = new NothingMatcher();
		$supportsConditionInParens = new Alternative( [
			&$supportsConditionBlock,
			new BlockMatcher( Token::T_LEFT_PAREN, $this->cssDeclaration( $declarationSanitizer ) ),
			$generalEnclosed,
		] );
		$supportsCondition = new Alternative( [
			new Juxtaposition( [ new KeywordMatcher( 'not' ), $ws, $supportsConditionInParens ] ),
			new Juxtaposition( [ $supportsConditionInParens, Quantifier::plus( new Juxtaposition( [
				$ws, new KeywordMatcher( 'and' ), $ws, $supportsConditionInParens
			] ) ) ] ),
			new Juxtaposition( [ $supportsConditionInParens, Quantifier::plus( new Juxtaposition( [
				$ws, new KeywordMatcher( 'or' ), $ws, $supportsConditionInParens
			] ) ) ] ),
			$supportsConditionInParens,
		] );
		$supportsConditionBlock = new BlockMatcher( Token::T_LEFT_PAREN, $supportsCondition );

		return $supportsCondition;
	}

	/**
	 * Matcher for a declaration
	 * @param PropertySanitizer|null $declarationSanitizer Check declarations against this Sanitizer
	 * @return Matcher
	 */
	public function cssDeclaration( ?PropertySanitizer $declarationSanitizer = null ) {
		$anythingPlus = new AnythingMatcher( [ 'quantifier' => '+' ] );

		return new CheckedMatcher(
			$anythingPlus,
			static function ( ComponentValueList $list, GrammarMatch $match, array $options )
				use ( $declarationSanitizer )
			{
				$cvlist = new ComponentValueList( $match->getValues() );
				$parser = Parser::newFromTokens( $cvlist->toTokenArray() );
				$declaration = $parser->parseDeclaration();
				if ( !$declaration || $parser->getParseErrors() ) {
					return false;
				}
				if ( !$declarationSanitizer ) {
					return true;
				}
				$reset = $declarationSanitizer->stashSanitizationErrors();
				$ret = $declarationSanitizer->sanitize( $declaration );
				$errors = $declarationSanitizer->getSanitizationErrors();
				unset( $reset );
				return $ret === $declaration && !$errors;
			}
		);
	}

	/**
	 * Matcher for single easing functions from CSS Easing Functions Level 1
	 * @see https://www.w3.org/TR/2023/CRD-css-easing-1-20230213/#typedef-easing-function
	 * @return Matcher
	 */
	public function cssSingleEasingFunction() {
		return $this->cache[__METHOD__]
			??= new Alternative( [
				new KeywordMatcher( [
					'ease', 'linear', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end'
				] ),
				new FunctionMatcher( 'steps', new Juxtaposition( [
					$this->integer(),
					Quantifier::optional( new KeywordMatcher( [
						'jump-start', 'jump-end', 'jump-none', 'jump-both', 'start', 'end'
					] ) ),
				], true ) ),
				new FunctionMatcher( 'cubic-bezier', Quantifier::hash( $this->number(), 4, 4 ) ),
			] );
	}

	/**
	 * Matcher for <counter-style>
	 * @see https://www.w3.org/TR/2021/CR-css-counter-styles-3-20210727/#typedef-counter-style
	 * @return Matcher
	 */
	public function counterStyle() {
		return $this->cache[__METHOD__] ??= new Alternative( [
			$this->customIdent( [ 'none' ] ),
			new FunctionMatcher(
				'symbols',
				// "If the system is alphabetic or numeric, there must be at least two
				// <string>s or <image>s, or else the function is invalid."
				// Implement that by modifying the grammar
				new Alternative( [
					new Juxtaposition( [
						new KeywordMatcher( [ 'numeric', 'alphabetic' ] ),
						Quantifier::count(
							new Alternative( [
								$this->string(),
								$this->image()
							] ),
							2, INF
						)
					] ),
					new Juxtaposition( [
						Quantifier::optional( new KeywordMatcher( [
							'cyclic', 'symbolic', 'fixed'
						] ) ),
						Quantifier::plus(
							new Alternative( [
								$this->string(),
								$this->image()
							] )
						)
					] )
				] )
			)
		] );
	}

	/***************************************************************************/
	// region   CSS Selectors Level 3
	/**
	 * @name   CSS Selectors Level 3
	 * https://www.w3.org/TR/2018/REC-selectors-3-20181106/#w3cselgrammar
	 */

	/**
	 * List of selectors (selectors_group)
	 *
	 *     selector [ COMMA S* selector ]*
	 *
	 * Capturing is set up for the `selector`s.
	 *
	 * @return Matcher
	 */
	public function cssSelectorList() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			// Technically the spec doesn't allow whitespace before the comma,
			// but I'd guess every browser does. So just use Quantifier::hash.
			$selector = $this->cssSelector()->capture( 'selector' );
			$this->cache[__METHOD__] = Quantifier::hash( $selector );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * A single selector (selector)
	 *
	 *     simple_selector_sequence [ combinator simple_selector_sequence ]*
	 *
	 * Capturing is set up for the `simple_selector_sequence`s (as 'simple') and `combinator`.
	 *
	 * @return Matcher
	 */
	public function cssSelector() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$simple = $this->cssSimpleSelectorSeq()->capture( 'simple' );
			$this->cache[__METHOD__] = new Juxtaposition( [
				$simple,
				Quantifier::star( new Juxtaposition( [
					$this->cssCombinator()->capture( 'combinator' ),
					$simple,
				] ) )
			] );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * A CSS combinator (combinator)
	 *
	 *     PLUS S* | GREATER S* | TILDE S* | S+
	 *
	 * (combinators can be surrounded by whitespace)
	 *
	 * @return Matcher
	 */
	public function cssCombinator() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$this->cache[__METHOD__] = new Alternative( [
				new Juxtaposition( [
					$this->optionalWhitespace(),
					new DelimMatcher( [ '+', '>', '~' ] ),
					$this->optionalWhitespace(),
				] ),
				$this->significantWhitespace(),
			] );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * A simple selector sequence (simple_selector_sequence)
	 *
	 *     [ type_selector | universal ]
	 *     [ HASH | class | attrib | pseudo | negation ]*
	 *     | [ HASH | class | attrib | pseudo | negation ]+
	 *
	 * The following captures are set:
	 *  - element: [ type_selector | universal ]
	 *  - id: HASH
	 *  - class: class
	 *  - attrib: attrib
	 *  - pseudo: pseudo
	 *  - negation: negation
	 *
	 * @return Matcher
	 */
	public function cssSimpleSelectorSeq() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$hashEtc = new Alternative( [
				$this->cssID()->capture( 'id' ),
				$this->cssClass()->capture( 'class' ),
				$this->cssAttrib()->capture( 'attrib' ),
				$this->cssPseudo()->capture( 'pseudo' ),
				$this->cssNegation()->capture( 'negation' ),
			] );

			$this->cache[__METHOD__] = new Alternative( [
				new Juxtaposition( [
					Alternative::create( [
						$this->cssTypeSelector(),
						$this->cssUniversal(),
					] )->capture( 'element' ),
					Quantifier::star( $hashEtc )
				] ),
				Quantifier::plus( $hashEtc )
			] );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * A type selector, i.e. a tag name (type_selector)
	 *
	 *     [ namespace_prefix ] ? element_name
	 *
	 * where element_name is
	 *
	 *     IDENT
	 *
	 * @return Matcher
	 */
	public function cssTypeSelector() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$this->cache[__METHOD__] = new Juxtaposition( [
				$this->cssOptionalNamespacePrefix(),
				new TokenMatcher( Token::T_IDENT )
			] );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * A namespace prefix (namespace_prefix)
	 *
	 *      [ IDENT | '*' ]? '|'
	 *
	 * @return Matcher
	 */
	public function cssNamespacePrefix() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$this->cache[__METHOD__] = new Juxtaposition( [
				Quantifier::optional( new Alternative( [
					$this->ident(),
					new DelimMatcher( [ '*' ] ),
				] ) ),
				new DelimMatcher( [ '|' ] ),
			] );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * An optional namespace prefix
	 *
	 *     [ namespace_prefix ]?
	 *
	 * @return Matcher
	 */
	private function cssOptionalNamespacePrefix() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$this->cache[__METHOD__] = Quantifier::optional( $this->cssNamespacePrefix() );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * The universal selector (universal)
	 *
	 *     [ namespace_prefix ]? '*'
	 *
	 * @return Matcher
	 */
	public function cssUniversal() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$this->cache[__METHOD__] = new Juxtaposition( [
				$this->cssOptionalNamespacePrefix(),
				new DelimMatcher( [ '*' ] )
			] );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * An ID selector
	 *
	 *     HASH
	 *
	 * @return Matcher
	 */
	public function cssID() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$this->cache[__METHOD__] = new TokenMatcher( Token::T_HASH, static function ( Token $t ) {
				return $t->typeFlag() === 'id';
			} );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * A class selector (class)
	 *
	 *     '.' IDENT
	 *
	 * @return Matcher
	 */
	public function cssClass() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$this->cache[__METHOD__] = new Juxtaposition( [
				new DelimMatcher( [ '.' ] ),
				$this->ident()
			] );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * An attribute selector (attrib)
	 *
	 *     '[' S* [ namespace_prefix ]? IDENT S*
	 *         [ [ PREFIXMATCH |
	 *             SUFFIXMATCH |
	 *             SUBSTRINGMATCH |
	 *             '=' |
	 *             INCLUDES |
	 *             DASHMATCH ] S* [ IDENT | STRING ] S*
	 *         ]? ']'
	 *
	 * Captures are set for the attribute, test, and value. Note that these
	 * captures will probably be relative to the contents of the SimpleBlock
	 * that this matcher matches!
	 *
	 * @return Matcher
	 */
	public function cssAttrib() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			// An attribute is going to be parsed by the parser as a
			// SimpleBlock, so that's what we need to look for here.

			$this->cache[__METHOD__] = new BlockMatcher( Token::T_LEFT_BRACKET,
				new Juxtaposition( [
					$this->optionalWhitespace(),
					Juxtaposition::create( [
						$this->cssOptionalNamespacePrefix(),
						$this->ident(),
					] )->capture( 'attribute' ),
					$this->optionalWhitespace(),
					Quantifier::optional( new Juxtaposition( [
						// Sigh. They removed various tokens from CSS Syntax 3, but didn't update the grammar
						// in CSS Selectors 3. Wing it with a hint from CSS Selectors 4's <attr-matcher>
						( new Juxtaposition( [
							Quantifier::optional( new DelimMatcher( [ '^', '$', '*', '~', '|' ] ) ),
							new DelimMatcher( [ '=' ] ),
						] ) )->capture( 'test' ),
						$this->optionalWhitespace(),
						Alternative::create( [
							$this->ident(),
							$this->string(),
						] )->capture( 'value' ),
						$this->optionalWhitespace(),
					] ) ),
				] )
			);
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * A pseudo-class or pseudo-element (pseudo)
	 *
	 *     ':' ':'? [ IDENT | functional_pseudo ]
	 *
	 * Where functional_pseudo is
	 *
	 *     FUNCTION S* expression ')'
	 *
	 * Although this actually only matches the pseudo-selectors defined in the
	 * following sources:
	 * - https://www.w3.org/TR/2018/REC-selectors-3-20181106/#pseudo-classes
	 * - https://www.w3.org/TR/2022/WD-css-pseudo-4-20221230/
	 * - https://www.w3.org/TR/2022/WD-selectors-4-20221111/#the-dir-pseudo
	 *
	 * @return Matcher
	 */
	public function cssPseudo() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$colon = new TokenMatcher( Token::T_COLON );
			$ows = $this->optionalWhitespace();
			$anplusb = new Juxtaposition( [ $ows, $this->cssANplusB(), $ows ] );
			$dirValues = new KeywordMatcher( [ 'ltr', 'rtl' ] );
			$this->cache[__METHOD__] = new Alternative( [
				new Juxtaposition( [
					$colon,
					new Alternative( [
						new KeywordMatcher( [
							'link', 'visited', 'hover', 'active', 'focus', 'target', 'enabled', 'disabled', 'checked',
							'indeterminate', 'root', 'first-child', 'last-child', 'first-of-type',
							'last-of-type', 'only-child', 'only-of-type', 'empty',
							// CSS2-compat elements with class syntax
							'first-line', 'first-letter', 'before', 'after',
						] ),
						new FunctionMatcher( 'lang', new Juxtaposition( [ $ows, $this->ident(), $ows ] ) ),
						new FunctionMatcher( 'dir', new Juxtaposition( [ $ows, $dirValues, $ows ] ) ),
						new FunctionMatcher( 'nth-child', $anplusb ),
						new FunctionMatcher( 'nth-last-child', $anplusb ),
						new FunctionMatcher( 'nth-of-type', $anplusb ),
						new FunctionMatcher( 'nth-last-of-type', $anplusb ),
					] ),
				] ),
				new Juxtaposition( [
					$colon,
					$colon,
					new Alternative( [
						new Juxtaposition( [
							new KeywordMatcher( 'first-letter' ),
							$colon,
							$colon,
							new KeywordMatcher( [ 'prefix', 'postfix' ] ),
						] ),
						new KeywordMatcher( [
							'first-line', 'first-letter', 'before', 'after', 'selection', 'target-text',
							'spelling-error', 'grammar-error', 'marker', 'placeholder',
							'file-selector-button',
						] ),
					] ),
				] ),
			] );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * An "AN+B" form
	 *
	 * https://www.w3.org/TR/2021/CRD-css-syntax-3-20211224/#anb-microsyntax
	 *
	 * @return Matcher
	 */
	public function cssANplusB() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			// Quoth the spec:
			// > The An+B notation was originally defined using a slightly
			// > different tokenizer than the rest of CSS, resulting in a
			// > somewhat odd definition when expressed in terms of CSS tokens.
			// That's a bit of an understatement

			$plusQ = Quantifier::optional( new DelimMatcher( [ '+' ] ) );
			$n = new KeywordMatcher( [ 'n' ] );
			$dashN = new KeywordMatcher( [ '-n' ] );
			$nDash = new KeywordMatcher( [ 'n-' ] );
			$plusQN = new Juxtaposition( [ $plusQ, $n ] );
			$plusQNDash = new Juxtaposition( [ $plusQ, $nDash ] );
			$nDimension = new TokenMatcher( Token::T_DIMENSION, static function ( Token $t ) {
				return $t->typeFlag() === 'integer' && !strcasecmp( $t->unit(), 'n' );
			} );
			$nDashDimension = new TokenMatcher( Token::T_DIMENSION, static function ( Token $t ) {
				return $t->typeFlag() === 'integer' && !strcasecmp( $t->unit(), 'n-' );
			} );
			$nDashDigitDimension = new TokenMatcher( Token::T_DIMENSION, static function ( Token $t ) {
				return $t->typeFlag() === 'integer' && preg_match( '/^n-\d+$/i', $t->unit() );
			} );
			$nDashDigitIdent = new TokenMatcher( Token::T_IDENT, static function ( Token $t ) {
				return preg_match( '/^n-\d+$/i', $t->value() );
			} );
			$dashNDashDigitIdent = new TokenMatcher( Token::T_IDENT, static function ( Token $t ) {
				return preg_match( '/^-n-\d+$/i', $t->value() );
			} );
			$signedInt = new TokenMatcher( Token::T_NUMBER, static function ( Token $t ) {
				return $t->typeFlag() === 'integer' && preg_match( '/^[+-]/', $t->representation() );
			} );
			$signlessInt = new TokenMatcher( Token::T_NUMBER, static function ( Token $t ) {
				return $t->typeFlag() === 'integer' && preg_match( '/^\d/', $t->representation() );
			} );
			$plusOrMinus = new DelimMatcher( [ '+', '-' ] );
			$S = $this->optionalWhitespace();

			$this->cache[__METHOD__] = new Alternative( [
				new KeywordMatcher( [ 'odd', 'even' ] ),
				new TokenMatcher( Token::T_NUMBER, static function ( Token $t ) {
					return $t->typeFlag() === 'integer';
				} ),
				$nDimension,
				$plusQN,
				$dashN,
				$nDashDigitDimension,
				new Juxtaposition( [ $plusQ, $nDashDigitIdent ] ),
				$dashNDashDigitIdent,
				new Juxtaposition( [ $nDimension, $S, $signedInt ] ),
				new Juxtaposition( [ $plusQN, $S, $signedInt ] ),
				new Juxtaposition( [ $dashN, $S, $signedInt ] ),
				new Juxtaposition( [ $nDashDimension, $S, $signlessInt ] ),
				new Juxtaposition( [ $plusQNDash, $S, $signlessInt ] ),
				new Juxtaposition( [ new KeywordMatcher( [ '-n-' ] ), $S, $signlessInt ] ),
				new Juxtaposition( [ $nDimension, $S, $plusOrMinus, $S, $signlessInt ] ),
				new Juxtaposition( [ $plusQN, $S, $plusOrMinus, $S, $signlessInt ] ),
				new Juxtaposition( [ $dashN, $S, $plusOrMinus, $S, $signlessInt ] )
			] );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * A negation (negation)
	 *
	 *     ':' not( S* [ type_selector | universal | HASH | class | attrib | pseudo ] S* ')'
	 *
	 * @return Matcher
	 */
	public function cssNegation() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			// A negation is going to be parsed by the parser as a colon
			// followed by a CSSFunction, so that's what we need to look for
			// here.

			$this->cache[__METHOD__] = new Juxtaposition( [
				new TokenMatcher( Token::T_COLON ),
				new FunctionMatcher( 'not',
					new Juxtaposition( [
						$this->optionalWhitespace(),
						new Alternative( [
							$this->cssTypeSelector(),
							$this->cssUniversal(),
							$this->cssID(),
							$this->cssClass(),
							$this->cssAttrib(),
							$this->cssPseudo(),
						] ),
						$this->optionalWhitespace(),
					] )
				)
			] );
			$this->cache[__METHOD__]->setDefaultOptions( [ 'skip-whitespace' => false ] );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * @return KeywordMatcher
	 */
	public function colorWords(): KeywordMatcher {
		return $this->cache[__METHOD__]
			??= new KeywordMatcher( [
				// Basic colors
				'aqua', 'black', 'blue', 'fuchsia', 'gray', 'green',
				'lime', 'maroon', 'navy', 'olive', 'purple', 'red',
				'silver', 'teal', 'white', 'yellow',
				// Extended colors
				'aliceblue', 'antiquewhite', 'aquamarine', 'azure',
				'beige', 'bisque', 'blanchedalmond', 'blueviolet', 'brown',
				'burlywood', 'cadetblue', 'chartreuse', 'chocolate',
				'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan',
				'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray',
				'darkgreen', 'darkgrey', 'darkkhaki', 'darkmagenta',
				'darkolivegreen', 'darkorange', 'darkorchid', 'darkred',
				'darksalmon', 'darkseagreen', 'darkslateblue',
				'darkslategray', 'darkslategrey', 'darkturquoise',
				'darkviolet', 'deeppink', 'deepskyblue', 'dimgray',
				'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite',
				'forestgreen', 'gainsboro', 'ghostwhite', 'gold',
				'goldenrod', 'greenyellow', 'grey', 'honeydew', 'hotpink',
				'indianred', 'indigo', 'ivory', 'khaki', 'lavender',
				'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue',
				'lightcoral', 'lightcyan', 'lightgoldenrodyellow',
				'lightgray', 'lightgreen', 'lightgrey', 'lightpink',
				'lightsalmon', 'lightseagreen', 'lightskyblue',
				'lightslategray', 'lightslategrey', 'lightsteelblue',
				'lightyellow', 'limegreen', 'linen', 'magenta',
				'mediumaquamarine', 'mediumblue', 'mediumorchid',
				'mediumpurple', 'mediumseagreen', 'mediumslateblue',
				'mediumspringgreen', 'mediumturquoise', 'mediumvioletred',
				'midnightblue', 'mintcream', 'mistyrose', 'moccasin',
				'navajowhite', 'oldlace', 'olivedrab', 'orange',
				'orangered', 'orchid', 'palegoldenrod', 'palegreen',
				'paleturquoise', 'palevioletred', 'papayawhip',
				'peachpuff', 'peru', 'pink', 'plum', 'powderblue',
				'rebeccapurple', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon',
				'sandybrown', 'seagreen', 'seashell', 'sienna', 'skyblue',
				'slateblue', 'slategray', 'slategrey', 'snow',
				'springgreen', 'steelblue', 'tan', 'thistle', 'tomato',
				'turquoise', 'violet', 'wheat', 'whitesmoke',
				'yellowgreen',
				// Other keywords
				'transparent', 'currentColor',
				// System colors
				'AccentColor', 'AccentColorText', 'ActiveText',
				'ButtonBorder', 'ButtonFace', 'ButtonText',
				'Canvas', 'CanvasText', 'Field', 'FieldText',
				'GrayText', 'Highlight', 'HighlightText',
				'LinkText', 'Mark', 'MarkText',
				'SelectedItem', 'SelectedItemText', 'VisitedText',
			] );
	}

	// endregion -- end of CSS Selectors Level 3

}

/*
 * This file uses VisualStudio style region/endregion fold markers which are
 * recognised by PHPStorm. If modelines are enabled, the following editor
 * configuration will also enable folding in vim, if it is in the last 5 lines
 * of the file. We also use "@name" which creates sections in Doxygen.
 *
 * vim: foldmarker=//\ region,//\ endregion foldmethod=marker
 */
