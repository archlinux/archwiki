<?php
namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\SpecialPageBeforeExecuteHandler;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MutableConfig;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Context\IContextSource;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\SpecialPageBeforeExecuteHandler
 */
class SpecialPageBeforeExecuteHandlerTest extends MediaWikiIntegrationTestCase {
	private SpecialPageBeforeExecuteHandler $handler;
	private SpecialPage $specialPage;

	private IContextSource $context;
	private MutableConfig $config;
	private Title $title;
	private OutputPage $outputPage;

	public function setUp(): void {
		parent::setUp();
		$conf = new SiteConfiguration();
		$conf->settings = [
			'wgServer' => [
				'enwiki' => 'https://en.example.org',
				'metawiki' => 'https://meta.example.org',
			],
			'wgArticlePath' => [
				'enwiki' => '/wiki/$1',
				'metawiki' => '/wiki/$1',
			],
		];
		$conf->suffixes = [ 'wiki' ];
		$this->setMwGlobals( 'wgConf', $conf );

		$this->handler = new SpecialPageBeforeExecuteHandler();

		$this->config = new HashConfig();
		$this->outputPage = $this->createMock( OutputPage::class );
		$this->title = $this->createMock( Title::class );

		$this->context = $this->createMock( IContextSource::class );
		$this->context->method( 'getConfig' )
			->willReturn( $this->config );
		$this->context->method( 'getOutput' )
			->willReturn( $this->outputPage );
		$this->context->method( 'getTitle' )
			->willReturn( $this->title );

		$this->specialPage = $this->createMock( SpecialPage::class );
		$this->specialPage->method( 'getContext' )
			->willReturn( $this->context );
	}

	public function testShouldDoNothingWhenCentralWikiConfigNotSet(): void {
		$this->config->set( 'CheckUserGlobalContributionsCentralWikiId', false );

		$this->title->method( 'isSpecial' )
			->with( 'GlobalContributions' )
			->willReturn( true );

		$this->outputPage->expects( $this->never() )
			->method( 'redirect' );

		$result = $this->handler->onSpecialPageBeforeExecute( $this->specialPage, '' );

		$this->assertTrue( $result );
	}

	public function testShouldDoNothingWhenNotOnGlobalContributionsPage(): void {
		$this->config->set( 'CheckUserGlobalContributionsCentralWikiId', 'metawiki' );

		$this->title->method( 'isSpecial' )
			->with( 'GlobalContributions' )
			->willReturn( false );

		$this->outputPage->expects( $this->never() )
			->method( 'redirect' );

		$result = $this->handler->onSpecialPageBeforeExecute( $this->specialPage, '' );

		$this->assertTrue( $result );
	}

	/**
	 * @dataProvider provideRedirectParams
	 */
	public function testShouldRedirectOnSpecialGlobalContributionsWithConfigSet(
		array $queryParams,
		string $titleText,
		string $expectedRedirectUrl
	): void {
		$this->config->set( 'CheckUserGlobalContributionsCentralWikiId', 'metawiki' );

		$this->title->method( 'isSpecial' )
			->with( 'GlobalContributions' )
			->willReturn( true );
		$this->title->method( 'getText' )
			->willReturn( $titleText );

		$request = new FauxRequest( $queryParams );
		$this->context->method( 'getRequest' )
			->willReturn( $request );

		$this->outputPage->expects( $this->once() )
			->method( 'redirect' )
			->with( $expectedRedirectUrl );

		$result = $this->handler->onSpecialPageBeforeExecute( $this->specialPage, '' );

		$this->assertFalse( $result );
	}

	public static function provideRedirectParams(): iterable {
		yield 'request with no params' => [
			[],
			'GlobalContributions',
			'https://meta.example.org/wiki/Special:GlobalContributions'
		];

		yield 'request with target' => [
			[ 'target' => '127.0.0.1' ],
			'GlobalContributions',
			'https://meta.example.org/wiki/Special:GlobalContributions?target=127.0.0.1',
		];

		yield 'request with superfluous title parameter' => [
			[ 'target' => '127.0.0.1', 'title' => 'Special:GlobalContributions' ],
			'GlobalContributions',
			'https://meta.example.org/wiki/Special:GlobalContributions?target=127.0.0.1'
		];

		yield 'request with target set in subpage' => [
			[],
			'GlobalContributions/127.0.0.1',
			'https://meta.example.org/wiki/Special:GlobalContributions/127.0.0.1'
		];
	}
}
