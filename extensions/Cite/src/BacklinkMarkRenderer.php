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

	/** @var string[] In-memory cache for the alphabet */
	private array $alphabet;
	/** @var string[]|null In-memory cache for the i18n-configured sequence */
	private ?array $legacySequence;

	private ReferenceMessageLocalizer $messageLocalizer;
	private AlphabetsProvider $alphabetsProvider;
	private ?IConfigurationProvider $configProvider = null;
	private Config $config;

	public function __construct(
		string $languageCode,
		ReferenceMessageLocalizer $messageLocalizer,
		AlphabetsProvider $alphabetsProvider,
		?ConfigurationProviderFactory $providerFactory,
		Config $config
	) {
		$this->messageLocalizer = $messageLocalizer;
		$this->alphabetsProvider = $alphabetsProvider;
		$this->config = $config;

		if ( $providerFactory && $this->config->get( 'CiteBacklinkCommunityConfiguration' ) ) {
			$this->configProvider = $providerFactory->newProvider( 'Cite' );
		}

		$this->alphabet = $this->getBacklinkAlphabet( $languageCode );

		$legacySequenceMessage = $this->messageLocalizer->msg( 'cite_references_link_many_format_backlink_labels' );
		$this->legacySequence = $legacySequenceMessage->isDisabled()
			? null : preg_split( '/\s+/', $legacySequenceMessage->plain() );
	}

	/**
	 * Calculate the alphabetic sequence marker for this index
	 *
	 * @param int $reuseIndex 1-based index into the reference's reuses
	 * @return string rendered marker
	 */
	public function getBacklinkMarker( int $reuseIndex ): string {
		return $this->buildAlphaLabel( $this->alphabet, $reuseIndex );
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

	private function loadCommunityConfig(): ?stdClass {
		if ( $this->configProvider ) {
			$status = $this->configProvider->loadValidConfiguration();
			return $status->isOK() ? $status->getValue() : null;
		}
		return null;
	}

	/**
	 * Returns an Alphabet of symbols that can be used to generate backlink markers.
	 * The Alphabet will either be retrieved from config or the CLDR AlphabetsProvider.
	 *
	 * @param string $code
	 * @return string[]
	 */
	private function getBacklinkAlphabet( string $code ): array {
		$alphabetString = $this->loadCommunityConfig()->Cite_Settings->backlinkAlphabet ??
			$this->config->get( 'CiteDefaultBacklinkAlphabet' ) ?? '';

		$alphabet = preg_split(
			'/\s+/',
			$alphabetString,
			-1,
			PREG_SPLIT_NO_EMPTY
		);

		if ( !$alphabet ) {
			$alphabet = $this->alphabetsProvider->getIndexCharacters( $code ) ?? [];
			$alphabet = array_map( 'mb_strtolower', $alphabet );
		}

		if ( !$alphabet ) {
			$alphabet = range( 'a', 'z' );
		}

		return $alphabet;
	}

	/**
	 * Recursive method to build a mark using an alphabet, repeating symbols to
	 * extend the range like "a…z, aa, ab…"
	 *
	 * @param string[] $symbols List of alphabet characters as strings
	 * @param int $number One-based footnote group index
	 * @param string $result Recursively-constructed output
	 * @return string Caller sees the final result
	 */
	private function buildAlphaLabel( array $symbols, int $number, string $result = '' ): string {
		// Done recursing?
		if ( $number <= 0 ) {
			return $result;
		}
		$radix = count( $symbols );
		// Use a zero-based index as it becomes more convenient for integer
		// modulo and symbol lookup (though not for division!)
		$remainder = ( $number - 1 ) % $radix;
		return $this->buildAlphaLabel(
			$symbols,
			// Subtract so the units value is zero and divide, moving to the next place leftwards.
			( $number - ( $remainder + 1 ) ) / $radix,
			// Prepend current symbol, moving left.
			$symbols[ $remainder ] . $result
		);
	}
}
