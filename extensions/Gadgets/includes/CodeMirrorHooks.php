<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Gadgets;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CodeMirror\Hooks\CodeMirrorGetModeHook;
use MediaWiki\Title\Title;

/**
 * Hooks for optional integration with the CodeMirror extension.
 */
class CodeMirrorHooks implements CodeMirrorGetModeHook {

	private readonly bool $useCodeMirror;

	public function __construct( Config $config ) {
		$this->useCodeMirror = $config->get( 'ScribuntoUseCodeMirror' );
	}

	/**
	 * Set the CodeMirror mode for GadgetDefinition pages.
	 *
	 * The CodeMirror extension sets the default syntax highlight mode based on
	 * the content model (not page title), so while gadget definitions have ".json"
	 * page titles, the fact that we use a more specific subclass as content model,
	 * means we must explicitly opt-in to JSON syntax highlighting.
	 *
	 * @param Title $title
	 * @param ?string &$mode
	 * @param string $model
	 * @return bool
	 */
	public function onCodeMirrorGetMode( Title $title, ?string &$mode, string $model ): bool {
		if ( $this->useCodeMirror && $title->hasContentModel( 'GadgetDefinition' ) ) {
			$mode = 'json';
			return false;
		}

		return true;
	}
}
