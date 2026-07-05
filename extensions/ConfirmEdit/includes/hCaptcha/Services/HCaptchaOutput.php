<?php

namespace MediaWiki\Extension\ConfirmEdit\hCaptcha\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\ConfirmEdit\CaptchaTriggers;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\HCaptcha;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;
use Wikimedia\Assert\Assert;

/**
 * Service used to de-duplicate the code for adding hCaptcha to the output in both
 * {@link HCaptcha} and {@link HTMLHCaptchaField}.
 */
class HCaptchaOutput {

	/** @internal Only public for service wiring use. */
	public const CONSTRUCTOR_OPTIONS = [
		'HCaptchaSiteKey',
		'HCaptchaEnterprise',
		'HCaptchaSecureEnclave',
		'HCaptchaInvisibleMode',
		'HCaptchaApiUrl',
	];

	public function __construct(
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Returns the HTML needed to add HCaptcha to a form.
	 *
	 * This method also adds other required script tags and ResourceLoader modules to the provided OutputPage.
	 *
	 * @param OutputPage $outputPage
	 * @param bool $previouslyFailedHCaptcha Whether the user has failed HCaptcha for a previous attempt at
	 *   submitting the associated form.
	 * @return string HTML of the hCaptcha fields to append to the outputted HTML
	 */
	public function addHCaptchaToForm( OutputPage $outputPage, bool $previouslyFailedHCaptcha ): string {
		if ( $outputPage->getTitle()->isSpecial( 'CreateAccount' ) ) {
			$action = CaptchaTriggers::CREATE_ACCOUNT;
		} elseif ( $outputPage->getTitle()->exists() ) {
			$action = CaptchaTriggers::EDIT;
		} else {
			$action = CaptchaTriggers::CREATE;
		}
		/** @var HCaptcha $simpleCaptcha */
		$simpleCaptcha = Hooks::getInstance( $action );
		Assert::postcondition(
			$simpleCaptcha instanceof HCaptcha, '$simpleCaptcha is not an instance of HCaptcha'
		);
		$siteKey = $simpleCaptcha->getSiteKeyForAction();
		$useInvisibleMode = $this->options->get( 'HCaptchaInvisibleMode' );

		$hCaptchaElementAttribs = [
			'id' => 'h-captcha',
			'class' => [
				'h-captcha',
				'mw-confirmedit-captcha-fail' => $previouslyFailedHCaptcha,
			],
			'data-sitekey' => $siteKey,
		];
		if ( $useInvisibleMode ) {
			$hCaptchaElementAttribs['data-size'] = 'invisible';
		}
		$output = Html::element( 'div', $hCaptchaElementAttribs );

		$useSecureEnclave = $this->options->get( 'HCaptchaEnterprise' ) &&
			$this->options->get( 'HCaptchaSecureEnclave' );
		if ( !$useSecureEnclave ) {
			$hCaptchaApiUrl = $this->options->get( 'HCaptchaApiUrl' );
			$outputPage->addHeadItem(
				'h-captcha',
				Html::element( 'script', [ 'src' => $hCaptchaApiUrl, 'async', 'defer' ] )
			);
		}

		// Load the hCaptcha module if we are adding the hCaptcha field. This will handle the secure enclave mode if
		// it is enabled.
		$outputPage->addModules( 'ext.confirmEdit.hCaptcha' );
		$outputPage->addModuleStyles( 'ext.confirmEdit.hCaptcha.styles' );

		if ( $useInvisibleMode ) {
			$output .= Html::rawElement(
				'div',
				[ 'class' => 'h-captcha-privacy-policy' ],
				$outputPage->msg( 'hcaptcha-privacy-policy' )->parse()
			);
		}

		// Add noscript message for users with JavaScript disabled in edit page
		$output .= Html::rawElement(
			'noscript',
			[ 'class' => 'h-captcha-noscript-container' ],
			Html::rawElement(
				'div',
				[ 'class' => 'h-captcha-noscript-message cdx-message cdx-message--error' ],
				$outputPage->msg( 'hcaptcha-noscript' )->parse()
			)
		);
		if ( $simpleCaptcha->shouldForceShowCaptcha() ) {
			// Set a flag that can be used in HCaptcha::shouldCheck() to know if the "showcaptcha"
			// AbuseFilter consequence was invoked.
			$output .= Html::hidden( 'wgConfirmEditForceShowCaptcha', true );
		}
		return $output;
	}
}
