<?php
namespace MediaWiki\Skins\Vector\Components;

use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Skin\Skin;
use MediaWiki\Skins\Vector\Constants;
use MediaWiki\Skins\Vector\FeatureManagement\FeatureManager;
use MediaWiki\User\UserIdentity;

/**
 * VectorComponentMainMenu component
 */
class VectorComponentMainMenu implements VectorComponent {
	/** @var bool */
	private $includeLanguages;
	private readonly bool $isPinned;
	private readonly VectorComponentPinnableHeader $pinnableHeader;
	/** @var string */
	public const ID = 'vector-main-menu';

	public function __construct(
		private readonly array $sidebarData,
		private readonly array $languageData,
		private readonly MessageLocalizer $localizer,
		private readonly UserIdentity $user,
		private readonly FeatureManager $featureManager,
		Skin $skin,
	) {
		$this->isPinned = $featureManager->isFeatureEnabled( Constants::FEATURE_MAIN_MENU_PINNED );
		$this->includeLanguages = $languageData && (
			$featureManager->isFeatureEnabled( Constants::FEATURE_LANGUAGE_IN_MAIN_MENU ) ||
			!$featureManager->isFeatureEnabled( Constants::FEATURE_LANGUAGE_IN_HEADER )
		);

		$this->pinnableHeader = new VectorComponentPinnableHeader(
			$this->localizer,
			$this->isPinned,
			self::ID,
			'main-menu-pinned'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$portletsRest = [];
		foreach ( $this->sidebarData[ 'array-portlets-rest' ] as $data ) {
			$portletsRest[] = ( new VectorComponentMenu( $data ) )->getTemplateData();
		}
		$firstPortlet = new VectorComponentMenu( $this->sidebarData['data-portlets-first'] );
		$languageMenu = new VectorComponentMenu( $this->languageData );

		$pinnableContainer = new VectorComponentPinnableContainer( self::ID, $this->isPinned );
		$pinnableElement = new VectorComponentPinnableElement( self::ID );

		return $pinnableElement->getTemplateData() + $pinnableContainer->getTemplateData() + [
			'data-portlets-first' => $firstPortlet->getTemplateData(),
			'array-portlets-rest' => $portletsRest,
			'data-pinnable-header' => $this->pinnableHeader->getTemplateData(),
			'data-languages' => $languageMenu->getTemplateData(),
			'is-languages-included' => $this->includeLanguages,
		];
	}
}
