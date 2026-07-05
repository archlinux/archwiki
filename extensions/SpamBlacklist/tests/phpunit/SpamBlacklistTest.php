<?php

use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\SpamBlacklist\BaseBlacklist;
use MediaWiki\Extension\SpamBlacklist\SpamBlacklist;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Article;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;

/**
 * @group SpamBlacklist
 * @group Database
 * @covers \MediaWiki\Extension\SpamBlacklist\SpamBlacklist
 */
class SpamBlacklistTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	/**
	 * @var SpamBlacklist
	 */
	protected $spamFilter;

	/**
	 * Spam blacklist regexes. Examples taken from:
	 *
	 * @see https://meta.wikimedia.org/wiki/Spam_blacklist
	 * @see https://en.wikipedia.org/wiki/MediaWiki:Spam-blacklist
	 *
	 * via Flow extension
	 *
	 * @var array
	 */
	protected $blacklist = [ '\b01bags\.com\b', 'sytes\.net' ];

	/**
	 * Spam whitelist regexes. Examples taken from:
	 *
	 * @see https://en.wikipedia.org/wiki/MediaWiki:Spam-whitelist
	 *
	 * via Flow extension
	 *
	 * @var array
	 */
	protected $whitelist = [ 'a5b\.sytes\.net' ];

	public static function spamProvider() {
		return [
			'no spam' => [
				[ 'https://example.com' ],
				false,
			],
			'revision with spam, with additional non-spam' => [
				[ 'https://foo.com', 'http://01bags.com', 'http://bar.com' ],
				[ '01bags.com' ],
			],

			'revision with spam using full width stop normalization' => [
				[ 'http://01bags．com' ],
				[ '01bags.com' ],
			],

			'revision with domain blacklisted as spam, but subdomain whitelisted' => [
				[ 'http://a5b.sytes.net' ],
				false,
			],
		];
	}

	/**
	 * @dataProvider spamProvider
	 */
	public function testSpamTempAccounts( $links, $expected ) {
		$this->enableAutoCreateTempUser();
		$this->prepareGlobals();
		$tempUserCreator = MediaWikiServices::getInstance()->getTempUserCreator();
		$user = $tempUserCreator->create(
			null,
			new FauxRequest()
		)->getUser();
		$returnValue = $this->spamFilter->filter(
			$links,
			Title::newMainPage(),
			$user
		);
		$this->assertEquals( $expected, $returnValue );
	}

	/**
	 * @dataProvider spamProvider
	 */
	public function testSpamAnonEditing( $links, $expected ) {
		$this->disableAutoCreateTempUser();
		$this->prepareGlobals();

		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newAnonymous();
		$returnValue = $this->spamFilter->filter(
			$links,
			Title::newMainPage(),
			$user
		);
		$this->assertEquals( $expected, $returnValue );
	}

	public static function spamEditProvider() {
		return [
			'no spam' => [
				'https://example.com',
				true,
			],
			'revision with spam, with additional non-spam' => [
				"https://foo.com\nhttp://01bags.com\nhttp://bar.com'",
				false,
			],

			'revision with domain blacklisted as spam, but subdomain whitelisted' => [
				'http://a5b.sytes.net',
				true,
			],
		];
	}

	/**
	 * @dataProvider spamEditProvider
	 */
	public function testSpamEdit( $text, $ok ) {
		$this->prepareGlobals();
		$fields = [
			'wpTextbox1' => $text,
			'wpUnicodeCheck' => EditPage::UNICODE_CHECK,
			'wpRecreate' => true,
		];

		$req = new FauxRequest( $fields, true );

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$title = $page->getTitle();

		$articleContext = new RequestContext;
		$articleContext->setRequest( $req );
		$articleContext->setWikiPage( $page );
		$articleContext->setUser( $this->getTestUser()->getUser() );

		$article = new Article( $title );
		$ep = new EditPage( $article );
		$ep->setContextTitle( $title );
		$ep->getContext()->setUser( $articleContext->getUser() );

		$ep->importFormData( $req );

		$status = $ep->attemptSave( $result );

		if ( $ok ) {
			$this->assertStatusOK( $status );
		} else {
			$this->assertStatusError( 'spam-blacklisted-link', $status );
		}
	}

	public function testLogFilterHitWhenLogHitsIsFalse() {
		$this->prepareGlobals();
		$this->overrideConfigValue( 'LogSpamBlacklistHits', false );

		$this->spamFilter->logFilterHit(
			$this->getTestUser()->getUser(),
			Title::newFromText( 'Test' ),
			'https://test.com'
		);

		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'logging' )
			->where( [ 'log_type' => 'spamblacklist' ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	/** @dataProvider provideLogFilterHit */
	public function testLogFilterHit( bool $checkUserInstalled ) {
		$this->prepareGlobals();
		$this->overrideConfigValue( 'LogSpamBlacklistHits', true );

		// If CheckUser is installed for this test case, then expect that the log entry is sent to be stored
		// in the CheckUser data tables. Otherwise, mock that it is not installed and expect no calls to do this
		$logIdFromRecentChange = null;
		if ( $checkUserInstalled ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

			$mockCheckUserInsert = $this->createMock( CheckUserInsert::class );
			$mockCheckUserInsert->expects( $this->once() )
				->method( 'updateCheckUserData' )
				->with( $this->callback( function ( $actualRecentChange ) use ( &$logIdFromRecentChange ) {
					$this->assertInstanceOf( RecentChange::class, $actualRecentChange );
					$logIdFromRecentChange = $actualRecentChange->getAttribute( 'rc_logid' );
					return true;
				} ) );
			$this->setService( 'CheckUserInsert', $mockCheckUserInsert );
		} else {
			$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
			$mockExtensionRegistry->method( 'isLoaded' )
				->with( 'CheckUser' )
				->willReturn( false );
			$this->setService( 'ExtensionRegistry', $mockExtensionRegistry );

			$serviceContainer = $this->getServiceContainer();
			if ( !$serviceContainer->hasService( 'CheckUserInsert' ) ) {
				// define as no-op and override afterwards to use MediaWikiIntegrationTestCase service reset
				$serviceContainer->defineService( 'CheckUserInsert', static fn () => null );
			}
			$this->setService(
				'CheckUserInsert',
				fn () => $this->fail( 'The CheckUserInsert service was expected to not be called' )
			);
		}

		$performer = $this->getTestUser()->getUser();
		$this->spamFilter->logFilterHit(
			$performer,
			Title::newFromText( 'Test' ),
			'https://test.com'
		);

		$this->newSelectQueryBuilder()
			->select( [ 'log_title', 'log_namespace', 'log_actor', 'log_params' ] )
			->from( 'logging' )
			->where( [ 'log_type' => 'spamblacklist', 'log_action' => 'hit' ] )
			->caller( __METHOD__ )
			->assertRowValue( [
				'Test',
				NS_MAIN,
				$performer->getActorId(),
				LogEntryBase::makeParamBlob( [ '4::url' => 'https://test.com' ] )
			] );

		if ( $logIdFromRecentChange !== null ) {
			$logId = $this->newSelectQueryBuilder()
				->select( 'log_id' )
				->from( 'logging' )
				->where( [ 'log_type' => 'spamblacklist', 'log_action' => 'hit' ] )
				->caller( __METHOD__ )
				->fetchField();
			$this->assertSame(
				(int)$logId,
				$logIdFromRecentChange,
				'Log ID in the RecentChange object passed to CheckUser differs to the log in the DB'
			);
		}
	}

	public static function provideLogFilterHit(): array {
		return [
			'CheckUser is installed' => [ true ],
			'CheckUser is not installed' => [ false ],
		];
	}

	private function prepareGlobals(): void {
		$this->overrideConfigValue( 'BlacklistSettings', [
			'files' => [],
		] );

		BaseBlacklist::clearInstanceCache();

		// create spam filter
		$this->spamFilter = new SpamBlacklist;

		MediaWikiServices::getInstance()->getMessageCache()->enable();
		$this->insertPage( 'MediaWiki:Spam-blacklist', implode( "\n", $this->blacklist ) );
		$this->insertPage( 'MediaWiki:Spam-whitelist', implode( "\n", $this->whitelist ) );

		// That only works if the spam blacklist is really reset
		$instance = BaseBlacklist::getInstance( 'spam' );
		$reflProp = new ReflectionProperty( $instance, 'regexes' );
		$reflProp->setValue( $instance, false );
	}

	protected function tearDown(): void {
		MediaWikiServices::getInstance()->getMessageCache()->disable();
		parent::tearDown();
	}
}
