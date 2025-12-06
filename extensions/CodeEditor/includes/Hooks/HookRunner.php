<?php

namespace MediaWiki\Extension\CodeEditor\Hooks;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Title\Title;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class HookRunner implements
	CodeEditorGetPageLanguageHook
{
	public function __construct(
		private readonly HookContainer $hookContainer,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, string $model, string $format ) {
		return $this->hookContainer->run(
			'CodeEditorGetPageLanguage',
			[ $title, &$lang, $model, $format ]
		);
	}
}
