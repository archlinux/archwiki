<?php

namespace MediaWiki\Extension\OATHAuth\HTMLField;

use MediaWiki\HTMLForm\HTMLFormField;
use OOUI\ActionFieldLayout;
use OOUI\ButtonWidget;
use OOUI\TextInputWidget;

class AddKeyLayout extends HTMLFormField {

	/**
	 * @param string $value
	 * @return string The ActionFieldLayout, stringified
	 */
	public function getInputHTML( $value ) {
		// We initiate the fields disabled, to avoid user interacting
		// with the form, until the client-side script is loaded and ready
		$input = new TextInputWidget( [
			'id' => 'key_name',
			'name' => 'key_name',
			'required' => false,
			'infusable' => true,
			'disabled' => true,
			'autofocus' => true,
		] );
		$button = new ButtonWidget( [
			'flags' => [ 'primary', 'progressive' ],
			'label' => wfMessage( 'oathauth-webauthn-ui-add-key' )->plain(),
			'disabled' => true,
			'id' => 'button_add_key',
			'infusable' => true
		] );
		return (string)new ActionFieldLayout( $input, $button );
	}
}
