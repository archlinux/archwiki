<?php

namespace MediaWiki\Extension\OATHAuth\HTMLField;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\Field\HTMLInfoField;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;

class NoJsInfoField extends HTMLInfoField {

	/** @inheritDoc */
	public function __construct( $info ) {
		parent::__construct( $info );

		$this->mClass .= ' webauthn-nojs';
	}

	/** @inheritDoc */
	public function getInputOOUI( $value ) {
		// This style is used in our management forms
		$this->mParent->getOutput()->addModuleStyles( 'ext.webauthn.ui.base.styles' );
		return new MessageWidget( [
			'type' => 'error',
			'label' => new HtmlSnippet( wfMessage( 'oathauth-webauthn-javascript-required' )->parse() ),
		] );
	}

	/** @inheritDoc */
	public function getInputHTML( $value ) {
		// This style is used in the MediaWiki auth forms
		$this->mParent->getOutput()->addModuleStyles( 'ext.webauthn.ui.base.styles' );
		$this->mParent->getOutput()->addModuleStyles( 'mediawiki.codex.messagebox.styles' );
		return Html::errorBox( wfMessage( 'oathauth-webauthn-javascript-required' )->parse() );
	}
}
