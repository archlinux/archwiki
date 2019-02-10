<?php

class SpecialCaptcha extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'Captcha' );
	}

	public function execute( $par ) {
		$this->setHeaders();

		$instance = ConfirmEditHooks::getInstance();

		switch ( $par ) {
			case "image":
				if ( method_exists( $instance, 'showImage' ) ) {
					// @todo: Do this in a more OOP way
					return $instance->showImage();
				}
			case "help":
			default:
				return $instance->showHelp();
		}
	}
}
