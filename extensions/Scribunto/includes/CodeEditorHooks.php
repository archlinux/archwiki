<?php

namespace MediaWiki\Extension\Scribunto;

use MediaWiki\Extension\CodeEditor\Hooks\CodeEditorGetPageLanguageHook;
use MediaWiki\Title\Title;

/**
 * Hooks from CodeEditor extension,
 * which is optional to use with this extension.
 */
class CodeEditorHooks implements CodeEditorGetPageLanguageHook {

	/**
	 * @param Title $title
	 * @param string|null &$languageCode
	 * @param string $model
	 * @param string $format
	 * @return bool
	 */
	public function onCodeEditorGetPageLanguage( Title $title, ?string &$languageCode, string $model, string $format ) {
		global $wgScribuntoUseCodeEditor;
		if ( $wgScribuntoUseCodeEditor && $title->hasContentModel( CONTENT_MODEL_SCRIBUNTO ) ) {
			$engine = Scribunto::newDefaultEngine();
			if ( $engine->getCodeEditorLanguage() ) {
				$languageCode = $engine->getCodeEditorLanguage();
				return false;
			}
		}

		return true;
	}

}
