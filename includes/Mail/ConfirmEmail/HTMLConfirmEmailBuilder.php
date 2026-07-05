<?php

namespace MediaWiki\Mail\ConfirmEmail;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\TemplateParser;
use MediaWiki\ResourceLoader\SkinModule;
use MediaWiki\Utils\UrlUtils;
use Wikimedia\ObjectCache\BagOStuff;

class HTMLConfirmEmailBuilder implements IConfirmEmailBuilder {

	private readonly TemplateParser $templateParser;

	public function __construct(
		private readonly IContextSource $context,
		BagOStuff $cache,
		private readonly UrlUtils $urlUtils
	) {
		$this->templateParser = new TemplateParser(
			dirname( __DIR__, 3 ) . '/resources/templates/ConfirmEmail', $cache
		);
	}

	/**
	 * Build config context for the logo used in EmailCreated.mustache
	 *
	 * @return array|null Null if no logo is configured
	 */
	private function buildLogoContext(): ?array {
		$config = SkinModule::getAvailableLogos(
			$this->context->getConfig(),
			$this->context->getLanguage()->getCode()
		);
		if ( !isset( $config['1x'] ) ) {
			return null;
		}

		return [
			'icon' => [
				'src' => $this->urlUtils->expand( $config['1x'], PROTO_CANONICAL ),
				'alt' => $this->context->msg( 'confirmemail_html_logo_alttext' )->text(),
			],
		];
	}

	public function buildEmailCreated( ConfirmEmailData $data ): ConfirmEmailContent {
		return $this->buildConfirmationEmail(
			$data,
			'confirmemail_html_par1',
			'confirmemail_html_par2'
		);
	}

	public function buildEmailChanged( ConfirmEmailData $data ): ConfirmEmailContent {
		return $this->buildConfirmationEmail(
			$data,
			'confirmemail_html_par1_changed',
			'confirmemail_html_par2_changed'
		);
	}

	public function buildEmailSet( ConfirmEmailData $data ): ConfirmEmailContent {
		return $this->buildConfirmationEmail(
			$data,
			'confirmemail_html_par1_set',
			'confirmemail_html_par2_set'
		);
	}

	/**
	 * Build a confirmation email with the given greeting and body message keys.
	 *
	 * @param ConfirmEmailData $data
	 * @param string $par1Key Message key for the greeting (e.g. "Hello $1,")
	 * @param string $par2Key Message key for the body paragraph
	 */
	private function buildConfirmationEmail(
		ConfirmEmailData $data,
		string $par1Key,
		string $par2Key
	): ConfirmEmailContent {
		$username = $data->getRecipientUser()->getName();
		return new ConfirmEmailContent(
			$this->context->msg( 'confirmemail_html_subject' )->text(),
			implode( PHP_EOL . PHP_EOL, [
				$this->context->msg( $par1Key, $username )->text(),
				$this->context->msg( $par2Key )->text(),
				$this->context->msg(
					'confirmemail_plaintext_button_label',
					$username,
					$data->getConfirmationUrl(),
				)->text(),
				$this->context->msg(
					'confirmemail_plaintext_footer',
					$username,
					$data->getInvalidationUrl(),
				)->text(),
			] ),
			$this->templateParser->processTemplate( 'EmailCreated', [
				'logo' => $this->buildLogoContext(),
				'button' => [
					'confirmationUrl' => $data->getConfirmationUrl(),
					'buttonLabel' => $this->context->msg(
						'confirmemail_html_button_label',
						$username,
					)->text(),
				],
				'par1' => $this->context->msg( $par1Key, $username )->parse(),
				'par2' => $this->context->msg( $par2Key )->parse(),
				'footer' => $this->context->msg(
					'confirmemail_html_footer',
					$username,
					$data->getInvalidationUrl(),
				)->parse(),
				'username' => $username,
			] )
		);
	}
}
