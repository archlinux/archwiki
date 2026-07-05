<?php
namespace MediaWiki\Skins\Vector\Components;

use MediaWiki\Config\Config;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Skins\Vector\Constants;
use MediaWiki\Skins\Vector\FeatureManagement\FeatureManager;

/**
 * VectorComponentTableOfContents component
 */
class VectorComponentTableOfContents implements VectorComponent {

	private readonly bool $isPinned;
	private readonly VectorComponentPinnableHeader $pinnableHeader;

	/** @var string */
	public const ID = 'vector-toc';

	public function __construct(
		private array $tocData,
		private readonly MessageLocalizer $localizer,
		private readonly Config $config,
		FeatureManager $featureManager,
	) {
		// FIXME: isPinned is no longer accurate because the appearance menu uses client preferences
		$this->isPinned = $featureManager->isFeatureEnabled( Constants::FEATURE_TOC_PINNED );
		$this->pinnableHeader = new VectorComponentPinnableHeader(
			$this->localizer,
			$this->isPinned,
			self::ID,
			'toc-pinned',
			'h2'
		);
	}

	public function isPinned(): bool {
		return $this->isPinned;
	}

	/**
	 * In tableOfContents.js we have tableOfContents::getTableOfContentsSectionsData(),
	 * that yields the same result as this function, please make sure to keep them in sync.
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$sections = $this->tocData[ 'array-sections' ] ?? [];
		if ( !$sections ) {
			return [];
		}
		// Populate button labels for collapsible TOC sections
		foreach ( $sections as &$section ) {
			if ( $section['is-top-level-section'] && $section['is-parent-section'] ) {
				$section['vector-button-label'] =
					$this->localizer->msg( 'vector-toc-toggle-button-label' )
						->rawParams( $section['line'] )
						->escaped();
			}
		}
		$this->tocData[ 'array-sections' ] = $sections;

		$pinnableElement = new VectorComponentPinnableElement( self::ID );

		return $pinnableElement->getTemplateData() +
			array_merge( $this->tocData, [
			'vector-is-collapse-sections-enabled' =>
				count( $this->tocData['array-sections'] ) > 3 &&
				$this->tocData[ 'number-section-count'] >= $this->config->get(
					'VectorTableOfContentsCollapseAtCount'
				),
			'data-pinnable-header' => $this->pinnableHeader->getTemplateData(),
		] );
	}
}
