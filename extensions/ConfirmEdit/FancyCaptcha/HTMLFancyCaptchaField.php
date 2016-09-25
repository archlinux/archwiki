<?php

/**
 * Captcha input field for FancyCaptcha that displays a question and returns the answer.
 * Does not include the captcha ID; that must be included in the form as a separate hidden field.
 */
class HTMLFancyCaptchaField extends HTMLFormField {
	/** @var string */
	protected $imageUrl;

	/** @var bool */
	protected $showCreateHelp;

	protected $mClass = 'captcha';

	/**
	 * Apart from normal HTMLFormField parameters, recognizes the following keys:
	 * - 'imageUrl': (string, required) src of the captcha image
	 * - 'showCreateHelp': (bool) show some extra messaging that's only relevant at account creation
	 * @param array $params
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );
		$this->imageUrl = $params['imageUrl'];
		$this->showCreateHelp = !empty( $params['showCreateHelp'] );
	}

	public function getInputHTML( $value ) {
		global $wgEnableAPI;

		$out = $this->mParent->getOutput();

		// Uses addModuleStyles so it is loaded when JS is disabled.
		$out->addModuleStyles( 'ext.confirmEdit.fancyCaptcha.styles' );

		if ( $wgEnableAPI ) {
			// Loaded only if JS is enabled
			$out->addModules( 'ext.confirmEdit.fancyCaptcha' );

			$captchaReload = Html::element(
				'small',
				[ 'class' => 'confirmedit-captcha-reload fancycaptcha-reload' ],
				wfMessage( 'fancycaptcha-reload-text' )->text()
			);
		} else {
			$captchaReload = '';
		}

		$attribs = [
			'type' => 'text',
			'id'   => $this->mID,
			'name' => $this->mName,
			'class' => 'mw-ui-input',
			'size' => '12',  // max_length in captcha.py plus fudge factor
			'dir' => $this->mDir,
			'autocomplete' => 'off',
			'autocorrect' => 'off',
			'autocapitalize' => 'off',
			'placeholder' => wfMessage( 'fancycaptcha-imgcaptcha-ph' )
		];
		$attribs += $this->getAttributes( [ 'tabindex', 'required', 'autofocus' ] );

		$html = Html::openElement( 'div', [ 'class' => 'fancycaptcha-captcha-container' ] )
			. Html::openElement( 'div', [ 'class' => 'fancycaptcha-captcha-and-reload' ] )
			. Html::openElement( 'div', [ 'class' => 'fancycaptcha-image-container' ] )
			. Html::element( 'img', [
				'class'  => 'fancycaptcha-image',
				'src'    => $this->imageUrl,
				'alt'    => ''
			] ) . $captchaReload . Html::closeElement( 'div' ) . Html::closeElement( 'div' ) . "\n"
			. Html::element( 'input', $attribs );

		if ( $this->showCreateHelp ) {
			// use raw element, the message will contain a link
			$html .= Html::rawElement( 'small', [
				'class' => 'mw-createacct-captcha-assisted'
			], wfMessage( 'createacct-imgcaptcha-help' )->parse() );
		}

		$html .= Html::closeElement( 'div' );

		return $html;
	}

	public function getLabel() {
		// slight abuse of what getLabel() should mean; $mLabel is used for the pre-label text
		// as the actual label is always the same
		return wfMessage( 'captcha-label' )->text() . ' ' . wfMessage( 'fancycaptcha-captcha' )->text();
	}

	public function getLabelHtml( $cellAttributes = [] ) {
		$labelHtml = parent::getLabelHtml( $cellAttributes );
		if ( $this->mLabel ) {
			// use raw element, the message will contain a link
			$labelHtml = Html::rawElement( 'p', [], $this->mLabel ) . $labelHtml;
		}
		return $labelHtml;
	}
}
