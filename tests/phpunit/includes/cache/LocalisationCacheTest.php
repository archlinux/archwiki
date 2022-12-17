<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Languages\LanguageNameUtils;
use Psr\Log\NullLogger;

/**
 * @group Database
 * @group Cache
 * @covers LocalisationCache
 * @author Niklas Laxström
 */
class LocalisationCacheTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param array $hooks Hook overrides
	 * @return LocalisationCache
	 */
	protected function getMockLocalisationCache( $hooks = [] ) {
		global $IP;

		$mockLangNameUtils = $this->createNoOpMock( LanguageNameUtils::class,
			[ 'isValidBuiltInCode', 'isSupportedLanguage', 'getMessagesFileName' ] );
		$mockLangNameUtils->method( 'isValidBuiltInCode' )->willReturnCallback(
			static function ( $code ) {
				// Copy-paste, but it's only one line
				return (bool)preg_match( '/^[a-z0-9-]{2,}$/', $code );
			}
		);
		$mockLangNameUtils->method( 'isSupportedLanguage' )->willReturnCallback(
			static function ( $code ) {
				return in_array( $code, [
					'ar',
					'arz',
					'ba',
					'de',
					'en',
					'ksh',
					'ru',
				] );
			}
		);
		$mockLangNameUtils->method( 'getMessagesFileName' )->willReturnCallback(
			static function ( $code ) {
				global $IP;
				$code = str_replace( '-', '_', ucfirst( $code ) );
				return "$IP/languages/messages/Messages$code.php";
			}
		);

		$hookContainer = $this->createHookContainer( $hooks );

		$lc = $this->getMockBuilder( LocalisationCache::class )
			->setConstructorArgs( [
				new ServiceOptions( LocalisationCache::CONSTRUCTOR_OPTIONS, [
					'forceRecache' => false,
					'manualRecache' => false,
					'ExtensionMessagesFiles' => [],
					'MessagesDirs' => [],
				] ),
				new LCStoreDB( [] ),
				new NullLogger,
				[],
				$mockLangNameUtils,
				$hookContainer
			] )
			->onlyMethods( [ 'getMessagesDirs' ] )
			->getMock();
		$lc->method( 'getMessagesDirs' )
			->willReturn( [ "$IP/tests/phpunit/data/localisationcache" ] );

		return $lc;
	}

	public function testPluralRulesFallback() {
		$cache = $this->getMockLocalisationCache();

		$this->assertEquals(
			$cache->getItem( 'ar', 'pluralRules' ),
			$cache->getItem( 'arz', 'pluralRules' ),
			'arz plural rules (undefined) fallback to ar (defined)'
		);

		$this->assertEquals(
			$cache->getItem( 'ar', 'compiledPluralRules' ),
			$cache->getItem( 'arz', 'compiledPluralRules' ),
			'arz compiled plural rules (undefined) fallback to ar (defined)'
		);

		$this->assertNotEquals(
			$cache->getItem( 'ksh', 'pluralRules' ),
			$cache->getItem( 'de', 'pluralRules' ),
			'ksh plural rules (defined) dont fallback to de (defined)'
		);

		$this->assertNotEquals(
			$cache->getItem( 'ksh', 'compiledPluralRules' ),
			$cache->getItem( 'de', 'compiledPluralRules' ),
			'ksh compiled plural rules (defined) dont fallback to de (defined)'
		);
	}

	public function testRecacheFallbacks() {
		$lc = $this->getMockLocalisationCache();
		$lc->recache( 'ba' );
		$this->assertEquals(
			[
				'present-ba' => 'ba',
				'present-ru' => 'ru',
				'present-en' => 'en',
			],
			$lc->getItem( 'ba', 'messages' ),
			'Fallbacks are only used to fill missing data'
		);
	}

	public function testRecacheFallbacksWithHooks() {
		// Use hook to provide updates for messages. This is what the
		// LocalisationUpdate extension does. See T70781.

		$lc = $this->getMockLocalisationCache( [
			'LocalisationCacheRecacheFallback' => [
				static function (
					LocalisationCache $lc,
					$code,
					array &$cache
				) {
					if ( $code === 'ru' ) {
						$cache['messages']['present-ba'] = 'ru-override';
						$cache['messages']['present-ru'] = 'ru-override';
						$cache['messages']['present-en'] = 'ru-override';
					}
				}
			]
		] );
		$lc->recache( 'ba' );
		$this->assertEquals(
			[
				'present-ba' => 'ba',
				'present-ru' => 'ru-override',
				'present-en' => 'ru-override',
			],
			$lc->getItem( 'ba', 'messages' ),
			'Updates provided by hooks follow the normal fallback order.'
		);
	}
}
