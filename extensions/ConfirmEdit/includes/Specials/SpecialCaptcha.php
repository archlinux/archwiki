<?php

namespace MediaWiki\Extension\ConfirmEdit\Specials;

use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaStore;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\UnlistedSpecialPage;

class SpecialCaptcha extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'Captcha' );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$this->setHeaders();

		// TODO: This should probably be passing an action otherwise it's going to just fallback to $wgCaptchaClass
		$instance = Hooks::getInstance();

		if ( $par === 'image' && method_exists( $instance, 'showImage' ) ) {
			// @todo: Do this in a more OOP way
			/** @phan-suppress-next-line PhanUndeclaredMethod */
			$instance->showImage( $this->getContext() );
			return;
		}

		$out = $this->getOutput();

		$out->setPageTitleMsg( $out->msg( 'captchahelp-title' ) );
		$out->addWikiMsg( 'captchahelp-text' );

		if ( CaptchaStore::get()->cookiesNeeded() ) {
			$out->addWikiMsg( 'captchahelp-cookies-needed' );
		}

		foreach ( Hooks::getActiveCaptchas() as $captcha ) {
			/** @var SimpleCaptcha $captcha */

			$out->addHtml(
				Html::element(
					'h2',
					[],
					$captcha->getName()
				)
			);

			$captcha->showHelp( $out );
		}
	}
}
