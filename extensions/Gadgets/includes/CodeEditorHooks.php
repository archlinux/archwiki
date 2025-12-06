<?php

namespace MediaWiki\Extension\Gadgets;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CodeEditor\Hooks\CodeEditorGetPageLanguageHook;
use MediaWiki\Title\Title;

/**
 * Hooks for optional integration with the CodeEditor extension.
 */
class CodeEditorHooks implements CodeEditorGetPageLanguageHook {

	private readonly bool $useCodeEditor;

	public function __construct(
		Config $config
	) {
		$this->useCodeEditor = $config->get( 'GadgetsDefinitionsUseCodeEditor' );
	}

	/**
	 * Set the CodeEditor language for GadgetDefinition pages.
	 *
	 * The CodeEditor extension sets the default syntax highlight language based
	 * on the content model (not page title), so while gadget definitions have ".json"
	 * page titles, the fact that we use a more specific subclass as content model,
	 * means we must explicitly opt-in to JSON syntax highlighting.
	 *
	 * @param Title $title
	 * @param string|null &$lang
	 * @param string $model
	 * @param string $format
	 * @return bool
	 */
	public function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, string $model, string $format ) {
		if ( $title->hasContentModel( 'GadgetDefinition' ) && (
				$this->useCodeEditor ||
				// Temporary code while CodeMirror is still in beta (T373711#11018957).
				// If the beta feature is disabled, we want to fallback to using CodeEditor.
				// Temporary while CodeMirror is still in beta (T373711#11018957).
				!( \MediaWiki\Extension\CodeEditor\Hooks::tempIsCodeMirrorEnabled() )
			)
		) {
			$lang = 'json';
			return false;
		}

		return true;
	}

}
