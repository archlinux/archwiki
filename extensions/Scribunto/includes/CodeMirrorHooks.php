<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Scribunto;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CodeMirror\Hooks\CodeMirrorGetModeHook;
use MediaWiki\Title\Title;

/**
 * Hooks from CodeMirror extension,
 * which is optional to use with this extension.
 */
class CodeMirrorHooks implements CodeMirrorGetModeHook {

	private bool $useCodeMirror;

	public function __construct(
		Config $config,
		private readonly EngineFactory $engineFactory,
	) {
		$this->useCodeMirror = $config->get( 'ScribuntoUseCodeMirror' );
	}

	/**
	 * @param Title $title
	 * @param string|null &$mode
	 * @param string $model
	 * @return bool
	 */
	public function onCodeMirrorGetMode( Title $title, ?string &$mode, string $model ): bool {
		if ( $this->useCodeMirror && $title->hasContentModel( CONTENT_MODEL_SCRIBUNTO ) ) {
			$engine = $this->engineFactory->getDefaultEngine();
			if ( $engine->getCodeMirrorLanguage() ) {
				$mode = $engine->getCodeMirrorLanguage();
				return false;
			}
		}

		return true;
	}

}
