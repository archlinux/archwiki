<?php

namespace MediaWiki\Tests\MWParsoid\Config;

use HashConfig;
use ILanguageConverter;
use Language;
use MagicWord;
use MagicWordArray;
use MagicWordFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiUnitTestCase;
use MessageCache;
use MWException;
use MWParsoid\Config\SiteConfig;
use NamespaceInfo;
use NullStatsdDataFactory;
use Parser;
use UnexpectedValueException;
use Wikimedia\TestingAccessWrapper;
use ZhConverter;

/**
 * @covers \MWParsoid\Config\SiteConfig
 * @package MediaWiki\Tests\MWParsoid\Config
 * TODO: these tests belong in MediaWiki once the config is moved.
 */
class SiteConfigTest extends MediaWikiUnitTestCase {

	private const DEFAULT_CONFIG = [
		'GalleryOptions' => [],
		'AllowExternalImages' => false,
		'AllowExternalImagesFrom' => '',
		'Server' => 'localhost',
		'ArticlePath' => false,
		'InterwikiMagic' => true,
		'ExtraInterlanguageLinkPrefixes' => [],
		'LocalInterwikis' => [],
		'LanguageCode' => 'qqq',
		'DisableLangConversion' => false,
		'NamespaceAliases' => [],
		'UrlProtocols' => [ 'http://' ],
		'Script' => false,
		'ScriptPath' => '/wiki',
		'LoadScript' => false,
		'LocalTZoffset' => null,
		'ThumbLimits' => [ 4242 ],
		'MaxTemplateDepth' => 42,
		'LegalTitleChars' => 'abc'
	];

	private function createMockOrOverride( string $class, array $overrides ) {
		if ( array_key_exists( $class, $overrides ) ) {
			return $overrides[$class];
		}
		return $this->createNoOpMock( $class );
	}

	/**
	 * TODO it might save code to have this helper always return a
	 * TestingAccessWrapper?
	 *
	 * @param array $configOverrides Configuration options overriding default ServiceOptions config defined in
	 *                               DEFAULT_CONFIG above.
	 * @param array $parsoidSettings
	 * @param array $serviceOverrides
	 *
	 * @return SiteConfig
	 */
	private function createSiteConfig(
		array $configOverrides = [],
		array $parsoidSettings = [],
		array $serviceOverrides = []
	): SiteConfig {
		return new SiteConfig(
			new ServiceOptions(
				SiteConfig::CONSTRUCTOR_OPTIONS,
				array_replace( self::DEFAULT_CONFIG, $configOverrides )
			),
			$parsoidSettings,
			$this->createSimpleObjectFactory(),
			$this->createMockOrOverride( Language::class, $serviceOverrides ),
			new NullStatsdDataFactory(),
			$this->createMockOrOverride( MagicWordFactory::class, $serviceOverrides ),
			$this->createMockOrOverride( NamespaceInfo::class, $serviceOverrides ),
			$this->createMockOrOverride( SpecialPageFactory::class, $serviceOverrides ),
			$this->createMockOrOverride( InterwikiLookup::class, $serviceOverrides ),
			$this->createMockOrOverride( UserOptionsLookup::class, $serviceOverrides ),
			$this->createMockOrOverride( LanguageFactory::class, $serviceOverrides ),
			$this->createMockOrOverride( LanguageConverterFactory::class, $serviceOverrides ),
			$this->createMockOrOverride( LanguageNameUtils::class, $serviceOverrides ),
			$this->createMockOrOverride( Parser::class, $serviceOverrides ),
			new HashConfig( $configOverrides )
		);
	}

	public function provideConfigParameterPassed(): iterable {
		yield 'galleryOptions' => [
			[ 'GalleryOptions' => [ 'blabla' ] ],
			'galleryOptions',
			[ 'blabla' ]
		];
		yield 'allowedExternalImagePrefixes, false' => [
			[ 'AllowExternalImages' => true ],
			'allowedExternalImagePrefixes',
			[ '' ]
		];
		yield 'allowedExternalImagePrefixes, true' => [
			[
				'AllowExternalImages' => false,
				'AllowExternalImagesFrom' => [ 'blabla' ]
			],
			'allowedExternalImagePrefixes',
			[ 'blabla' ]
		];
		yield 'interwikiMagic' => [
			[ 'InterwikiMagic' => true ],
			'interwikiMagic',
			true
		];
		yield 'lang' => [
			[ 'LanguageCode' => 'qqx' ],
			'lang',
			'qqx'
		];
		// This is a setting from Cite extension
		yield 'responsiveReferences, absent' => [
			[],
			'responsiveReferences',
			[ 'enabled' => false, 'threshold' => 10 ]
		];
		// This is a setting from Cite extension
		yield 'responsiveReferences, true' => [
			[ 'CiteResponsiveReferences' => true ],
			'responsiveReferences',
			[ 'enabled' => true, 'threshold' => 10 ]
		];
		yield 'script' => [
			[ 'Script' => 'blabla' ],
			'script',
			'blabla'
		];
		yield 'scriptpath' => [
			[ 'ScriptPath' => 'blabla' ],
			'scriptpath',
			'blabla'
		];
		yield 'server' => [
			[ 'Server' => 'blabla' ],
			'server',
			'blabla'
		];
		yield 'timezoneOffset' => [
			[ 'LocalTZoffset' => 42 ],
			'timezoneOffset',
			42
		];
		yield 'getMaxTemplateDepth' => [
			[ 'MaxTemplateDepth' => 42 ],
			'getMaxTemplateDepth',
			42
		];
		/* $wgLegalTitleChars can't be tested with this mechanism.
		yield 'legalTitleChars' => [
			[ 'LegalTitleChars' => 'blabla' ],
			'legalTitleChars',
			'blabla'
		];
		*/
		yield 'getProtocols' => [
			[ 'UrlProtocols' => [ 'blabla' ] ],
			'getProtocols',
			[ 'blabla' ]
		];
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::galleryOptions
	 * @covers \MWParsoid\Config\SiteConfig::allowedExternalImagePrefixes
	 * @covers \MWParsoid\Config\SiteConfig::interwikiMagic
	 * @covers \MWParsoid\Config\SiteConfig::lang
	 * @covers \MWParsoid\Config\SiteConfig::responsiveReferences
	 * @covers \MWParsoid\Config\SiteConfig::script
	 * @covers \MWParsoid\Config\SiteConfig::scriptpath
	 * @covers \MWParsoid\Config\SiteConfig::server
	 * @covers \MWParsoid\Config\SiteConfig::timezoneOffset
	 * @covers \MWParsoid\Config\SiteConfig::getMaxTemplateDepth
	 * @covers \MWParsoid\Config\SiteConfig::legalTitleChars
	 * @covers \MWParsoid\Config\SiteConfig::getProtocols
	 * @dataProvider provideConfigParameterPassed
	 * @param array $settings
	 * @param string $method
	 * @param mixed $expectedValue
	 */
	public function testConfigParametersPassed(
		array $settings,
		string $method,
		$expectedValue
	) {
		$config = $this->createSiteConfig( $settings );
		$config = TestingAccessWrapper::newFromObject( $config );
		$this->assertSame( $expectedValue, $config->$method() );
	}

	public function provideParsoidSettingPassed() {
		yield 'nativeGalleryEnabled' => [
			[ 'nativeGalleryEnabled' => true ],
			'nativeGalleryEnabled',
			true
		];
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::nativeGalleryEnabled()
	 * @dataProvider provideParsoidSettingPassed
	 * @param array $settings
	 * @param string $method
	 * @param mixed $expectedValue
	 */
	public function testParsoidSettingPassed(
		array $settings,
		string $method,
		$expectedValue
	) {
		$config = $this->createSiteConfig( [], $settings );
		$config = TestingAccessWrapper::newFromObject( $config );
		$this->assertSame( $expectedValue, $config->$method() );
	}

	public function provideServiceMethodProxied() {
		yield 'canonicalNamespaceId' => [
			NamespaceInfo::class, 'getCanonicalIndex', [ 'blabla_arg' ], 42, 'canonicalNamespaceId', 42
		];
		yield 'namespaceId' => [
			Language::class, 'getNsIndex', [ 'blabla_arg' ], 42, 'namespaceId', 42
		];
		yield 'namespaceName, NS_MAIN' => [
			Language::class, 'getFormattedNsText', [ NS_MAIN ], '', 'namespaceName', ''
		];
		yield 'namespaceName, NS_USER, null' => [
			Language::class, 'getFormattedNsText', [ NS_USER ], '', 'namespaceName', null
		];
		yield 'namespaceName, NS_USER' => [
			Language::class, 'getFormattedNsText', [ NS_USER ], 'User', 'namespaceName', 'User'
		];
		yield 'namespaceHasSubpages' => [
			NamespaceInfo::class, 'hasSubpages', [ 42 ], true, 'namespaceHasSubpages', true
		];
		yield 'namespaceCase, first letter' => [
			NamespaceInfo::class, 'isCapitalized', [ 42 ], true, 'namespaceCase', 'first-letter'
		];
		yield 'namespaceCase, case sensitive' => [
			NamespaceInfo::class, 'isCapitalized', [ 42 ], false, 'namespaceCase', 'case-sensitive'
		];
		yield 'namespaceIsTalk' => [
			NamespaceInfo::class, 'isTalk', [ 42 ], true, 'namespaceIsTalk', true
		];
		yield 'ucfirst' => [
			Language::class, 'ucfirst', [ 'bla' ], 'Bla', 'ucfirst', 'Bla'
		];
		yield 'linkTrail' => [
			Language::class, 'linkTrail', [], 'blabla', 'linkTrail', 'blabla'
		];
		yield 'rtl' => [
			Language::class, 'isRTL', [], true, 'rtl', true
		];
		yield 'getVariableIDs' => [
			MagicWordFactory::class, 'getVariableIDs', [], [ 'blabla' ], 'getVariableIDs', [ 'blabla' ]
		];
		yield 'getFunctionSynonyms' => [
			Parser::class, 'getFunctionSynonyms', [], [ 0 => [ 'blabla' ], 1 => [ 'blabla' ] ],
			'getFunctionSynonyms', [ 0 => [ 'blabla' ], 1 => [ 'blabla' ] ]
		];
		yield 'getMagicWords' => [
			Language::class, 'getMagicWords', [], [ 'blabla' ], 'getMagicWords', [ 'blabla' ]
		];
		yield 'getNonNativeExtensionTags' => [
			Parser::class, 'getTags', [], [ 'blabla' ], 'getNonNativeExtensionTags', [ 'blabla' => true ]
		];
	}

	/**
	 * @dataProvider provideServiceMethodProxied
	 * @covers \MWParsoid\Config\SiteConfig::canonicalNamespaceId
	 * @covers \MWParsoid\Config\SiteConfig::namespaceId
	 * @covers \MWParsoid\Config\SiteConfig::namespaceName
	 * @covers \MWParsoid\Config\SiteConfig::namespaceHasSubpages
	 * @covers \MWParsoid\Config\SiteConfig::namespaceCase
	 * @covers \MWParsoid\Config\SiteConfig::namespaceIsTalk
	 * @covers \MWParsoid\Config\SiteConfig::ucfirst
	 * @covers \MWParsoid\Config\SiteConfig::linkTrail
	 * @covers \MWParsoid\Config\SiteConfig::rtl
	 * @covers \MWParsoid\Config\SiteConfig::widthOption
	 * @covers \MWParsoid\Config\SiteConfig::getVariableIDs
	 * @covers \MWParsoid\Config\SiteConfig::getFunctionSynonyms
	 * @covers \MWParsoid\Config\SiteConfig::getMagicWords
	 * @covers \MWParsoid\Config\SiteConfig::getNonNativeExtensionTags
	 * @param string $serviceClass
	 * @param string $serviceMethod
	 * @param array $arguments
	 * @param mixed $returnValue
	 * @param string $method
	 * @param mixed $expectedValue
	 */
	public function testServiceMethodProxied(
		string $serviceClass,
		string $serviceMethod,
		array $arguments,
		$returnValue,
		string $method,
		$expectedValue
	) {
		$serviceMock = $this->createMock( $serviceClass );
		$serviceMock
			->expects( $this->once() )
			->method( $serviceMethod )
			->with( ...$arguments )
			->willReturn( $returnValue );
		$config = $this->createSiteConfig( [], [], [
			$serviceClass => $serviceMock
		] );
		$config = TestingAccessWrapper::newFromObject( $config );
		$this->assertSame( $expectedValue, $config->$method( ...$arguments ) );
	}

	public function provideArticlePath_exception() {
		yield 'No $1' => [ '/test/test' ];
		yield 'Wrong path' => [ 'test\\test/$1' ];
	}

	/**
	 * @dataProvider provideArticlePath_exception
	 * @covers \MWParsoid\Config\SiteConfig::determineArticlePath
	 * @covers \MWParsoid\Config\SiteConfig::baseURI
	 * @covers \MWParsoid\Config\SiteConfig::relativeLinkPrefix
	 * @param string $articlePath
	 */
	public function testArticlePath_exception( string $articlePath ) {
		$this->expectException( UnexpectedValueException::class );
		$config = $this->createSiteConfig( [
			'ArticlePath' => $articlePath
		] );
		$config->baseURI();
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::determineArticlePath
	 * @covers \MWParsoid\Config\SiteConfig::baseURI
	 * @covers \MWParsoid\Config\SiteConfig::relativeLinkPrefix
	 */
	public function testArticlePath_nopath() {
		$config = $this->createSiteConfig( [
			'ArticlePath' => '$1',
			'Server' => 'https://localhost'
		] );
		$this->assertSame( 'https://localhost/', $config->baseURI() );
		$this->assertSame( './', $config->relativeLinkPrefix() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::determineArticlePath
	 * @covers \MWParsoid\Config\SiteConfig::baseURI
	 * @covers \MWParsoid\Config\SiteConfig::relativeLinkPrefix
	 */
	public function testArticlePath() {
		$config = $this->createSiteConfig( [
			'ArticlePath' => '/wiki/$1',
			'Server' => 'https://localhost'
		] );
		$this->assertSame( './', $config->relativeLinkPrefix() );
		$this->assertSame( 'https://localhost/wiki/', $config->baseURI() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::mwaToRegex
	 * @covers \MWParsoid\Config\SiteConfig::redirectRegexp
	 */
	public function testRedirectRegexp() {
		$magicWordFactoryMock = $this->createMock( MagicWordFactory::class );
		$magicWordFactoryMock
			->method( 'newArray' )
			->willReturn(
				new MagicWordArray( [ 'blabla_case_sen', 'blabla_case_insen' ], $magicWordFactoryMock )
			);
		$magicWordFactoryMock
			->method( 'get' )
			->willReturnOnConsecutiveCalls(
				new MagicWord( 'blabla_id', [ 'blabla_synonym1' ], true ),
				new MagicWord( 'blabla_id', [ 'blabla_synonym2' ], false )
			);
		$config = $this->createSiteConfig( [], [], [
			MagicWordFactory::class => $magicWordFactoryMock
		] );
		$this->assertSame( '@(?i:blabla_synonym2)|blabla_synonym1@Su', $config->redirectRegexp() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::categoryRegexp
	 */
	public function testCategoryRegexp() {
		$nsInfoMock = $this->createMock( NamespaceInfo::class );
		$nsInfoMock
			->method( 'getCanonicalName' )
			->willReturn( 'Bla bla' );
		$langMock = $this->createMock( Language::class );
		$langMock
			->method( 'getNamespaceAliases' )
			->willReturn( [ 'Bla_alias' => NS_CATEGORY, 'Ignored' => NS_MAIN ] );
		$config = $this->createSiteConfig( [], [], [
			NamespaceInfo::class => $nsInfoMock,
			Language::class => $langMock
		] );
		$this->assertSame( '@(?i:Bla[ _]bla|Bla[ _]alias)@', $config->categoryRegexp() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::bswRegexp
	 */
	public function testBswRegexp() {
		$magicWordFactoryMock = $this->createMock( MagicWordFactory::class );
		$magicWordFactoryMock
			->method( 'getDoubleUnderscoreArray' )
			->willReturn(
				new MagicWordArray( [ 'blabla' ], $magicWordFactoryMock )
			);
		$magicWordFactoryMock
			->method( 'get' )
			->willReturn(
				new MagicWord( 'blabla_id', [ 'blabla_synonym' ], true )
			);
		$config = $this->createSiteConfig( [], [], [
			MagicWordFactory::class => $magicWordFactoryMock
		] );
		$this->assertSame( '@(?i:(?!))|blabla_synonym@Su', $config->bswRegexp() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::specialPageLocalName
	 */
	public function testSpecialPageLocalName() {
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock
			->method( 'resolveAlias' )
			->with( 'blabla_alias' )
			->willReturn( [ 'resolved_page', 'resolved_subpage' ] );
		$specialPageFactoryMock
			->method( 'getLocalNameFor' )
			->with( 'resolved_page', 'resolved_subpage' )
			->willReturn( 'blabla' );
		$config = $this->createSiteConfig( [], [], [
			SpecialPageFactory::class => $specialPageFactoryMock
		] );
		$this->assertSame( 'blabla', $config->specialPageLocalName( 'blabla_alias' ) );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::interwikiMap
	 */
	public function testInterwikiMap() {
		$interwikiMock = $this->createMock( InterwikiLookup::class );
		$interwikiMock
			->method( 'getAllPrefixes' )
			->willReturn( [
				[ 'iw_prefix' => 'ru', 'iw_url' => '//test/', 'iw_local' => 1 ]
			] );
		$langNameUtilsMock = $this->createMock( LanguageNameUtils::class );
		$langNameUtilsMock
			->method( 'getLanguageNames' )
			->willReturn( [ 'ru' => 'Russian' ] );
		$messageCacheMock = $this->createMock( MessageCache::class );
		$messageCacheMock
			->method( 'get' )
			->willReturn( false );

		$config = $this->createSiteConfig( [
			'ExtraInterlanguageLinkPrefixes' => [ 'ru' ],
			'LocalInterwikis' => [ 'ru' ],
		], [], [
			InterwikiLookup::class => $interwikiMock,
			LanguageNameUtils::class => $langNameUtilsMock,
			MessageCache::class => $messageCacheMock,
		] );
		$this->assertSame( [
			'ru' => [
				'prefix' => 'ru',
				'url' => 'http://test/$1',
				'protorel' => true,
				'local' => true,
				'language' => true,
				'localinterwiki' => true,
				'extralanglink' => true,
			]
		], $config->interwikiMap() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::iwp
	 */
	public function testIwp() {
		$config = $this->createSiteConfig();
		$this->assertNotNull( $config->iwp() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::linkPrefixRegex
	 */
	public function testLinkPrefixRegex_disabled() {
		$langMock = $this->createMock( Language::class );
		$langMock
			->method( 'linkPrefixExtension' )
			->willReturn( false );
		$config = $this->createSiteConfig( [], [], [
			Language::class => $langMock
		] );
		$this->assertNull( $config->linkPrefixRegex() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::linkPrefixRegex
	 */
	public function testLinkPrefixRegex() {
		$langMock = $this->createMock( Language::class );
		$langMock
			->method( 'linkPrefixExtension' )
			->willReturn( true );
		$langMock
			->method( 'linkPrefixCharset' )
			->willReturn( 'blabla' );
		$config = $this->createSiteConfig( [], [], [
			Language::class => $langMock
		] );
		$this->assertStringContainsString( 'blabla', $config->linkPrefixRegex() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::mainpage
	 */
	public function testMainpage() {
		$this->markTestSkipped( 'Requires MessageCache; not a unit test' );
		$this->assertSame( 'Main Page', $this->createSiteConfig()->mainpage() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::langConverterEnabled
	 */
	public function testLangConverterEnabled_disabled() {
		$langConverterFactoryMock = $this->createMock( LanguageConverterFactory::class );
		$langConverterFactoryMock
			->method( 'isConversionDisabled' )
			->willReturn( true );
		$config = $this->createSiteConfig( [], [], [
			LanguageConverterFactory::class => $langConverterFactoryMock,
		] );
		$this->assertFalse( $config->langConverterEnabled( 'zh' ) );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::langConverterEnabled
	 */
	public function testLangConverterEnabled_invalidCode() {
		$langConverterFactoryMock = $this->createMock( LanguageConverterFactory::class );
		$langConverterFactoryMock
			->method( 'isConversionDisabled' )
			->willReturn( false );
		$config = $this->createSiteConfig( [], [], [
			LanguageConverterFactory::class => $langConverterFactoryMock,
		] );
		$this->assertFalse( $config->langConverterEnabled( 'zhasdcasdc' ) );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::langConverterEnabled
	 */
	public function testLangConverterEnabled_valid() {
		$langMock = $this->createMock( Language::class );
		$langFactoryMock = $this->createMock( LanguageFactory::class );
		$langFactoryMock
			->method( 'getLanguage' )
			->with( 'zh' )
			->willReturn( $langMock );
		$langConverterMock = $this->getMockBuilder( ZhConverter::class )
			->disableOriginalConstructor()
			->getMock();
		$langConverterMock
			->method( 'hasVariants' )
			->willReturn( true );
		$langConverterFactoryMock = $this->createMock( LanguageConverterFactory::class );
		$langConverterFactoryMock
			->method( 'getLanguageConverter' )
			->with( $langMock )
			->willReturn( $langConverterMock );
		$langConverterFactoryMock
			->method( 'isConversionDisabled' )
			->willReturn( false );
		$config = $this->createSiteConfig( [], [], [
			LanguageFactory::class => $langFactoryMock,
			LanguageConverterFactory::class => $langConverterFactoryMock
		] );
		$this->assertTrue( $config->langConverterEnabled( 'zh' ) );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::langConverterEnabled
	 */
	public function testLangConverterEnabled_exception() {
		$langFactoryMock = $this->createMock( LanguageFactory::class );
		$langFactoryMock
			->method( 'getLanguage' )
			->with( 'zh' )
			->willThrowException( new MWException( 'TEST' ) );
		$langConverterFactoryMock = $this->createMock( LanguageConverterFactory::class );
		$langConverterFactoryMock
			->method( 'isConversionDisabled' )
			->willReturn( false );
		$config = $this->createSiteConfig( [], [], [
			LanguageFactory::class => $langFactoryMock,
			LanguageConverterFactory::class => $langConverterFactoryMock,
		] );
		$this->assertFalse( $config->langConverterEnabled( 'zh' ) );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::variants
	 */
	public function testVariants_disabled() {
		$langConverterFactoryMock = $this->createMock( LanguageConverterFactory::class );
		$langConverterFactoryMock
			->method( 'isConversionDisabled' )
			->willReturn( true );
		$config = $this->createSiteConfig( [], [], [
			LanguageConverterFactory::class => $langConverterFactoryMock,
		] );
		$this->assertSame( [], $config->variants() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::variants
	 */
	public function testVariants() {
		$langFactoryMock = $this->createMock( LanguageFactory::class );
		$langFactoryMock
			->method( 'getLanguage' )
			->willReturnCallback( function ( $code ) {
				$langMock = $this->createMock( Language::class );
				$langMock->method( 'getCode' )
					->willReturn( $code );
				return $langMock;
			} );
		$converterMock = $this->createMock( ILanguageConverter::class );
		$converterMock
			->method( 'hasVariants' )
			->willReturn( true );
		$converterMock
			->method( 'getVariants' )
			->willReturn( [ 'zh-hans' ] );
		$converterMock
			->method( 'getVariantFallbacks' )
			->willReturn( 'zh-fallback' );
		$langConverterFactoryMock = $this->createMock( LanguageConverterFactory::class );
		$langConverterFactoryMock
			->method( 'isConversionDisabled' )
			->willReturn( false );
		$langConverterFactoryMock
			->method( 'getLanguageConverter' )
			->willReturnCallback( function ( $l ) use ( $converterMock ) {
				if ( $l->getCode() === 'zh' ) {
					return $converterMock;
				}
				return $this->createMock( ILanguageConverter::class );
			} );
		$config = $this->createSiteConfig( [], [], [
			LanguageFactory::class => $langFactoryMock,
			LanguageConverterFactory::class => $langConverterFactoryMock
		] );
		$this->assertSame(
			[ 'zh-hans' => [ 'base' => 'zh', 'fallbacks' => [ 'zh-fallback' ] ] ],
			$config->variants()
		);
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::widthOption
	 */
	public function testWithOption() {
		$optionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$optionsLookupMock
			->method( 'getDefaultOption' )
			->with( 'thumbsize' )
			->willReturn( 'small' );
		$config = $this->createSiteConfig( [
			'ThumbLimits' => [ 'small' => 42 ]
		], [], [
			UserOptionsLookup::class => $optionsLookupMock
		] );
		$this->assertSame( 42, $config->widthOption() );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::getMagicWordMatcher
	 */
	public function testGetMagicWordMatcher() {
		$magicWordMock = $this->createMock( MagicWord::class );
		$magicWordMock
			->expects( $this->once() )
			->method( 'getRegexStartToEnd' )
			->willReturn( 'blabla' );
		$magicWordFactoryMock = $this->createMock( MagicWordFactory::class );
		$magicWordFactoryMock
			->expects( $this->once() )
			->method( 'get' )
			->with( 'blabla_id' )
			->willReturn( $magicWordMock );
		$config = $this->createSiteConfig( [], [], [
			MagicWordFactory::class => $magicWordFactoryMock
		] );
		$this->assertSame( 'blabla', $config->getMagicWordMatcher( 'blabla_id' ) );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::getParameterizedAliasMatcher
	 */
	public function testGetParameterizedAliasMatcher() {
		$magicWordFactoryMock = $this->createMock( MagicWordFactory::class );
		$magicWordFactoryMock
			->method( 'newArray' )
			->willReturn( new MagicWordArray( [ 'test' ], $magicWordFactoryMock ) );
		$magicWordFactoryMock
			->method( 'get' )
			->willReturn( new MagicWord( 'blabla_id', [ 'blabla_alias:$1' ], true ) );
		$config = $this->createSiteConfig( [], [], [
			MagicWordFactory::class => $magicWordFactoryMock
		] );
		$matcher = $config->getParameterizedAliasMatcher( [ 'blabla' ] );
		$this->assertSame( [ 'k' => 'test', 'v' => 'blabla' ], $matcher( 'blabla_alias:blabla' ) );
		$this->assertNull( $matcher( 'Blabla_alias:blabla' ) );
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::getSpecialNSAliases
	 */
	public function testGetSpecialNSAliases() {
		$mockLang = $this->createMock( Language::class );
		$mockLang
			->method( 'getNsText' )
			->willReturn( 'Special_Special' );
		$mockLang
			->method( 'getNamespaceAliases' )
			->willReturn( [
				'From Language' => NS_SPECIAL,
				'Whatever' => NS_MAIN
			] );
		$config = $this->createSiteConfig( [
			'NamespaceAliases' => [
				'From Config' => NS_SPECIAL,
				'Whatever' => NS_MAIN
			]
		], [], [
			Language::class => $mockLang
		] );
		$config = TestingAccessWrapper::newFromObject( $config );
		$this->assertSame(
			[ 'Special', 'Special[ _]Special', 'From[ _]Language', 'From[ _]Config' ],
			$config->getSpecialNSAliases()
		);
	}

	/**
	 * @covers \MWParsoid\Config\SiteConfig::getSpecialPageAliases
	 */
	public function testGetSpecialPageAliases() {
		$mockLang = $this->createMock( Language::class );
		$mockLang
			->method( 'getSpecialPageAliases' )
			->willReturn( [
				'Page1' => [ 'Alias1', 'Alias2' ]
			] );
		$config = $this->createSiteConfig( [], [], [
			Language::class => $mockLang
		] );
		$config = TestingAccessWrapper::newFromObject( $config );
		$this->assertSame( [ 'Page1', 'Alias1', 'Alias2' ], $config->getSpecialPageAliases( 'Page1' ) );
		$this->assertSame( [ 'Page2' ], $config->getSpecialPageAliases( 'Page2' ) );
	}
}
