<?php

namespace MediaWiki\Minerva;

use DOMDocument;
use DOMXPath;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Minerva\Skins\SkinMinerva
 * @covers \MediaWiki\Minerva\Menu\Definitions
 * @group MinervaNeue
 * @group Database
 */
class DonationLinksTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setContentLang( 'en' );
		$this->setUserLang( 'en' );
		$this->overrideConfigValues( [
			'ArticlePath' => '/wiki/$1',
			'UrlProtocols' => [ 'http://', 'https://', '//' ],
		] );
		// Exercise Minerva's own messages even when WikimediaMessages is installed.
		$this->clearHook( 'MessageCacheFetchOverrides' );
		$this->clearHook( 'MessageCache::get' );
		$this->getServiceContainer()->getMessageCache()->enable();
		$this->getServiceContainer()->getService( 'Minerva.SkinOptions' )->setMultiple( [
			SkinOptions::SHOW_DONATE_BANNER => true,
			SkinOptions::SHOW_DONATE => true,
			SkinOptions::MAIN_MENU_EXPANDED => false,
		] );
		RequestContext::getMain()->setUser( $this->getServiceContainer()->getUserFactory()->newAnonymous() );

		foreach ( [
			'skin-minerva-donate-banner-message' => 'Support the wiki',
			'skin-minerva-donate-banner-subtitle' => 'Please give today.',
			'skin-minerva-donate-banner-url' => 'https://donate.example.org/',
			'skin-minerva-donate-banner-gif' => '/static/images/donate/donate.gif',
			'skin-minerva-donate-banner-reduced-motion' => '/static/images/donate/donate.png',
			'sitesupport' => 'Donate',
			'sitesupport-url' => 'https://donate.example.org/',
		] as $key => $value ) {
			$this->editMessage( $key, $value );
		}
	}

	private function editMessage( string $key, string $value ): void {
		// The MediaWiki namespace uses a capital first letter. Some data sets use the same value as
		// setUp(). The edit then has the "edit-no-change" warning, so check only that it is OK.
		$this->assertStatusOK( $this->editPage( Title::makeTitle( NS_MEDIAWIKI, ucfirst( $key ) ), $value ) );
		$this->runDeferredUpdates();
	}

	private function renderMenu(): DOMXPath {
		$context = RequestContext::getMain();
		$context->setTitle( $this->getExistingTestPage()->getTitle() );
		$skin = $this->getServiceContainer()->getSkinFactory()->makeSkin( 'minerva' );
		$context->setSkin( $skin );
		$data = $skin->getTemplateData();
		$parser = new TemplateParser( __DIR__ . '/../../../includes/Skins' );
		$html = $parser->processTemplate( 'menu', $data['data-minerva-main-menu'] + $data );
		$document = new DOMDocument();
		$document->loadHTML( $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		return new DOMXPath( $document );
	}

	/**
	 * @dataProvider provideValidImagePaths
	 */
	public function testValidImagePaths( string $gif, string $reducedMotion ): void {
		$this->editMessage( 'skin-minerva-donate-banner-gif', $gif );
		$this->editMessage( 'skin-minerva-donate-banner-reduced-motion', $reducedMotion );
		$xpath = $this->renderMenu();

		$this->assertSame( 1, $xpath->query( '//picture/img' )->length );
		$this->assertSame( $gif, $xpath->evaluate( 'string(//picture/img/@src)' ) );
		$this->assertSame( 1, $xpath->query( '//picture/source' )->length );
		$this->assertSame( $reducedMotion, $xpath->evaluate( 'string(//picture/source/@srcset)' ) );
		$this->assertSame( '(prefers-reduced-motion)', $xpath->evaluate( 'string(//picture/source/@media)' ) );
		$this->assertSame(
			'Support the wiki',
			$xpath->evaluate( 'string(//span[@class="donate-banner__text"])' )
		);
		$this->assertSame(
			'Please give today.',
			$xpath->evaluate( 'string(//span[@class="donate-banner__subtitle"])' )
		);
	}

	public static function provideValidImagePaths(): array {
		return [
			'WMF paths' => [ '/static/images/donate/donate.gif', '/static/images/donate/donate.png' ],
			'custom paths' => [ '/images/campaign_2026/banner-2.gif', '/images/campaign_2026/banner-2.png' ],
		];
	}

	/**
	 * @dataProvider provideInvalidImagePaths
	 */
	public function testInvalidImagePathOmitsPicture( string $key, string $path ): void {
		$this->editMessage( $key, $path );
		$xpath = $this->renderMenu();

		$this->assertSame( 0, $xpath->query( '//picture | //img | //source' )->length );
		$this->assertSame( 1, $xpath->query( '//a[@data-event-name="menu.donateBanner"]' )->length );
		$this->assertSame(
			'Support the wiki',
			$xpath->evaluate( 'string(//span[@class="donate-banner__text"])' )
		);
	}

	public static function provideInvalidImagePaths(): iterable {
		$paths = [
			'empty' => '',
			'HTTPS URL' => 'https://images.example.org/banner.png',
			'HTTP URL' => 'http://images.example.org/banner.png',
			'protocol-relative URL' => '//images.example.org/banner.png',
			'backslash' => '/\\images.example.org/banner.png',
			'tab' => "/\t/images.example.org/banner.png",
			'newline' => "/\n/images.example.org/banner.png",
			'javascript URL' => 'javascript:alert(1)',
			'data URL' => 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///w==',
			'external srcset candidate' => '/images/banner.png 1x, https://images.example.org/banner.png 2x',
			'attribute injection' => '/images/banner.png" onerror="alert(1)',
			'page-relative path' => 'images/banner.png',
			'query string' => '/images/banner.png?campaign=donate',
			'encoded space' => '/images/donate%20banner.png',
		];
		foreach ( [ 'skin-minerva-donate-banner-gif', 'skin-minerva-donate-banner-reduced-motion' ] as $key ) {
			foreach ( $paths as $name => $path ) {
				yield "$key: $name" => [ $key, $path ];
			}
		}
	}

	/**
	 * @dataProvider provideUnsafeDonationUrls
	 */
	public function testUnsafeDonationUrls( string $url ): void {
		$this->editMessage( 'skin-minerva-donate-banner-url', $url );
		$this->editMessage( 'sitesupport-url', $url );
		$xpath = $this->renderMenu();

		foreach ( [ 'menu.donateBanner', 'menu.donate' ] as $eventName ) {
			$links = $xpath->query( '//a[@data-event-name="' . $eventName . '"]' );
			$this->assertSame( 1, $links->length );
			// Unsafe schemes must become local page links or an empty destination.
			$this->assertMatchesRegularExpression( '~^(?:/wiki/|\?|$)~', $links->item( 0 )->getAttribute( 'href' ) );
		}
		$this->assertSame( 0, $xpath->query( '//*[@onerror or @onclick or @onmouseover]' )->length );
	}

	public static function provideUnsafeDonationUrls(): array {
		return [
			'javascript' => [ 'javascript:alert(document.domain)//https://donate.example.org' ],
			'mixed-case javascript' => [ 'JaVaScRiPt:alert(1)' ],
			'data' => [ 'data:text/html,<script>alert(1)</script>' ],
			'leading whitespace' => [ ' javascript:alert(1)' ],
			'embedded tab' => [ "java\tscript:alert(1)" ],
			'embedded newline' => [ "java\nscript:alert(1)" ],
		];
	}

	/**
	 * @dataProvider provideValidDonationUrls
	 */
	public function testValidDonationUrls( string $url, string $bannerHref, string $menuHref ): void {
		$this->editMessage( 'skin-minerva-donate-banner-url', $url );
		$this->editMessage( 'sitesupport-url', $url );
		$xpath = $this->renderMenu();

		$this->assertSame(
			$bannerHref,
			$xpath->evaluate( 'string(//a[@data-event-name="menu.donateBanner"]/@href)' )
		);
		$this->assertSame(
			$menuHref,
			$xpath->evaluate( 'string(//a[@data-event-name="menu.donate"]/@href)' )
		);
		$this->assertSame( 0, $xpath->query( '//*[@onerror or @onclick or @onmouseover]' )->length );
	}

	public static function provideValidDonationUrls(): array {
		return [
			'HTTPS with query and fragment' => [
				'https://donate.example.org/?campaign=wiki&lang=en#donate',
				'https://donate.example.org/?campaign=wiki&lang=en#donate',
				'https://donate.example.org/?campaign=wiki&lang=en&wmf_key=minerva#donate',
			],
			'protocol-relative URL' => [
				'//donate.example.org/',
				'//donate.example.org/',
				'//donate.example.org/?wmf_key=minerva',
			],
			'local page title' => [ 'Donate page', '/wiki/Donate_page', '/wiki/Donate_page?wmf_key=minerva' ],
			'quotes remain inside the attribute' => [
				'https://donate.example.org/" onmouseover="alert(1)',
				'https://donate.example.org/" onmouseover="alert(1)',
				'https://donate.example.org/" onmouseover="alert(1)?wmf_key=minerva',
			],
		];
	}

	/**
	 * @dataProvider provideHiddenDonationBanner
	 */
	public function testDonationBannerHidden( bool $enabled, bool $registered ): void {
		$this->getServiceContainer()->getService( 'Minerva.SkinOptions' )->setMultiple( [
			SkinOptions::SHOW_DONATE_BANNER => $enabled,
		] );
		if ( $registered ) {
			RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );
		}
		$xpath = $this->renderMenu();
		$this->assertSame( 0, $xpath->query( '//div[@class="donate-banner"]' )->length );
	}

	public static function provideHiddenDonationBanner(): array {
		return [
			'feature disabled' => [ false, false ],
			'registered user' => [ true, true ],
		];
	}
}
