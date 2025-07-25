<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiLogout;
use MediaWiki\Api\ApiQuery;
use MediaWiki\CheckUser\HookHandler\ClientHints;
use MediaWiki\Config\HashConfig;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\FauxResponse;
use MediaWiki\Request\WebRequest;
use MediaWiki\Request\WebResponse;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\ClientHints
 */
class ClientHintsTest extends MediaWikiUnitTestCase {

	public function testClientHintsSpecialPageConfigDisabled() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		$webRequest = $this->createMock( WebRequest::class );
		$webResponse = $this->createMock( WebResponse::class );
		$webRequest->method( 'response' )->willReturn( $webResponse );
		$webResponse->expects( $this->never() )->method( 'header' );
		$special->method( 'getRequest' )->willReturn( $webRequest );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders()
			] ),
			$specialPageFactoryMock
		);
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
	}

	public function testClientHintsSpecialPageNotInAllowList() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		$webRequest = $this->createMock( WebRequest::class );
		$webResponse = $this->createMock( WebResponse::class );
		$webRequest->method( 'response' )->willReturn( $webResponse );
		$webResponse->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$special->method( 'getRequest' )->willReturn( $webRequest );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
	}

	public function testClientHintsSpecialPageRequested() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		$requestMock = $this->getMockBuilder( FauxRequest::class )
			->onlyMethods( [ 'response', 'getHeader' ] )->getMock();
		$requestMock->method( 'getHeader' )->with( 'Sec-Ch-Ua' )
			->willReturn( '"Not.A/Brand";v="8", "Chromium";v="114", "Google Chrome";v="114"' );
		$fauxResponse = new FauxResponse();
		$requestMock->method( 'response' )->willReturn( $fauxResponse );
		$special->method( 'getRequest' )->willReturn( $requestMock );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
		$this->assertSame(
			implode( ', ', array_keys( $this->getDefaultClientHintHeaders() ) ),
			$fauxResponse->getHeader( 'ACCEPT-CH' )
		);
	}

	public function testClientHintsSpecialPageRequestedWhenConfigSpecifiesHeaderString() {
		$mockRequest = new FauxRequest();
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )
			->willReturn( 'Foo' );
		$special->method( 'getRequest' )
			->willReturn( $mockRequest );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' => 'header' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
		$this->assertSame(
			implode( ', ', array_keys( $this->getDefaultClientHintHeaders() ) ),
			$mockRequest->response()->getHeader( 'ACCEPT-CH' )
		);
	}

	public function testClientHintsSpecialPageRequestedWhenConfigSpecifiesJsAPI() {
		// We load ext.checkUser.clientHints on all pages, regardless of whether
		// CheckUserClientHintsSpecialPages says "js" or "header".
		$mockRequest = new FauxRequest();
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )
			->willReturn( 'Foo' );
		$special->method( 'getRequest' )
			->willReturn( $mockRequest );
		// Expect that the ext.checkUser.clientHints module gets added to the special page
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$special->method( 'getOutput' )
			->willReturn( $outputPage );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' => 'js' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$skinMock = $this->createMock( Skin::class );
		$requestMock = $this->createMock( 'WebRequest' );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'isSpecialPage' )->willReturn( true );
		$outputPage->method( 'getTitle' )->willReturn( $titleMock );
		$hookHandler->onBeforePageDisplay( $outputPage, $skinMock );
	}

	public function testBeforePageDisplayDoesNotOverrideHeaderInSpecialPageExecuteWithClientHintsSpecialPage() {
		$special = $this->createMock( SpecialPage::class );
		$skinMock = $this->createMock( Skin::class );
		$requestMock = $this->createMock( 'WebRequest' );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->never() )->method( 'header' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$special->method( 'getOutput' )
			->willReturn( $outputPage );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'isSpecialPage' )->willReturn( true );
		$outputPage->method( 'getTitle' )->willReturn( $titleMock );
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )
			->willReturn( 'Foo' );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' => [ 'js', 'header' ] ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$hookHandler->onBeforePageDisplay( $outputPage, $skinMock );
	}

	public function testBeforePageDisplayDoesNotOverrideHeaderInSpecialPageExecuteWithClientHintsSpecialPageJsOnly() {
		$special = $this->createMock( SpecialPage::class );
		$skinMock = $this->createMock( Skin::class );
		$requestMock = $this->createMock( 'WebRequest' );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$special->method( 'getOutput' )
			->willReturn( $outputPage );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'isSpecialPage' )->willReturn( true );
		$outputPage->method( 'getTitle' )->willReturn( $titleMock );
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )
			->willReturn( 'Foo' );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' => [ 'js' ] ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$hookHandler->onBeforePageDisplay( $outputPage, $skinMock );
	}

	public function testBeforePageDisplayDoesNotOverrideHeaderInSpecialPageExecuteWithNonClientHintsSpecialPage() {
		$special = $this->createMock( SpecialPage::class );
		$skinMock = $this->createMock( Skin::class );
		$requestMock = $this->createMock( 'WebRequest' );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$special->method( 'getOutput' )
			->willReturn( $outputPage );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'isSpecialPage' )->willReturn( true );
		$outputPage->method( 'getTitle' )->willReturn( $titleMock );
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )
			->willReturn( 'Bar' );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' => [ 'js', 'header' ] ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$hookHandler->onBeforePageDisplay( $outputPage, $skinMock );
	}

	public function testClientHintsBeforePageDisplayDisabled() {
		$special = $this->createMock( SpecialPage::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => false,
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$title = $this->createMock( Title::class );
		$title->method( 'isSpecialPage' )->willReturn( true );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		// We should not add ext.checkUser.clientHints to page if feature flag is off.
		$outputPage->expects( $this->never() )->method( 'addModules' );
		$webRequest = $this->createMock( WebRequest::class );
		$webResponse = $this->createMock( WebResponse::class );
		$webRequest->method( 'response' )->willReturn( $webResponse );
		// ::header() should never be called, because global config flag is off.
		$webResponse->expects( $this->never() )->method( 'header' );
		$outputPage->method( 'getRequest' )->willReturn( $webRequest );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	public function testClientHintsBeforePageDisplayUnsetsHeader() {
		$special = $this->createMock( SpecialPage::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		// We should add ext.checkUser.clientHints to page
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	public function testClientHintsBeforePageDisplayPOSTRequest() {
		$special = $this->createMock( SpecialPage::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		// We add ext.checkUser.clientHints to page on POST, because rollback action
		// using the JS confirmable (T215020) does a POST.
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$requestMock->method( 'wasPosted' )->willReturn( true );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );

		// Repeat the scenario, but with CheckUserClientHintsUnsetHeaderWhenPossible set to false.
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => false,
			] ),
			$specialPageFactoryMock
		);
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->never() )->method( 'header' );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$requestMock->method( 'wasPosted' )->willReturn( true );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	/** @dataProvider provideApiGetAllowedParams */
	public function testApiGetAllowedParamsForApiLogout(
		$apiModuleClass, $clientHintsEnabled, $shouldAddClientHintsParam
	) {
		$special = $this->createMock( SpecialPage::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		/** @var ApiBase|MockObject $module */
		$module = $this->createMock( $apiModuleClass );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => $clientHintsEnabled,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			$specialPageFactoryMock
		);
		$params = [];
		$hookHandler->onAPIGetAllowedParams( $module, $params, 0 );
		if ( $shouldAddClientHintsParam ) {
			$this->assertSame( 'checkuserclienthints', array_key_first( $params ) );
		} else {
			$this->assertSame( [], $params );
		}
	}

	public static function provideApiGetAllowedParams() {
		return [
			'ApiLogout module with Client Hints enabled' => [ ApiLogout::class, true, true ],
			'ApiLogout module without Client Hints enabled' => [ ApiLogout::class, false, false ],
			'ApiQuery module' => [ ApiQuery::class, true, false ],
		];
	}

	private function getDefaultClientHintHeaders(): array {
		return [
			'Sec-CH-UA' => '',
			'Sec-CH-UA-Arch' => 'architecture',
			'Sec-CH-UA-Bitness' => 'bitness',
			'Sec-CH-UA-Form-Factor' => '',
			'Sec-CH-UA-Full-Version-List' => 'fullVersionList',
			'Sec-CH-UA-Mobile' => 'mobile',
			'Sec-CH-UA-Model' => 'model',
			'Sec-CH-UA-Platform' => 'platform',
			'Sec-CH-UA-Platform-Version' => 'platformVersion',
			'Sec-CH-UA-WoW64' => ''
		];
	}

}
