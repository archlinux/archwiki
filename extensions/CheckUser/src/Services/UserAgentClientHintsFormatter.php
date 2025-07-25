<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\ClientHints\ClientHintsBatchFormatterResults;
use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\ClientHints\ClientHintsLookupResults;
use MediaWiki\Config\ServiceOptions;
use MessageLocalizer;

/**
 * A service that formats ClientHintsData objects into a human-readable
 * string format.
 */
class UserAgentClientHintsFormatter {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserClientHintsForDisplay',
		'CheckUserClientHintsValuesToHide'
	];

	public const NAME_TO_MESSAGE_KEY = [
		"userAgent" => "checkuser-clienthints-name-brand",
		"architecture" => "checkuser-clienthints-name-architecture",
		"bitness" => "checkuser-clienthints-name-bitness",
		"brands" => "checkuser-clienthints-name-brand",
		"formFactor" => "checkuser-clienthints-name-form-factor",
		"fullVersionList" => "checkuser-clienthints-name-brand",
		"mobile" => "checkuser-clienthints-name-mobile",
		"model" => "checkuser-clienthints-name-model",
		"platform" => "checkuser-clienthints-name-platform",
		"platformVersion" => "checkuser-clienthints-name-platform-version",
		"woW64" => "checkuser-clienthints-name-wow64"
	];

	private MessageLocalizer $messageLocalizer;
	private ServiceOptions $options;

	private array $msgCache;

	public function __construct(
		MessageLocalizer $messageLocalizer,
		ServiceOptions $options
	) {
		$this->messageLocalizer = $messageLocalizer;
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->generateMsgCache();
	}

	/**
	 * Generates a cache of messages that are used that also take no
	 * parameters so that they do not need to be re-calculated each time.
	 *
	 * @return void
	 */
	private function generateMsgCache(): void {
		foreach ( self::NAME_TO_MESSAGE_KEY as $msg ) {
			$this->msgCache[$msg] = $this->messageLocalizer->msg( $msg )->escaped();
		}
		$this->msgCache['checkuser-clienthints-value-yes'] = $this->messageLocalizer
			->msg( 'checkuser-clienthints-value-yes' )->escaped();
		$this->msgCache['checkuser-clienthints-value-no'] = $this->messageLocalizer
			->msg( 'checkuser-clienthints-value-no' )->escaped();
	}

	/**
	 * Batch formats ClientHintsData objects associated with reference IDs by
	 * taking the result from UserAgentClientHintsLookup::getClientHintsByReferenceIds
	 * and converting the ClientHintsData objects into human-readable strings.
	 *
	 * @param ClientHintsLookupResults $clientHintsLookupResults
	 * @return ClientHintsBatchFormatterResults
	 */
	public function batchFormatClientHintsData(
		ClientHintsLookupResults $clientHintsLookupResults
	): ClientHintsBatchFormatterResults {
		[ $referenceIdsToClientHintsDataIndex, $clientHintsDataObjects ] = $clientHintsLookupResults->getRawData();
		foreach ( $clientHintsDataObjects as &$clientHintsDataObject ) {
			// This "batches" the formatting as it only does it once per unique combination of
			// ClientHintsData object instead of passing the same object to ::formatClientHintsDataObject
			// multiple times.
			$clientHintsDataObject = $this->formatClientHintsDataObject( $clientHintsDataObject );
		}
		return new ClientHintsBatchFormatterResults( $referenceIdsToClientHintsDataIndex, $clientHintsDataObjects );
	}

	/**
	 * @param ClientHintsData $clientHintsData
	 * @return string
	 */
	public function formatClientHintsDataObject( ClientHintsData $clientHintsData ): string {
		$clientHintsForDisplay = $this->options->get( 'CheckUserClientHintsForDisplay' );
		// Combine Client Hints data where possible to reduce the length of the generated string.
		$dataAsArray = $this->combineClientHintsData( $clientHintsData->jsonSerialize(), $clientHintsForDisplay );
		// Get all the Client Hints data as their human-readable string representations
		// in an array for later combination.
		$dataAsStringArray = [];
		foreach ( $clientHintsForDisplay as $clientHintName ) {
			if ( array_key_exists( $clientHintName, $dataAsArray ) ) {
				// If the Client Hint name is configured for display and is set in the $dataAsArray array
				// of Client Hints data, then add it to the $dataAsStringArray after conversion to a string.
				if ( in_array( $clientHintName, [ 'brands', 'fullVersionList' ] ) ) {
					// If the Client Hint name is 'brands' or 'fullVersionList', then the value will be
					// an array of items. Therefore add each brand as a new item to the $dataAsStringArray array.
					if ( $dataAsArray[$clientHintName] !== null ) {
						foreach ( $dataAsArray[$clientHintName] as $key => $brand ) {
							// Get the brand as string using ::getBrandAsString, and if it returns a string
							// that isn't empty then add it to $dataAsStringArray
							$brandAsString = $this->getBrandAsString( $brand, false );
							if ( $brandAsString ) {
								$dataAsStringArray[$clientHintName . '-' . $key] = $this->generateClientHintsListItem(
									$clientHintName, $brandAsString
								);
							}
						}
					}
				} else {
					$dataAsStringArray[$clientHintName] = $this->generateClientHintsListItem(
						$clientHintName, $dataAsArray[$clientHintName]
					);
				}
			}
		}
		// Remove items that equate to false (in this case this should be empty strings).
		$dataAsStringArray = array_filter( $dataAsStringArray );
		return $this->listToTextWithoutAnd( $dataAsStringArray );
	}

	/**
	 * Functionally similar to Language::listToText, but
	 * does not separate the last items with an "and" and instead
	 * uses another comma.
	 *
	 * This is done as the Language::listToText adding the "and"
	 * message makes the separation between the second to last
	 * Client Hints value and the last Client Hints name unclear.
	 *
	 * @param string[] $list
	 * @param-taint $list tainted
	 * @return string
	 */
	private function listToTextWithoutAnd( array $list ): string {
		$itemCount = count( $list );
		if ( $itemCount < 1 ) {
			return '';
		}
		$comma = $this->messageLocalizer->msg( 'comma-separator' )->escaped();
		return implode( $comma, $list );
	}

	/**
	 * Combines the Client Hints data given in the $dataAsArray parameter
	 * that is being configured to be displayed by $clientHintsForDisplay:
	 *  * The "platform" and "platformVersion" items are combined into "platform" if
	 *    both are to be displayed and both have values.
	 *  * Items in the "brands" array are removed if an item exists in the "fullVersionList"
	 *    array that has the same brand name and significant version number.
	 *
	 * @param array $dataAsArray The Client Hints data from ClientHintsData::jsonSerialize.
	 * @param array &$clientHintsForDisplay The value of the 'CheckUserClientHintsForDisplay' in a
	 *   variable that can be modified without modifying that config. This is passed by reference.
	 * @return array
	 */
	private function combineClientHintsData( array $dataAsArray, array &$clientHintsForDisplay ): array {
		if (
			in_array( 'platform', $clientHintsForDisplay ) &&
			in_array( 'platformVersion', $clientHintsForDisplay ) &&
			( $dataAsArray['platform'] ?? false ) &&
			( $dataAsArray['platformVersion'] ?? false )
		) {
			// Combine "platform" and "platformVersion" if both are set and are configured to be displayed.
			// When combining them use a hardcoded space so be consistent with "brands" and "fullVersionList".
			$dataAsArray["platform"] = $dataAsArray["platform"] . ' ' . $dataAsArray["platformVersion"];
			unset( $dataAsArray["platformVersion"] );
			// Update $clientHintsForDisplay to be have "platform" to the position of "platformVersion" if
			// that was ordered closer to the start of $clientHintsForDisplay
			$platformKey = array_search( 'platform', $clientHintsForDisplay );
			$platformVersionKey = array_search( 'platformVersion', $clientHintsForDisplay );
			if ( $platformVersionKey < $platformKey ) {
				// Move "platform" via array_splice calls to be the item before "platformVersion" if
				// "platformVersion" has a smaller integer key.
				array_splice( $clientHintsForDisplay, $platformKey, 1 );
				array_splice( $clientHintsForDisplay, $platformVersionKey, 0, 'platform' );
			}
		}
		// Remove "brands" items if a entry for that brand name also exists in "fullVersionList"
		// and both "brands" and "fullVersionList" are configured for display.
		if (
			in_array( 'brands', $clientHintsForDisplay ) &&
			in_array( 'fullVersionList', $clientHintsForDisplay ) &&
			( $dataAsArray['brands'] ?? false ) &&
			( $dataAsArray['fullVersionList'] ?? false )
		) {
			// Get the items in 'brands' as strings for comparison
			$brandsAsString = array_map( function ( $item ) {
				return $this->getBrandAsString( $item, false );
			}, $dataAsArray['brands'] );
			// Remove brands that were not parsable.
			$brandsAsString = array_filter( $brandsAsString );
			foreach ( $dataAsArray["fullVersionList"] as $fullVersionBrand ) {
				// If the 'fullVersionList' brand name with only the significant version number
				// exactly matches an item in the 'brands' array, then remove that item in the
				// 'brands' array as it duplicates the one in 'fullVersionList'.
				$fullVersionBrandWithOnlySignificantVersion = $this->getBrandAsString( $fullVersionBrand, true );
				$matchingBrandKey = array_search( $fullVersionBrandWithOnlySignificantVersion, $brandsAsString );
				if ( $matchingBrandKey !== false ) {
					unset( $dataAsArray['brands'][$matchingBrandKey] );
					unset( $brandsAsString[$matchingBrandKey] );
				}
			}
			// Reset the key numbering after some values may have been unset
			// above.
			$dataAsArray['brands'] = array_values( $dataAsArray['brands'] );
		}
		return $dataAsArray;
	}

	/**
	 * Generates a string item for the Client Hints string list returned by
	 * ::formatClientHintsDataObject. This adds the translated name and the
	 * value using the 'checkuser-clienthints-list-item' message.
	 *
	 * This method does not check if the $clientHintName is configured for
	 * display, but does check if the $clientHintValue is to be hidden.
	 *
	 * @param string $clientHintName The name of the Client Hints name-value pair, where the name is one of the
	 *   array keys returned by ClientHintsData::jsonSerialize
	 * @param string|bool|null $clientHintValue The value for the Client Hints name-value pair. If this is a boolean,
	 *   then "true" and "false" are converted to the messages "checkuser-clienthints-value-yes" and
	 *   "checkuser-clienthints-value-no" respectively. If this is null, then an empty string is returned.
	 * @return string
	 */
	private function generateClientHintsListItem( string $clientHintName, $clientHintValue ) {
		// Return the empty string for a null value or an falsey string value.
		if (
			$clientHintValue === null ||
			( is_string( $clientHintValue ) && !$clientHintValue )
		) {
			return '';
		}
		$clientHintsValuesToHide = $this->options->get( 'CheckUserClientHintsValuesToHide' );
		// Return an empty string if the item is configured to be hidden.
		if (
			array_key_exists( $clientHintName, $clientHintsValuesToHide ) &&
			in_array( $clientHintValue, $clientHintsValuesToHide[$clientHintName] )
		) {
			return '';
		}
		// If the item is a boolean, convert the value to the translated version of
		// "Yes" for true and "No" for false.
		if ( is_bool( $clientHintValue ) ) {
			if ( $clientHintValue ) {
				$clientHintValue = $this->msgCache['checkuser-clienthints-value-yes'];
			} else {
				$clientHintValue = $this->msgCache['checkuser-clienthints-value-no'];
			}
		} else {
			// If the item is a string, then trim leading and ending spaces
			// as some wikis may have uach_value items with trailing or leading spaces.
			// This would not be necessary if T345837 is implemented. This is currently
			// needed because of untrimmed uach_value items in the database as detailed
			// in T345649.
			$clientHintValue = trim( $clientHintValue );
		}
		// Return the Client Hints name-value pair using the "checkuser-clienthints-list-item" message
		// to combine the name and value.
		return $this->messageLocalizer->msg( 'checkuser-clienthints-list-item' )
			->rawParams( $this->msgCache[self::NAME_TO_MESSAGE_KEY[$clientHintName]] )
			->params( $clientHintValue )
			->escaped();
	}

	/**
	 * Gets the string version of an array or string in the 'brands' and 'fullVersionList' arrays.
	 * With the version optionally cut to only the significant number (the number before the first dot).
	 *
	 * @param mixed $item An array item from the 'brands' or 'fullVersionList' array.
	 * @param bool $significantOnly If an array, then attempt to make the version number only have
	 *   the significant number.
	 * @return string|null If $item is an array then the imploded array. if $item is string then $item. Otherwise null.
	 */
	private function getBrandAsString( $item, bool $significantOnly ): ?string {
		if ( is_array( $item ) ) {
			ksort( $item );
			if ( $significantOnly && count( $item ) > 1 ) {
				// Remove the non-significant numbers from the version number if $significantOnly is set.
				if ( array_key_exists( 'version', $item ) ) {
					// If the 'version' key is set, then use this.
					if ( strpos( $item['version'], '.' ) ) {
						// If there is a point, then remove all text after the . in the 'version'.
						$item['version'] = substr( $item['version'], 0, strpos( $item['version'], '.' ) );
					}
				}
			}
			// Implode the array into a string to get the brand as a string.
			// The code above will have handled the removal of non-significant
			// version numbers if this was requested and was also possible.
			return implode( ' ', $item );
		} elseif ( is_string( $item ) ) {
			return $item;
		}
		// If the item is neither a string or array, then
		// just return null as there isn't going to be a
		// useful way to display the brand as a string in this case.
		return null;
	}
}
