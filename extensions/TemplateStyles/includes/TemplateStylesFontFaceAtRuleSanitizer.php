<?php

namespace MediaWiki\Extension\TemplateStyles;

/**
 * @file
 * @license GPL-2.0-or-later
 */

use Wikimedia\CSS\Grammar\Alternative;
use Wikimedia\CSS\Grammar\Juxtaposition;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Grammar\Quantifier;
use Wikimedia\CSS\Grammar\TokenMatcher;
use Wikimedia\CSS\Objects\Token;
use Wikimedia\CSS\Sanitizer\FontFaceAtRuleSanitizer;

/**
 * Extend the standard `@font-face` matcher to require a prefix on families.
 */
class TemplateStylesFontFaceAtRuleSanitizer extends FontFaceAtRuleSanitizer {

	/**
	 * @param MatcherFactory $matcherFactory
	 */
	public function __construct( MatcherFactory $matcherFactory ) {
		parent::__construct( $matcherFactory );

		// Only allow the font-family if it begins with "TemplateStyles"
		$validator = static fn ( Token $t ) => str_starts_with( $t->value(), 'TemplateStyles' );
		$this->propertySanitizer->setKnownProperties( [
			'font-family' => new Alternative( [
				new TokenMatcher( Token::T_STRING, $validator ),
				new Juxtaposition( [
					new TokenMatcher( Token::T_IDENT, $validator ),
					Quantifier::star( $matcherFactory->ident() ),
				] ),
			] ),
		] + $this->propertySanitizer->getKnownProperties() );
	}
}
