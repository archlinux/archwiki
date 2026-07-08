<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\HookHandlers;

use MediaWiki\Hook\EditPage__showEditForm_initialHook;
use MediaWiki\User\Options\UserOptionsLookup;

readonly class EditPageHooksHandler implements EditPage__showEditForm_initialHook {

	public function __construct(
		private UserOptionsLookup $userOptionsLookup
	) {
	}

	/** @inheritDoc */
	public function onEditPage__showEditForm_initial( $editor, $out ): void {
		// Load ext.math when using WikiEditor or CodeMirror.
		if ( $this->userOptionsLookup->getBoolOption( $out->getUser(), 'usebetatoolbar' ) ||
			$this->userOptionsLookup->getBoolOption( $out->getUser(), 'usecodemirror' )
		) {
			$out->addModules( 'ext.math.editpage' );
		}
	}
}
