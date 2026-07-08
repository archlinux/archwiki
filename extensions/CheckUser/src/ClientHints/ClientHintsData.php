<?php

namespace MediaWiki\Extension\CheckUser\ClientHints;

use JsonSerializable;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Request\WebRequest;
use TypeError;

/**
 * Value object for modeling user agent client hints data.
 */
class ClientHintsData implements JsonSerializable {
	public const HEADER_TO_CLIENT_HINTS_DATA_PROPERTY_NAME = [
		"Sec-CH-UA" => "brands",
		"Sec-CH-UA-Arch" => "architecture",
		"Sec-CH-UA-Bitness" => "bitness",
		"Sec-CH-UA-Form-Factor" => "formFactor",
		"Sec-CH-UA-Full-Version-List" => "fullVersionList",
		"Sec-CH-UA-Mobile" => "mobile",
		"Sec-CH-UA-Model" => "model",
		"Sec-CH-UA-Platform" => "platform",
		"Sec-CH-UA-Platform-Version" => "platformVersion",
		"Sec-CH-UA-WoW64" => "woW64",
		"x-is-browser" => "isBrowser",
		"x-ja3n" => "ja3n",
		"x-ja4h" => "ja4h",
	];

	/**
	 * @param string|null $architecture
	 * @param string|null $bitness
	 * @param string[][]|null $brands
	 * @param string|null $formFactor
	 * @param string[][]|null $fullVersionList
	 * @param bool|null $mobile
	 * @param string|null $model
	 * @param string|null $platform
	 * @param string|null $platformVersion
	 * @param bool|null $woW64
	 * @param int|null $isBrowser
	 * @param string|null $ja3n
	 * @param string|null $ja4h
	 */
	public function __construct(
		private readonly ?string $architecture,
		private readonly ?string $bitness,
		private readonly ?array $brands,
		private readonly ?string $formFactor,
		private readonly ?array $fullVersionList,
		private readonly ?bool $mobile,
		private readonly ?string $model,
		private readonly ?string $platform,
		private readonly ?string $platformVersion,
		private readonly ?bool $woW64,
		private readonly ?int $isBrowser,
		private readonly ?string $ja3n,
		private readonly ?string $ja4h,
	) {
	}

	/**
	 * Given a string of JSON obtained by calling ClientHintsData::jsonSerialize, construct a ClientHintsData
	 * object with the same data.
	 */
	public static function newFromSerialisedJsonArray( array $data ): self {
		return new self(
			$data['architecture'],
			$data['bitness'],
			$data['brands'],
			$data['formFactor'],
			$data['fullVersionList'],
			$data['mobile'],
			$data['model'],
			$data['platform'],
			$data['platformVersion'],
			$data['woW64'],
			$data['isBrowser'] ?? null,
			$data['ja3n'] ?? null,
			$data['ja4h'] ?? null
		);
	}

	/**
	 * Given an array of data received from the client-side JavaScript API for obtaining
	 * user agent client hints, construct a new ClientHintsData object.
	 *
	 * @see UserAgentClientHintsManager::getBodyValidator
	 *
	 * @throws TypeError on invalid data (such as platformVersion being an array).
	 */
	public static function newFromJsApi( array $data ): self {
		// Handle clients sending uaFullVersion in their JS API request (T350316) by adding it to the
		// fullVersionList if the fullVersionList is empty or not defined. If fullVersionList is defined,
		// then the data is almost certainly duplicated in the already defined fullVersionList so ignore it.
		if (
			array_key_exists( 'uaFullVersion', $data ) &&
			(
				!array_key_exists( 'fullVersionList', $data ) ||
				!is_array( $data['fullVersionList'] ) ||
				!count( $data['fullVersionList'] )
			)
		) {
			if ( !array_key_exists( 'fullVersionList', $data ) ) {
				$data['fullVersionList'] = [];
			}
			$data['fullVersionList'][] = $data['uaFullVersion'];
		}
		return new self(
			$data['architecture'] ?? null,
			$data['bitness'] ?? null,
			$data['brands'] ?? null,
			null,
			$data['fullVersionList'] ?? null,
			$data['mobile'] ?? null,
			$data['model'] ?? null,
			$data['platform'] ?? null,
			$data['platformVersion'] ?? null,
			null,
			null,
			null,
			null
		);
	}

	/**
	 * Get a {@link ClientHintsData} object with values fetched from the Client Hints HTTP headers
	 * in the provided $request.
	 *
	 * @param WebRequest $request
	 * @param string[] $collectOnly If not an empty array, only collect data for these Client Hints data attributes
	 * @throws TypeError on invalid data in the Client Hints headers
	 */
	public static function newFromRequestHeaders( WebRequest $request, array $collectOnly = [] ): self {
		$data = [];

		$headers = self::HEADER_TO_CLIENT_HINTS_DATA_PROPERTY_NAME;
		if ( $collectOnly ) {
			$headers = array_filter(
				$headers,
				static fn ( $propertyName ) => in_array( $propertyName, $collectOnly, true )
			);
		}

		foreach ( $headers as $header => $propertyName ) {
			$headerValue = $request->getHeader( $header );
			if ( !$headerValue ) {
				$headerValue = null;
			}
			if ( $headerValue === '?0' ) {
				// Represents the boolean false per
				// https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Sec-CH-UA-Mobile
				$headerValue = false;
			} elseif ( $headerValue === '?1' ) {
				// Represents the boolean true per
				// https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Sec-CH-UA-Mobile
				$headerValue = true;
			} elseif ( $headerValue && in_array( $propertyName, [ 'brands', 'fullVersionList' ] ) ) {
				// See https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Sec-CH-UA for the format of the
				// value for these headers. This expands the string into an array of brands with the brand name
				// and version number separated.
				$headerValue = explode( ',', $headerValue );
				$headerValue = array_map( static function ( $value ) use ( $header ) {
					$explodedValue = explode( ';v=', $value );
					if ( count( $explodedValue ) > 1 ) {
						$brandName = $explodedValue[0];
						$versionNumber = $explodedValue[1];
						return [
							'brand' => trim( $brandName, " \n\r\t\v\0\"" ),
							'version' => trim( $versionNumber, " \n\r\t\v\0\"" ),
						];
					} else {
						// The format does not match the HTTP header, so don't store anything as it's likely fake
						// data.
						throw new TypeError( "Invalid header $header" );
					}
				}, $headerValue );
			} elseif ( $headerValue ) {
				// The header value needs to be trimmed, along with removing the quotation marks that wrap the value.
				$headerValue = trim( $headerValue, " \n\r\t\v\0\"" );
			}

			if ( $propertyName === 'isBrowser' && $headerValue !== null ) {
				$headerValue = intval( $headerValue );
			}

			$data[$propertyName] = $headerValue;
		}
		return new self(
			$data['architecture'] ?? null,
			$data['bitness'] ?? null,
			$data['brands'] ?? null,
			$data['formFactor'] ?? null,
			$data['fullVersionList'] ?? null,
			$data['mobile'] ?? null,
			$data['model'] ?? null,
			$data['platform'] ?? null,
			$data['platformVersion'] ?? null,
			$data['woW64'] ?? null,
			$data['isBrowser'] ?? null,
			$data['ja3n'] ?? null,
			$data['ja4h'] ?? null
		);
	}

	/**
	 * Given an array of rows from the useragent_clienthints table,
	 * construct a new ClientHintsData object.
	 *
	 * @param array<array{uach_name: string, uach_value: string}> $rows
	 */
	public static function newFromDatabaseRows( array $rows ): self {
		$data = [];
		foreach ( $rows as $row ) {
			if ( in_array( $row['uach_name'], [ 'brands', 'fullVersionList' ] ) ) {
				// There can be multiple client hint values with this name
				// for brands and fullVersionList
				if ( !array_key_exists( $row['uach_name'], $data ) ) {
					$data[$row['uach_name']] = [];
				}
				// Assume that last space separates version number from brand name (e.g. "NotABrand 123")
				// When saving to the DB, we combine the version number and brand name
				// with a separator of a space.
				$explodedValue = explode( ' ', $row['uach_value'] );
				if ( count( $explodedValue ) > 1 ) {
					$versionNumber = array_pop( $explodedValue );
					$brandName = implode( ' ', $explodedValue );
					$data[$row['uach_name']][] = [
						"brand" => $brandName,
						"version" => $versionNumber,
					];
				} else {
					// No space was found, therefore keep the value as is.
					$data[$row['uach_name']][] = $row['uach_value'];
				}
			} else {
				$value = $row['uach_value'];
				// Convert "0" and "1" to their boolean values
				// for "mobile" and "woW64"
				if ( in_array( $row['uach_name'], [ 'mobile', 'woW64' ] ) ) {
					$value = boolval( $value );
				}
				$data[$row['uach_name']] = $value;
			}
		}
		return new self(
			$data['architecture'] ?? null,
			$data['bitness'] ?? null,
			$data['brands'] ?? null,
			$data['formFactor'] ?? null,
			$data['fullVersionList'] ?? null,
			$data['mobile'] ?? null,
			$data['model'] ?? null,
			$data['platform'] ?? null,
			$data['platformVersion'] ?? null,
			$data['woW64'] ?? null,
			$data['isBrowser'] ?? null,
			$data['ja3n'] ?? null,
			$data['ja4h'] ?? null
		);
	}

	/**
	 * @return array[]
	 *  An array of arrays containing maps of uach_name => uach_value items
	 *  to insert into the cu_useragent_clienthints table.
	 */
	public function toDatabaseRows(): array {
		$rows = [];
		foreach ( $this->jsonSerialize() as $key => $value ) {
			if ( !is_array( $value ) ) {
				if ( $value === "" || $value === null ) {
					continue;
				}
				if ( is_bool( $value ) ) {
					$value = $value ? "1" : "0";
				}
				$value = trim( (string)$value );
				$rows[] = [ 'uach_name' => $key, 'uach_value' => $value ];
			} else {
				// Some values are arrays, for example:
				//  [
				//    "brand": "Not.A/Brand",
				//    "version": "8"
				//  ],
				// We transform these by joining brand/version with a space, e.g. "Not.A/Brand 8"
				$itemsAsString = [];
				foreach ( $value as $item ) {
					if ( is_array( $item ) ) {
						// Sort so "brand" is always first and then "version".
						ksort( $item );
						// Trim the data to remove leading and trailing spaces.
						$item = array_map( trim( ... ), $item );
						// Convert arrays to a string by imploding
						$itemsAsString[] = implode( ' ', $item );
					} elseif ( is_string( $item ) || is_numeric( $item ) ) {
						// Allow integers, floats and strings to be stored
						// as their string representation.
						//
						// Trim the data to remove leading and trailing spaces.
						$item = strval( $item );
						$itemsAsString[] = trim( $item );
					}
				}
				// Remove any duplicates
				$itemsAsString = array_unique( $itemsAsString );
				// Limit to 10 maximum items
				if ( count( $itemsAsString ) > 10 ) {
					LoggerFactory::getInstance( 'CheckUser' )->info(
						"ClientHintsData object has too many items in array for {key}. " .
						"Truncated to 10 items.",
						[ $key ]
					);
					// array_splice modifies the array in place, by taking the array
					// as the first argument via reference. The return value is
					// the elements that were "extracted", which in this case are
					// the items to be ignored.
					array_splice( $itemsAsString, 10 );
				}
				// Now convert to DB rows
				foreach ( $itemsAsString as $item ) {
					$rows[] = [
						'uach_name' => $key,
						'uach_value' => $item,
					];
				}
			}
		}
		return $rows;
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		return [
			'architecture' => $this->architecture,
			'bitness' => $this->bitness,
			'brands' => $this->brands,
			'formFactor' => $this->formFactor,
			'fullVersionList' => $this->fullVersionList,
			'mobile' => $this->mobile,
			'model' => $this->model,
			'platform' => $this->platform,
			'platformVersion' => $this->platformVersion,
			'woW64' => $this->woW64,
			'isBrowser' => $this->isBrowser,
			'ja3n' => $this->ja3n,
			'ja4h' => $this->ja4h,
		];
	}
}
