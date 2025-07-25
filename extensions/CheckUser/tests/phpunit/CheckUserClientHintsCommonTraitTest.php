<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\ClientHints\ClientHintsData;

/**
 * A helper trait used by classes that need to get an example ClientHintsData object,
 * an example JS API response, and/or assert that two ClientHintsData objects are
 * equal.
 */
trait CheckUserClientHintsCommonTraitTest {
	/**
	 * Generates example Client Hints data in a format
	 * that would be sent as the request body to the
	 * Client Hints REST API.
	 *
	 * @param string|null $architecture
	 * @param string|null $bitness
	 * @param array|null $brands Provide null to use the default. Provide an empty array for no data.
	 * @param array|null $fullVersionList Provide null to use the default. Provide an empty array for no data.
	 * @param bool|null $mobile
	 * @param string|null $model
	 * @param string|null $platform
	 * @param string|null $platformVersion
	 * @return array Data that can be passed to ClientHintsData::newFromJsApi
	 */
	public static function getExampleClientHintsJsApiResponse(
		?string $architecture = "x86",
		?string $bitness = "64",
		?array $brands = null,
		?array $fullVersionList = null,
		?bool $mobile = false,
		?string $model = "",
		?string $platform = "Windows",
		?string $platformVersion = "15.0.0"
	): array {
		if ( $brands === null ) {
			$brands = [
				[
					"brand" => "Not.A/Brand",
					"version" => "8"
				],
				[
					"brand" => "Chromium",
					"version" => "114"
				],
				[
					"brand" => "Google Chrome",
					"version" => "114"
				]
			];
		}
		if ( $fullVersionList === null ) {
			$fullVersionList = [
				[
					"brand" => "Not.A/Brand",
					"version" => "8.0.0.0"
				],
				[
					"brand" => "Chromium",
					"version" => "114.0.5735.199"
				],
				[
					"brand" => "Google Chrome",
					"version" => "114.0.5735.199"
				]
			];
		}
		return [
			"architecture" => $architecture,
			"bitness" => $bitness,
			"brands" => $brands,
			"fullVersionList" => $fullVersionList,
			"mobile" => $mobile,
			"model" => $model,
			"platform" => $platform,
			"platformVersion" => $platformVersion
		];
	}

	/**
	 * Gets an example ClientHintsData object with example data that is
	 * passed through the ClientHintsData::newFromJsApi method.
	 *
	 * @param string|null $architecture
	 * @param string|null $bitness
	 * @param array|null $brands Provide null to use the default. Provide an empty array for no data.
	 * @param array|null $fullVersionList Provide null to use the default. Provide an empty array for no data.
	 * @param bool|null $mobile
	 * @param string|null $model
	 * @param string|null $platform
	 * @param string|null $platformVersion
	 * @return ClientHintsData
	 */
	public static function getExampleClientHintsDataObjectFromJsApi(
		?string $architecture = "x86",
		?string $bitness = "64",
		?array $brands = null,
		?array $fullVersionList = null,
		?bool $mobile = false,
		?string $model = "",
		?string $platform = "Windows",
		?string $platformVersion = "15.0.0"
	): ClientHintsData {
		return ClientHintsData::newFromJsApi(
			self::getExampleClientHintsJsApiResponse(
				$architecture,
				$bitness,
				$brands,
				$fullVersionList,
				$mobile,
				$model,
				$platform,
				$platformVersion
			)
		);
	}

	/**
	 * Asserts if two ClientHintsData objects are equal.
	 *
	 * @param ClientHintsData $clientHintsData The first ClientHintsData object in the comparison
	 * @param ClientHintsData $other The second ClientHintsData object in the comparison
	 * @param bool $otherFromDb Whether the $other ClientHintsData object was created via ::newFromDatabaseRows.
	 */
	public function assertClientHintsDataObjectsEqual(
		ClientHintsData $clientHintsData,
		ClientHintsData $other,
		bool $otherFromDb = false
	): void {
		$jsonSerializedData = $other->jsonSerialize();
		foreach ( $clientHintsData->jsonSerialize() as $name => $value ) {
			if ( $otherFromDb ) {
				if ( $value === "" ) {
					// Storage into the DB converts $value to null
					$value = null;
				} elseif ( is_string( $value ) ) {
					// Storage into the DB trims trailing and leading spaces from strings.
					$value = trim( $value );
				}
			}
			if ( in_array( $name, [ 'brands', 'fullVersionList' ] ) ) {
				if ( $jsonSerializedData[$name] === null && $value === null ) {
					// If both are null, then they are equal.
					continue;
				}
				// Key sort the second level dimensional arrays of $value
				// to make comparison easier.
				foreach ( $value as &$item ) {
					if ( is_array( $item ) ) {
						foreach ( $item as &$subItem ) {
							if ( $otherFromDb && is_string( $subItem ) ) {
								// Storage into the DB trims trailing and leading spaces from strings.
								$subItem = trim( $subItem );
							}
						}
						ksort( $item );
					} elseif ( $otherFromDb && is_string( $item ) ) {
						// Storage into the DB trims trailing and leading spaces from strings.
						$item = trim( $item );
					}
				}
				// Key sort the second level dimensional arrays of $jsonSerializedData[$name]
				// to make comparison easier.
				foreach ( $jsonSerializedData[$name] as &$datum ) {
					if ( is_array( $datum ) ) {
						ksort( $datum );
					}
				}
				// Apply de-duplication to the $clientHintsData ClientHintsData object
				// if the $other came from the DB.
				if ( $otherFromDb && is_array( $value ) ) {
					$value = array_unique( $value, SORT_REGULAR );
				}
				$this->assertArrayEquals(
					$value,
					$jsonSerializedData[$name],
					false,
					false,
					"The value of '$name' was not as expected."
				);
			} else {
				$this->assertSame(
					$value,
					$jsonSerializedData[$name],
					"The value of '$name' was not as expected."
				);
			}
		}
	}
}
