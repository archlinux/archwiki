<?php
namespace MediaWiki\Skins\Vector\Components;

use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Skins\Vector\Constants;
use MediaWiki\Skins\Vector\FeatureManagement\FeatureManager;

/**
 * VectorComponentAppearance component
 */
class VectorComponentAppearance implements VectorComponent {

	private readonly bool $isPinned;

	/** @var string */
	public const ID = 'vector-appearance';

	public function __construct(
		private readonly MessageLocalizer $localizer,
		FeatureManager $featureManager,
	) {
		// FIXME: isPinned is no longer accurate because the appearance menu uses client preferences
		$this->isPinned = $featureManager->isFeatureEnabled( Constants::FEATURE_APPEARANCE_PINNED );
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$pinnedContainer = new VectorComponentPinnableContainer( self::ID, $this->isPinned );
		$pinnableElement = new VectorComponentPinnableElement( self::ID );
		$pinnableHeader = new VectorComponentPinnableHeader(
			$this->localizer,
			$this->isPinned,
			// Name
			self::ID,
			// Feature name
			'appearance-pinned'
		);

		$data = $pinnableElement->getTemplateData() +
			$pinnedContainer->getTemplateData();

		return $data + [
			'data-pinnable-header' => $pinnableHeader->getTemplateData()
		];
	}
}
