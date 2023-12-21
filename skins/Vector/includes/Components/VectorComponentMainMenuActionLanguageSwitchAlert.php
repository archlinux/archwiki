<?php
namespace MediaWiki\Skins\Vector\Components;

use Html;
use Skin;

/**
 * VectorComponentMainMenuActionLanguageSwitchAlert component
 */
class VectorComponentMainMenuActionLanguageSwitchAlert implements VectorComponent {
	/** @var Skin */
	private $skin;
	/** @var int */
	private $numLanguages;

	/**
	 * @param Skin $skin
	 * @param int $numLanguages
	 */
	public function __construct( Skin $skin, int $numLanguages ) {
		$this->skin = $skin;
		$this->numLanguages = $numLanguages;
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$skin = $this->skin;
		$languageSwitchAlert = [
			'html-content' => Html::noticeBox(
				$skin->msg( 'vector-language-redirect-to-top' )->parse(),
				'vector-language-sidebar-alert'
			),
		];
		$headingOptions = [
			'heading' => $skin->msg( 'vector-languages' )->plain(),
		];

		$component = new VectorComponentMainMenuAction(
			'lang-alert', $skin, $languageSwitchAlert, $headingOptions,
			( $this->numLanguages === 0 ? 'vector-main-menu-action-lang-alert-empty' : '' )
		);
		return $component->getTemplateData();
	}
}
