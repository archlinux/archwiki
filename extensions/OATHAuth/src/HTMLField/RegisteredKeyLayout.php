<?php

namespace MediaWiki\Extension\OATHAuth\HTMLField;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLFormField;
use OOUI\ButtonInputWidget;
use OOUI\HorizontalLayout;
use OOUI\HtmlSnippet;
use OOUI\LabelWidget;

class RegisteredKeyLayout extends HTMLFormField {

	/**
	 * @param array $value
	 * @return string The HorizontalLayout, stringified
	 */
	public function getInputHTML( $value ) {
		$nameValue = $value['name'];

		$name = new LabelWidget( [
			'label' => new HtmlSnippet( Html::element( 'b', [], $nameValue ) )
		] );
		$removeButton = new ButtonInputWidget( [
			'framed' => false,
			'flags' => [ 'primary', 'progressive' ],
			'label' => wfMessage( 'oathauth-webauthn-ui-remove-key' )->plain(),
			'classes' => [ 'removeButton' ],
			'disabled' => true,
			'value' => $nameValue,
			'infusable' => true
		] );

		return (string)new HorizontalLayout( [
			'classes' => [ 'webauthn-key-layout' ],
			'items' => [
				$name, $removeButton
			]
		] );
	}
}
