<?php

namespace MediaWiki\Extension\TemplateStyles\Hooks;

use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "TemplateStylesPropertySanitizer" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface TemplateStylesPropertySanitizerHook {
	/**
	 * Allows for adjusting or replacing the StylePropertySanitizer used when sanitizing style rules.
	 * For example, you might add, remove, or redefine known properties
	 *
	 * @param StylePropertySanitizer &$propertySanitizer StylePropertySanitizer to be used  for sanitization
	 * @param MatcherFactory $matcherFactory MatcherFactory being used, for use in adding or redefining known
	 *  properties or replacing the entire sanitizer
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onTemplateStylesPropertySanitizer(
		StylePropertySanitizer &$propertySanitizer,
		MatcherFactory $matcherFactory
	);
}
