<?php

namespace MediaWiki\Extension\TemplateStyles\Hooks;

use MediaWiki\HookContainer\HookContainer;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;
use Wikimedia\CSS\Sanitizer\StylesheetSanitizer;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class HookRunner implements
	TemplateStylesPropertySanitizerHook,
	TemplateStylesStylesheetSanitizerHook
{
	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onTemplateStylesPropertySanitizer(
		StylePropertySanitizer &$propertySanitizer,
		MatcherFactory $matcherFactory
	) {
		return $this->hookContainer->run(
			'TemplateStylesPropertySanitizer',
			[ &$propertySanitizer, $matcherFactory ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onTemplateStylesStylesheetSanitizer(
		StylesheetSanitizer &$sanitizer,
		StylePropertySanitizer $propertySanitizer,
		MatcherFactory $matcherFactory
	) {
		return $this->hookContainer->run(
			'TemplateStylesStylesheetSanitizer',
			[ &$sanitizer, $propertySanitizer, $matcherFactory ]
		);
	}
}
