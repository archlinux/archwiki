<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\Extension\CheckUser\Tests\CheckUserClientHintsCommonTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @group CheckUser
 *
 * @covers \MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsFormatter
 */
class UserAgentClientHintsFormatterTest extends MediaWikiIntegrationTestCase {
	use CheckUserClientHintsCommonTestTrait;

	/** @dataProvider provideFormatClientHintsDataObject */
	public function testFormatClientHintsDataObject( $clientHintsData, $expectedFormattedString ) {
		$this->overrideConfigValues( [
			'CheckUserClientHintsForDisplay' => [
				"model",
				"fullVersionList",
				"platformVersion",
				"platform",
				"userAgent",
				"brands",
				"formFactor",
				"architecture",
				"mobile",
				"bitness",
				"woW64",
				"isBrowser",
				"ja3n",
				"ja4h",
			],
			'CheckUserClientHintsValuesToHide' => [
				"architecture" => [ "x86" ],
				"bitness" => [ "64" ],
				"woW64" => [ false ],
			],
		] );
		/** @var UserAgentClientHintsFormatter $objectUnderTest */
		$objectUnderTest = $this->getServiceContainer()->get( 'UserAgentClientHintsFormatter' );
		$this->assertSame(
			$expectedFormattedString,
			$objectUnderTest->formatClientHintsDataObject( $clientHintsData ),
			'Returned string from ::formatClientHintsDataObject was not as expected.'
		);
	}

	public static function provideFormatClientHintsDataObject() {
		return [
			'Empty Client Hints data object' => [
				ClientHintsData::newFromJsApi( [] ),
				'',
			],
			'Example Client Hints data object for Windows PC using Chrome' => [
				new ClientHintsData(
					"x86",
					"64",
					[
						[ "brand" => "Not.A/Brand", "version" => "99" ],
						[ "brand" => "Google Chrome", "version" => "115" ],
						[ "brand" => "Chromium", "version" => "115" ],
					],
					null,
					[
						[ "brand" => "Not.A/Brand", "version" => "99.0.0.0" ],
						[ "brand" => "Google Chrome", "version" => "115.0.5790.171" ],
						[ "brand" => "Chromium", "version" => "115.0.5790.171" ],
					],
					false,
					"",
					"Windows",
					"15.0.0",
					false,
					null,
					null,
					null
				),
				'Brand: Not.A/Brand 99.0.0.0, Brand: Google Chrome 115.0.5790.171, Brand: Chromium 115.0.5790.171, ' .
				'Platform: Windows 15.0.0, Mobile: No',
			],
			'Example Client Hints data object for Mobile using Chrome with other headers' => [
				new ClientHintsData(
					"",
					"32",
					[
						[ "brand" => "Not/A)Brand", "version" => "99" ],
						[ "brand" => "Google Chrome", "version" => "115" ],
						[ "brand" => "Chromium", "version" => "115" ],
					],
					null,
					[
						[ "brand" => "Not/A)Brand", "version" => "99.0.0.0" ],
						[ "brand" => "Google Chrome", "version" => "115.0.5790.171" ],
						[ "brand" => "Chromium", "version" => "115.0.5790.171" ],
					],
					true,
					"SM-G965U",
					"Android",
					"10.0.0",
					false,
					30,
					'abc',
					'def'
				),
				'Model: SM-G965U, Brand: Not/A)Brand 99.0.0.0, Brand: Google Chrome 115.0.5790.171, Brand: ' .
				'Chromium 115.0.5790.171, Platform: Android 10.0.0, Mobile: Yes, Bitness: 32, ' .
				'x-is-browser: Indeterminate, x-ja3n: abc, x-ja4h: def',
			],
			'x-is-browser is less than 20' => [
				new ClientHintsData(
					"",
					null,
					[],
					null,
					[],
					true,
					null,
					null,
					null,
					false,
					18,
					'abc',
					null
				),
				'Mobile: Yes, x-is-browser: Likely bot, x-ja3n: abc',
			],
			'x-is-browser is greater than 80' => [
				new ClientHintsData(
					"",
					null,
					[],
					null,
					[],
					true,
					null,
					null,
					null,
					false,
					84,
					null,
					null
				),
				'Mobile: Yes, x-is-browser: Likely browser',
			],
		];
	}

	/** @dataProvider provideGenerateMsgCache */
	public function testGenerateMsgCache( $expectedMessageKeys ) {
		/** @var UserAgentClientHintsFormatter $objectUnderTest */
		$objectUnderTest = $this->getServiceContainer()->get( 'UserAgentClientHintsFormatter' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		// ::generateMsgCache should be called in the constructor for the service, so no need to call it again.
		$this->assertArrayEquals(
			$expectedMessageKeys,
			array_keys( $objectUnderTest->msgCache ),
			false,
			false,
			'::generateMsgCache has not generated the msgCache property with the expected array keys.'
		);
		foreach ( $expectedMessageKeys as $key ) {
			$this->assertSame(
				wfMessage( $key )->escaped(),
				$objectUnderTest->msgCache[$key],
				"::generateMsgCache did not cache the correct text for message with key '$key'."
			);
		}
	}

	public static function provideGenerateMsgCache() {
		return [
			'All message keys to be cached' => [ [
				'checkuser-clienthints-value-yes', 'checkuser-clienthints-value-no',
				...array_unique( array_values( UserAgentClientHintsFormatter::NAME_TO_MESSAGE_KEY ) ),
			] ],
		];
	}
}
