<?php

namespace Cite;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityConfiguration\Provider\ConfigurationProviderFactory;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use stdClass;

/**
 * Render the label for backlink marks, for example "a", "b", …
 *
 * The backlink mark alphabet can be customized.
 *
 * @license GPL-2.0-or-later
 */
class BacklinkMarkRenderer {

	/** @var string[]|null In-memory cache for the alphabet */
	private ?array $alphabet = null;
	/** @var string[]|null In-memory cache for the i18n-configured sequence */
	private ?array $legacySequence = null;

	private ?IConfigurationProvider $configProvider = null;

	public function __construct(
		private readonly string $languageCode,
		private readonly ReferenceMessageLocalizer $messageLocalizer,
		private readonly AlphabetsProvider $alphabetsProvider,
		?ConfigurationProviderFactory $providerFactory,
		private readonly Config $config,
	) {
		if ( $providerFactory && $this->config->get( 'CiteBacklinkCommunityConfiguration' ) ) {
			$this->configProvider = $providerFactory->newProvider( 'Cite' );
		}
	}

	/**
	 * Calculate the alphabetic sequence marker for this index
	 *
	 * @param int $reuseIndex 1-based index into the reference's reuses
	 * @return string rendered marker
	 */
	public function getBacklinkMarker( int $reuseIndex ): string {
		// Lazy initialization only when needed because this is a little expensive
		$this->alphabet ??= $this->getBacklinkAlphabet( $this->languageCode );

		$radix = count( $this->alphabet );
		$label = '';
		while ( $reuseIndex > 0 ) {
			// Logically, our alphabet is meant to be 1-based but the array is 0-based
			$reuseIndex--;
			// Start with the least significant place, walking up from right to left
			$label = $this->alphabet[$reuseIndex % $radix] . $label;
			$reuseIndex = (int)( $reuseIndex / $radix );
		}
		return $label;
	}

	/**
	 * Calculate the numeric sequence marker for this index
	 *
	 * Constructs backlink markers like "1.0, 1.1"
	 *
	 * @deprecated since 1.44
	 * @param int $reuseIndex 1-based index into the reference's reuses
	 * @param int $reuseCount total number of reuses, in order to calculate zero-padding.
	 * @param string $parentLabel footnote marker to use as the prefix for each backlink, for example "1"
	 * @return string rendered and localized marker
	 */
	public function getLegacyNumericMarker( int $reuseIndex, int $reuseCount, string $parentLabel = '' ): string {
		return ( $parentLabel ?
				$parentLabel . $this->messageLocalizer->localizeSeparators( '.' ) : '' ) .
			$this->messageLocalizer->localizeDigits(
				str_pad( (string)$reuseIndex, strlen( (string)$reuseCount ), '0', STR_PAD_LEFT ) );
	}

	/**
	 * Render the legacy i18n alphabetic sequence marker for this index
	 *
	 * Falls back to numeric markers if the legacy sequence is overrun.
	 *
	 * @deprecated since 1.44
	 * @param int $reuseIndex 1-based index into the reference's reuses
	 * @param int $reuseCount total number of reuses, in order to calculate zero-padding.
	 * @param string $parentLabel footnote marker to use as the prefix for each backlink, for example "1"
	 * @return string rendered marker
	 */
	public function getLegacyAlphabeticMarker( int $reuseIndex, int $reuseCount, string $parentLabel = '' ): string {
		if ( $this->legacySequence === null ) {
			$msg = $this->messageLocalizer->msg( 'cite_references_link_many_format_backlink_labels' );
			$this->legacySequence = $msg->isDisabled() ?
				[] :
				preg_split( '/\s+/', $msg->plain() );
		}

		return $this->legacySequence[$reuseIndex - 1]
			?? $this->getLegacyNumericMarker( $reuseIndex, $reuseCount, $parentLabel );
	}

	/**
	 * Return true if the site is configured to use legacy backlink markers
	 *
	 * @deprecated since 1.44
	 * @return bool
	 */
	public function isLegacyMode(): bool {
		return $this->config->get( 'CiteUseLegacyBacklinkLabels' );
	}

	/**
	 * @return string localized "↑"
	 */
	public function getUpArrow(): string {
		// FIXME: This i18n message is not commonly used and will need to be
		// configured on most wikis. Would be preferable to start with a Community
		// Configuration knob.
		return $this->messageLocalizer->msg( 'cite_reference_backlink_symbol' )->plain();
	}

	private function loadCommunityConfig(): stdClass {
		if ( $this->configProvider ) {
			$status = $this->configProvider->loadValidConfiguration();
			if ( $status->isOK() ) {
				return $status->getValue();
			}
		}
		return (object)[];
	}

	/**
	 * Returns an Alphabet of symbols that can be used to generate backlink markers.
	 * The Alphabet will either be retrieved from config or the CLDR AlphabetsProvider.
	 *
	 * @param string $languageCode
	 * @return string[]
	 */
	private function getBacklinkAlphabet( string $languageCode ): array {
		$alphabetString = $this->loadCommunityConfig()->Cite_Settings->backlinkAlphabet ??
			$this->config->get( 'CiteDefaultBacklinkAlphabet' ) ?? '';

		$alphabet = preg_split(
			'/\s+/',
			$alphabetString,
			-1,
			PREG_SPLIT_NO_EMPTY
		);

		if ( !$alphabet ) {
			$alphabet = $this->alphabetsProvider->getIndexCharacters( $languageCode ) ?? [];
			$alphabet = array_map( 'mb_strtolower', $alphabet );
		}

		if ( !$alphabet ) {
			$alphabet = range( 'a', 'z' );
		}

		return $alphabet;
	}

}
