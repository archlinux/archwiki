<?php
namespace MediaWiki\Skins\Vector\Components;

use MediaWiki\Html\Html;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Linker\Linker;

/**
 * VectorComponentLink component
 */
class VectorComponentLink implements VectorComponent {
	/**
	 * @param string $href
	 * @param string $text
	 * @param null|string $icon
	 * @param null|MessageLocalizer $localizer for generation of tooltip and access keys
	 * @param null|string $accessKeyHint will be used to derive HTML attributes such as title, accesskey
	 *   and aria-label ("$accessKeyHint-label")
	 */
	public function __construct(
		private readonly string $href,
		private readonly string $text,
		private readonly ?string $icon = null,
		private readonly ?MessageLocalizer $localizer = null,
		private readonly ?string $accessKeyHint = null,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$localizer = $this->localizer;
		$accessKeyHint = $this->accessKeyHint;
		$additionalAttributes = [];
		if ( $localizer ) {
			$msg = $localizer->msg( $accessKeyHint . '-label' );
			if ( $msg->exists() ) {
				$additionalAttributes[ 'aria-label' ] = $msg->text();
			}
		}
		return [
			'icon' => $this->icon,
			'text' => $this->text,
			'href' => $this->href,
			'html-attributes' => $localizer && $accessKeyHint ? Html::expandAttributes(
				Linker::tooltipAndAccesskeyAttribs(
					$accessKeyHint,
					[],
					[],
					$localizer
				) + $additionalAttributes
			) : '',
		];
	}
}
