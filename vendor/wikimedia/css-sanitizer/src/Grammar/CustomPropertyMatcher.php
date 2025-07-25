<?php
/**
 * @file
 * @license https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */

namespace Wikimedia\CSS\Grammar;

use Wikimedia\CSS\Objects\ComponentValueList;
use Wikimedia\CSS\Objects\Token;

/**
 * Matcher that matches a custom property (a CSS variable) example --name-of-variable
 *
 * This utilises regex to match the custom property --[a-zA-Z][a-zA-Z0-9-] without any input validation.
 *
 * @see https://www.w3.org/TR/2022/CR-css-variables-1-20220616/#custom-property
 */
class CustomPropertyMatcher extends Matcher {

	/** @inheritDoc */
	protected function generateMatches( ComponentValueList $values, $start, array $options ) {
		$cv = $values[$start] ?? null;
		if ( $cv instanceof Token && $cv->type() === Token::T_IDENT
			&& preg_match( '/^\-\-[a-zA-Z][a-zA-Z0-9-]*$/', $cv->value() ) ) {
			yield $this->makeMatch( $values, $start, $this->next( $values, $start, $options ) );
		}
	}
}
