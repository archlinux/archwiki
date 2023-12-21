<?php

namespace MediaWiki\Extension\Gadgets;

use MediaWiki\Extension\CodeEditor\Hooks\CodeEditorGetPageLanguageHook;
use MediaWiki\Title\Title;

/**
 * Hooks from CodeEditor extension,
 * which is optional to use with this extension.
 */
class CodeEditorHooks implements CodeEditorGetPageLanguageHook {

	/**
	 * Set the CodeEditor language for Gadget definition pages. It already
	 * knows the language for Gadget: namespace pages.
	 *
	 * @param Title $title
	 * @param string|null &$lang
	 * @param string $model
	 * @param string $format
	 * @return bool
	 */
	public function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, string $model, string $format ) {
		if ( $title->hasContentModel( 'GadgetDefinition' ) ) {
			$lang = 'json';
			return false;
		}

		return true;
	}

}
