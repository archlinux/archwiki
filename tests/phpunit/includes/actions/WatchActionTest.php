<?php

use MediaWiki\Tests\Unit\DummyServicesTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers WatchAction
 *
 * @group Action
 */
class WatchActionTest extends MediaWikiIntegrationTestCase {
	use DummyServicesTrait;
	use MockAuthorityTrait;

	/**
	 * @var WatchAction
	 */
	private $watchAction;

	/**
	 * @var WikiPage
	 */
	private $testWikiPage;

	/**
	 * @var IContextSource
	 */
	private $context;

	protected function setUp(): void {
		parent::setUp();

		$testTitle = Title::newFromText( 'UTTest' );
		$this->testWikiPage = new WikiPage( $testTitle );
		$testContext = new DerivativeContext( RequestContext::getMain() );
		$testContext->setTitle( $testTitle );
		$this->context = $testContext;
		$this->watchAction = $this->getWatchAction(
			Article::newFromWikiPage( $this->testWikiPage, $testContext ),
			$testContext
		);
	}

	/**
	 * @throws MWException
	 */
	protected function tearDown(): void {
		$this->hideDeprecated( 'Hooks::clear' );
		Hooks::clear( 'WatchArticle' );
		Hooks::clear( 'UnwatchArticle' );

		parent::tearDown();
	}

	private function getWatchAction( Article $article, IContextSource $context ) {
		$mwServices = $this->getServiceContainer();
		return new WatchAction(
			$article,
			$context,
			$mwServices->getWatchlistManager(),
			$mwServices->getWatchedItemStore()
		);
	}

	/**
	 * @covers WatchAction::getName()
	 */
	public function testGetName() {
		$this->assertEquals( 'watch', $this->watchAction->getName() );
	}

	/**
	 * @covers WatchAction::requiresUnblock()
	 */
	public function testRequiresUnlock() {
		$this->assertFalse( $this->watchAction->requiresUnblock() );
	}

	/**
	 * @covers WatchAction::doesWrites()
	 */
	public function testDoesWrites() {
		$this->assertTrue( $this->watchAction->doesWrites() );
	}

	/**
	 * @covers WatchAction::onSubmit()
	 */
	public function testOnSubmit() {
		/** @var Status $actual */
		$actual = $this->watchAction->onSubmit( [] );

		$this->assertTrue( $actual->isGood() );
	}

	/**
	 * @covers WatchAction::onSubmit()
	 */
	public function testOnSubmitHookAborted() {
		// WatchlistExpiry feature flag.
		$this->setMwGlobals( 'wgWatchlistExpiry', true );

		$testContext = $this->getMockBuilder( DerivativeContext::class )
			->onlyMethods( [ 'getRequest' ] )
			->setConstructorArgs( [ $this->watchAction->getContext() ] )
			->getMock();

		// Change the context to have a registered user with correct permission.
		$user = new UserIdentityValue( 100, 'User Name' );
		$performer = $this->mockUserAuthorityWithPermissions( $user, [ 'editmywatchlist' ] );
		$testContext->setAuthority( $performer );

		/** @var MockObject|WebRequest $testRequest */
		$testRequest = $this->createMock( WebRequest::class );
		$testRequest->expects( $this->once() )
			->method( 'getVal' )
			->willReturn( '6 months' );
		$testContext->method( 'getRequest' )->willReturn( $testRequest );

		$this->watchAction = $this->getWatchAction(
			Article::newFromWikiPage( $this->testWikiPage, $testContext ),
			$testContext
		);

		Hooks::register( 'WatchArticle', static function () {
			return false;
		} );

		/** @var Status $actual */
		$actual = $this->watchAction->onSubmit( [] );

		$this->assertInstanceOf( Status::class, $actual );
		$this->assertTrue( $actual->hasMessage( 'hookaborted' ) );
	}

	/**
	 * @covers WatchAction::checkCanExecute()
	 */
	public function testShowUserNotLoggedIn() {
		$notLoggedInUser = new User();
		$testContext = new DerivativeContext( $this->watchAction->getContext() );
		$testContext->setUser( $notLoggedInUser );
		$watchAction = $this->getWatchAction(
			Article::newFromWikiPage( $this->testWikiPage, $testContext ),
			$testContext
		);
		$this->expectException( UserNotLoggedIn::class );

		$watchAction->show();
	}

	/**
	 * @covers WatchAction::checkCanExecute()
	 */
	public function testShowUserLoggedInNoException() {
		$registeredUser = $this->createMock( User::class );
		$registeredUser->method( 'isRegistered' )->willReturn( true );
		$testContext = new DerivativeContext( $this->watchAction->getContext() );
		$testContext->setUser( $registeredUser );
		$watchAction = $this->getWatchAction(
			Article::newFromWikiPage( $this->testWikiPage, $testContext ),
			$testContext
		);

		$exception = null;
		try {
			$watchAction->show();
		} catch ( UserNotLoggedIn $e ) {
			$exception = $e;
		}
		$this->assertNull( $exception,
			'UserNotLoggedIn exception should not be thrown if user is a registered one.' );
	}

	/**
	 * @covers WatchAction::onSuccess()
	 */
	public function testOnSuccessMainNamespaceTitle() {
		/** @var MockObject|IContextSource $testContext */
		$testContext = $this->getMockBuilder( DerivativeContext::class )
			->onlyMethods( [ 'msg' ] )
			->setConstructorArgs( [ $this->watchAction->getContext() ] )
			->getMock();
		$testOutput = new OutputPage( $testContext );
		$testContext->setOutput( $testOutput );
		$testContext->method( 'msg' )->willReturnCallback( static function ( $msgKey ) {
			return new RawMessage( $msgKey );
		} );
		$watchAction = $this->getWatchAction(
			Article::newFromWikiPage( $this->testWikiPage, $testContext ),
			$testContext
		);

		$watchAction->onSuccess();

		$this->assertEquals( '<p>addedwatchtext
</p>', $testOutput->getHTML() );
	}

	/**
	 * @covers WatchAction::onSuccess()
	 */
	public function testOnSuccessTalkPage() {
		/** @var MockObject|IContextSource $testContext */
		$testContext = $this->getMockBuilder( DerivativeContext::class )
			->onlyMethods( [ 'getOutput', 'msg' ] )
			->setConstructorArgs( [ $this->watchAction->getContext() ] )
			->getMock();
		$testOutput = new OutputPage( $testContext );
		$testContext->method( 'getOutput' )->willReturn( $testOutput );
		$testContext->method( 'msg' )->willReturnCallback( static function ( $msgKey ) {
			return new RawMessage( $msgKey );
		} );
		$talkPageTitle = Title::newFromText( 'Talk:UTTest' );
		$testContext->setTitle( $talkPageTitle );
		$watchAction = $this->getWatchAction(
			Article::newFromTitle( $talkPageTitle, $testContext ),
			$testContext
		);

		$watchAction->onSuccess();

		$this->assertEquals( '<p>addedwatchtext-talk
</p>', $testOutput->getHTML() );
	}

	/**
	 * @dataProvider provideOnSuccessDifferentMessages
	 */
	public function testOnSuccessDifferentMessages(
		$watchlistExpiry, $msg, $prefixedTitle, $submittedExpiry, $expiryLabel
	) {
		// Fake current time to be 2020-09-17 12:00:00 UTC.
		ConvertibleTimestamp::setFakeTime( '20200917120000' );

		// WatchlistExpiry feature flag.
		$this->setMwGlobals( 'wgWatchlistExpiry', $watchlistExpiry );

		// Set up context, request, and output.
		/** @var MockObject|IContextSource $testContext */
		$testContext = $this->getMockBuilder( DerivativeContext::class )
			->onlyMethods( [ 'getOutput', 'getRequest', 'getLanguage' ] )
			->setConstructorArgs( [ $this->watchAction->getContext() ] )
			->getMock();
		/** @var MockObject|OutputPage $testOutput */
		$testOutput = $this->createMock( OutputPage::class );
		$testOutput->expects( $this->once() )
			->method( 'addWikiMsg' )
			->with( $msg, $prefixedTitle, $expiryLabel );
		$testContext->method( 'getOutput' )->willReturn( $testOutput );
		// Set language to anything non-English/default, to catch assumptions.
		$langDe = $this->getServiceContainer()->getLanguageFactory()->getLanguage( 'de' );
		$testContext->method( 'getLanguage' )->willReturn( $langDe );
		/** @var MockObject|WebRequest $testRequest */
		$testRequest = $this->createMock( WebRequest::class );
		$testRequest->expects( $this->once() )
			->method( 'getText' )
			->willReturn( $submittedExpiry );
		$testContext->method( 'getRequest' )->willReturn( $testRequest );

		// Call the onSuccess method, and the above mocks will confirm it's correct.
		/** @var WatchAction $watchAction */
		$watchAction = TestingAccessWrapper::newFromObject(
			$this->getWatchAction(
				Article::newFromTitle( Title::newFromText( $prefixedTitle ), $testContext ),
				$testContext
			)
		);
		$watchAction->onSuccess();
	}

	public function provideOnSuccessDifferentMessages() {
		return [
			[
				'wgWatchlistExpiry' => false,
				'msg' => 'addedwatchtext',
				'prefixedTitle' => 'Foo',
				'submittedExpiry' => null,
				'expiryLabel' => null,
			],
			[
				'wgWatchlistExpiry' => false,
				'msg' => 'addedwatchtext-talk',
				'prefixedTitle' => 'Talk:Foo',
				'submittedExpiry' => null,
				'expiryLabel' => null,
			],
			[
				'wgWatchlistExpiry' => true,
				'msg' => 'addedwatchindefinitelytext',
				'prefixedTitle' => 'Foo',
				'submittedExpiry' => 'infinite',
				'expiryLabel' => 'Dauerhaft',
			],
			[
				'wgWatchlistExpiry' => true,
				'msg' => 'addedwatchindefinitelytext-talk',
				'prefixedTitle' => 'Talk:Foo',
				'submittedExpiry' => 'infinite',
				'expiryLabel' => 'Dauerhaft',
			],
			[
				'wgWatchlistExpiry' => true,
				'msg' => 'addedwatchexpirytext',
				'prefixedTitle' => 'Foo',
				'submittedExpiry' => '1 week',
				'expiryLabel' => '1 Woche',
			],
			[
				'wgWatchlistExpiry' => true,
				'msg' => 'addedwatchexpirytext-talk',
				'prefixedTitle' => 'Talk:Foo',
				'submittedExpiry' => '1 week',
				'expiryLabel' => '1 Woche',
			],
			[
				'wgWatchlistExpiry' => true,
				'msg' => 'addedwatchexpiryhours',
				'prefixedTitle' => 'Foo',
				'submittedExpiry' => '2020-09-17T14:00:00Z',
				'expiryLabel' => null,
			],
			[
				'wgWatchlistExpiry' => true,
				'msg' => 'addedwatchexpiryhours-talk',
				'prefixedTitle' => 'Talk:Foo',
				'submittedExpiry' => '2020-09-17T14:00:00Z',
				'expiryLabel' => null,
			],
		];
	}

	/**
	 * @covers WatchAction::getExpiryOptions()
	 */
	public function testGetExpiryOptions() {
		// Fake current time to be 2020-06-10T00:00:00Z
		ConvertibleTimestamp::setFakeTime( '20200610000000' );
		$userIdentity = new UserIdentityValue( 100, 'User Name' );
		$target = new TitleValue( 0, 'SomeDbKey' );

		$optionsNoExpiry = WatchAction::getExpiryOptions( $this->context, false );
		$expectedNoExpiry = [
			'options' => [
				'Permanent' => 'infinite',
				'1 week' => '1 week',
				'1 month' => '1 month',
				'3 months' => '3 months',
				'6 months' => '6 months'
			],
			'default' => 'infinite'
		];

		$this->assertSame( $expectedNoExpiry, $optionsNoExpiry );

		// Adding a watched item with an expiry a month from the frozen time
		$watchedItemMonth = new WatchedItem( $userIdentity, $target, null, '20200710000000' );
		$optionsExpiryOneMonth = WatchAction::getExpiryOptions( $this->context, $watchedItemMonth );
		$expectedExpiryOneMonth = [
			'options' => [
				'30 days left' => '2020-07-10T00:00:00Z',
				'Permanent' => 'infinite',
				'1 week' => '1 week',
				'1 month' => '1 month',
				'3 months' => '3 months',
				'6 months' => '6 months'
			],
			'default' => '2020-07-10T00:00:00Z'
		];

		$this->assertSame( $expectedExpiryOneMonth, $optionsExpiryOneMonth );

		// Adding a watched item with an expiry 7 days from the frozen time
		$watchedItemWeek = new WatchedItem( $userIdentity, $target, null, '20200617000000' );
		$optionsExpiryOneWeek = WatchAction::getExpiryOptions( $this->context, $watchedItemWeek );
		$expectedOneWeek = [
			'options' => [
				'7 days left' => '2020-06-17T00:00:00Z',
				'Permanent' => 'infinite',
				'1 week' => '1 week',
				'1 month' => '1 month',
				'3 months' => '3 months',
				'6 months' => '6 months'
			],
			'default' => '2020-06-17T00:00:00Z'
		];

		$this->assertSame( $expectedOneWeek, $optionsExpiryOneWeek );

		// Case for when WatchedItem is true
		$optionsNoExpiryWIFalse = WatchAction::getExpiryOptions( $this->context, true );
		$expectedNoExpiryWIFalse = [
			'options' => [
				'Permanent' => 'infinite',
				'1 week' => '1 week',
				'1 month' => '1 month',
				'3 months' => '3 months',
				'6 months' => '6 months'
			],
			'default' => 'infinite'
		];

		$this->assertSame( $expectedNoExpiryWIFalse, $optionsNoExpiryWIFalse );
	}

	/**
	 * @covers WatchAction::getExpiryOptions()
	 */
	public function testGetExpiryOptionsWithInvalidTranslations() {
		$mockMessageLocalizer = $this->createMock( MockMessageLocalizer::class );
		$mockLanguage = $this->createMock( Language::class );
		$mockLanguage->method( 'getCode' )->willReturn( 'not-english' );
		$mockMessage = $this->getMockMessage( 'invalid:invalid, foo:bar, thing' );
		$mockMessage->method( 'getLanguage' )->willReturn( $mockLanguage );

		$mockMessageLocalizer->expects( $this->exactly( 2 ) )
			->method( 'msg' )
			->will(
				$this->onConsecutiveCalls(
					$mockMessage,
					new Message( 'watchlist-expiry-options' )
				)
			);

		$expected = WatchAction::getExpiryOptions( new MockMessageLocalizer( 'en' ), false );
		$expiryOptions = WatchAction::getExpiryOptions( $mockMessageLocalizer, false );
		$this->assertSame( $expected, $expiryOptions );
	}

	/**
	 * @covers WatchAction::getExpiryOptions()
	 */
	public function testGetExpiryOptionsWithPartialInvalidTranslations() {
		$mockMessageLocalizer = $this->createMock( MockMessageLocalizer::class );
		$mockMessageLocalizer->expects( $this->once() )
			->method( 'msg' )
			->with( 'watchlist-expiry-options' )
			->willReturn( $this->getMockMessage( 'invalid:invalid, thing, 1 week: 1 week,3 days:3 days' ) );

		$expected = [
			'options' => [
				'1 week' => '1 week',
				'3 days' => '3 days',
			],
			'default' => '1 week'
		];
		$expiryOptions = WatchAction::getExpiryOptions( $mockMessageLocalizer, false );
		$this->assertSame( $expected, $expiryOptions );
	}

	private function setWatchedItemStore() {
		$this->overrideMwServices();
		// DummyServicesTrait::getDummyWatchedItemStore has all of the handling needed
		$mock = $this->getDummyWatchedItemStore();
		$this->setService( 'WatchedItemStore', $mock );
		return $mock;
	}
}
