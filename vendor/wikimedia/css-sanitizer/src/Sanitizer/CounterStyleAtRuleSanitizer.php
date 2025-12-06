<?php

namespace Wikimedia\CSS\Sanitizer;

use Wikimedia\CSS\Grammar\Alternative;
use Wikimedia\CSS\Grammar\Juxtaposition;
use Wikimedia\CSS\Grammar\KeywordMatcher;
use Wikimedia\CSS\Grammar\Matcher;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Grammar\Quantifier;
use Wikimedia\CSS\Grammar\UnorderedGroup;
use Wikimedia\CSS\Objects\AtRule;
use Wikimedia\CSS\Objects\CSSObject;
use Wikimedia\CSS\Objects\Rule;
use Wikimedia\CSS\Util;

/**
 * Sanitizes a \@counter-style rule
 * @see https://www.w3.org/TR/2021/CR-css-counter-styles-3-20210727/
 */
class CounterStyleAtRuleSanitizer extends RuleSanitizer {
	/** @var Matcher */
	protected $nameMatcher;

	/** @var Sanitizer */
	protected $propertySanitizer;

	public function __construct( MatcherFactory $matcherFactory, array $options = [] ) {
		$this->nameMatcher = $matcherFactory->customIdent( [ 'none' ] );

		// Do not include <image> per at-risk note
		$symbol = new Alternative( [
			$matcherFactory->string(),
			$matcherFactory->customIdent()
		] );

		$integer = $matcherFactory->integer();
		$counterStyleName = $matcherFactory->customIdent( [ 'none' ] );

		$this->propertySanitizer = new PropertySanitizer( [
			'additive-symbols' => Quantifier::hash(
				UnorderedGroup::allOf( [
					$integer,
					$symbol,
				] )
			),
			'fallback' => $counterStyleName,
			'negative' => new Juxtaposition( [
				$symbol,
				Quantifier::optional( $symbol )
			] ),
			'pad' => UnorderedGroup::allOf( [
				$integer,
				$symbol
			] ),
			'prefix' => $symbol,
			'range' => new Alternative( [
				Quantifier::hash(
					Quantifier::count(
						new Alternative( [
							$integer,
							new KeywordMatcher( 'infinite' )
						] ),
						2, 2
					)
				),
				new KeywordMatcher( 'auto' )
			] ),
			'speak-as' => new Alternative( [
				new KeywordMatcher( [
					'auto', 'bullets', 'numbers', 'words', 'spell-out'
				] ),
				$counterStyleName
			] ),
			'suffix' => $symbol,
			'symbols' => Quantifier::plus( $symbol ),
			'system' => new Alternative( [
				new KeywordMatcher( [
					'cyclic', 'numeric', 'alphabetic', 'symbolic', 'additive'
				] ),
				new Juxtaposition( [
					new KeywordMatcher( 'fixed' ),
					Quantifier::optional( $integer )
				] ),
				new Juxtaposition( [
					new KeywordMatcher( 'extends' ),
					$counterStyleName
				] )
			] )
		] );
	}

	/** @inheritDoc */
	public function handlesRule( Rule $rule ) {
		return $rule instanceof AtRule && !strcasecmp( $rule->getName(), 'counter-style' );
	}

	/** @inheritDoc */
	protected function doSanitize( CSSObject $object ) {
		if ( !$object instanceof AtRule || !$this->handlesRule( $object ) ) {
			$this->sanitizationError( 'expected-at-rule', $object, [ 'counter-style' ] );
			return null;
		}

		if ( $object->getBlock() === null ) {
			$this->sanitizationError( 'at-rule-block-required', $object, [ 'counter-style' ] );
			return null;
		}

		// Test the name
		if ( !$this->nameMatcher->matchAgainst(
			$object->getPrelude(), [ 'mark-significance' => true ]
		) ) {
			$cv = Util::findFirstNonWhitespace( $object->getPrelude() );
			if ( $cv ) {
				$this->sanitizationError( 'invalid-counter-style-name', $cv );
			} else {
				$this->sanitizationError( 'missing-counter-style-name', $object );
			}
			return null;
		}

		// Test the declaration list
		$ret = clone $object;
		$this->fixPreludeWhitespace( $ret, false );
		$this->sanitizeDeclarationBlock( $ret->getBlock(), $this->propertySanitizer );
		return $ret;
	}
}
