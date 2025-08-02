<?php

namespace MediaWiki\Extension\TemplateStyles;

/**
 * @file
 * @license GPL-2.0-or-later
 */

use MediaWiki\Extension\CodeEditor\Hooks\CodeEditorGetPageLanguageHook;
use MediaWiki\Title\Title;

/**
 * TemplateStyles extension hooks
 * All hooks from the CodeEditor extension which is optional to use with this extension.
 */
class CodeEditorHooks implements
	CodeEditorGetPageLanguageHook
{
	/**
	 * Edit our CSS content model like core's CSS
	 * @param Title $title Title being edited
	 * @param string|null &$lang CodeEditor language to use
	 * @param string $model Content model
	 * @param string $format Content format
	 * @return bool
	 */
	public function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, string $model, string $format ): bool {
		if ( $model === 'sanitized-css' && Hooks::getConfig()->get( 'TemplateStylesUseCodeEditor' ) ) {
			$lang = 'css';
			return false;
		}
		return true;
	}
}
