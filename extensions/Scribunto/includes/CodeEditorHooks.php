<?php

namespace MediaWiki\Extension\Scribunto;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CodeEditor\Hooks\CodeEditorGetPageLanguageHook;
use MediaWiki\Title\Title;

/**
 * Hooks from CodeEditor extension,
 * which is optional to use with this extension.
 */
class CodeEditorHooks implements CodeEditorGetPageLanguageHook {

	private bool $useCodeEditor;

	public function __construct(
		Config $config,
		private readonly EngineFactory $engineFactory,
	) {
		$this->useCodeEditor = $config->get( 'ScribuntoUseCodeEditor' );
	}

	/**
	 * @param Title $title
	 * @param string|null &$languageCode
	 * @param string $model
	 * @param string $format
	 * @return bool
	 */
	public function onCodeEditorGetPageLanguage( Title $title, ?string &$languageCode, string $model, string $format ) {
		if ( $title->hasContentModel( CONTENT_MODEL_SCRIBUNTO ) && (
				$this->useCodeEditor ||
				// Temporary while CodeMirror is still in beta (T373711#11018957).
				!( \MediaWiki\Extension\CodeEditor\Hooks::tempIsCodeMirrorEnabled() )
			)
		) {
			$engine = $this->engineFactory->getDefaultEngine();
			if ( $engine->getCodeEditorLanguage() ) {
				$languageCode = $engine->getCodeEditorLanguage();
				return false;
			}
		}

		return true;
	}

}
