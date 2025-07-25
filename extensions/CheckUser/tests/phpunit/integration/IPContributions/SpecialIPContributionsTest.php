<?php

namespace MediaWiki\CheckUser\Tests\Integration\IPContributions;

use DOMDocument;
use DOMXPath;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use SpecialPageTestBase;
use Wikimedia\IPUtils;

/**
 * @covers \MediaWiki\CheckUser\IPContributions\SpecialIPContributions
 * @covers \MediaWiki\CheckUser\IPContributions\IPContributionsPager
 * @group CheckUser
 * @group Database
 */
class SpecialIPContributionsTest extends SpecialPageTestBase {

	use CheckUserTempUserTestTrait;

	private static User $disallowedUser;
	private static User $checkuser;
	private static User $sysop;
	private static User $checkuserAndSysop;

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'IPContributions' );
	}

	protected function setup(): void {
		$this->enableAutoCreateTempUser();
		parent::setup();
	}

	/**
	 * Add a temporary user and a fully registered user who contributed
	 * from the same IP address. This is important to ensure we don't
	 * leak that the fully registered user edited from that IP.
	 *
	 * Also add contributions from multiple temp users and from multiple
	 * IPs, to test ranges.
	 */
	public function addDBDataOnce() {
		$this->enableAutoCreateTempUser();

		// The users must be created now because the actor table will
		// be altered when the edits are made, and added to the list
		// of tables that can't be altered again in $dbDataOnceTables.
		self::$disallowedUser = static::getTestUser()->getUser();
		self::$checkuser = static::getTestUser( [ 'checkuser' ] )->getUser();
		self::$sysop = static::getTestSysop()->getUser();
		self::$checkuserAndSysop = static::getTestUser( [ 'checkuser', 'sysop' ] )->getUser();

		$temp1 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-01', new FauxRequest() )->getUser();
		$temp2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-2024-02', new FauxRequest() )->getUser();

		// Named user and 2 temp users edit from the first IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		$this->editPage(
			'Test page', 'Test Content 1', 'test', NS_MAIN, self::$sysop
		);
		$this->editPage(
			'Test page', 'Test Content 2', 'test', NS_MAIN, $temp1
		);
		$this->editPage(
			'Test page', 'Test Content 3', 'test', NS_MAIN, $temp2
		);

		$this->editPage(
			'Test page for deletion', 'Test Content', 'test', NS_MAIN, $temp1
		);
		$title = Title::newFromText( 'Test page for deletion' );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$this->deletePage( $page );

		// Temp user edits again from a different IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			'Test page', 'Test Content 4', 'test', NS_MAIN, $temp1
		);
	}

	/**
	 * @dataProvider provideTargets
	 */
	public function testExecuteTarget( $target, $expectedCount ) {
		[ $html ] = $this->executeSpecialPage(
			$target,
			null,
			null,
			self::$checkuser
		);

		// Target field should be populated
		$this->assertStringContainsString( $target, $html );

		if ( $expectedCount > 0 ) {
			$this->assertStringContainsString( 'mw-pager-body', $html );
			// Use occurrences of data attribute in to determine how many rows,
			// to test pager.
			$this->assertSame( $expectedCount, substr_count( $html, 'data-mw-revid' ) );
			$this->runJobs();
			// Test that a log entry was inserted for the viewing of this target.
			$this->assertSame(
				1,
				$this->getDb()->newSelectQueryBuilder()
					->from( 'logging' )
					->where( [
						'log_type' => TemporaryAccountLogger::LOG_TYPE,
						'log_action' => TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP,
						'log_actor' => self::$checkuser->getActorId(),
						'log_namespace' => NS_USER,
						'log_title' => IPUtils::prettifyIP( IPUtils::sanitizeRange( $target ) ),
					] )
					->fetchRowCount()
			);
		} else {
			$this->assertStringNotContainsString( 'mw-pager-body', $html );
		}
	}

	public static function provideTargets() {
		return [
			'Empty target' => [ '', 0 ],
			'Valid IP' => [ '127.0.0.1', 2 ],
			'Valid range' => [ '127.0.0.1/24', 3 ],
			'Temp user' => [ '~check-user-test-2024-1', 0 ],
			'Nonexistent user' => [ 'Nonexistent', 0 ],
		];
	}

	public function testExecuteWideRange() {
		// Ensure the range restriction comes from $wgRangeContributionsCIDRLimit,
		// not $wgCheckUserCIDRLimit
		$this->overrideConfigValue( 'CheckUserCIDRLimit', [ 'IPv4' => 1, 'IPv6' => 1 ] );
		$this->overrideConfigValue( 'RangeContributionsCIDRLimit', [ 'IPv4' => 17, 'IPv6' => 20 ] );

		[ $html ] = $this->executeSpecialPage(
			'127.0.0.1/1',
			null,
			null,
			self::$checkuser
		);

		$this->assertStringNotContainsString( 'mw-pager-body', $html );
		$this->assertStringContainsString( 'sp-contributions-outofrange', $html );
	}

	public function testExecuteNotIP() {
		// Block our test user so that normally a block log extract would be shown on the special page
		$this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser( self::$disallowedUser->getName(), self::$sysop, 'indefinite' )
			->placeBlock();

		[ $html ] = $this->executeSpecialPage(
			self::$disallowedUser->getName(),
			null,
			'qqx',
			self::$checkuser,
			true
		);

		$this->assertSame( 0, substr_count( $html, 'data-mw-revid' ) );
		$this->assertStringContainsString( 'checkuser-ip-contributions-target-error-no-ip-banner', $html );
		$this->assertStringNotContainsString(
			'sp-contributions-blocked', $html, 'No block log extract should be shown on an error page'
		);
	}

	public function testExecuteArchive() {
		[ $html ] = $this->executeSpecialPage(
			'127.0.0.1',
			new FauxRequest( [ 'isArchive' => '1' ] ),
			null,
			self::$checkuserAndSysop
		);

		$this->assertSame( 1, substr_count( $html, 'data-mw-revid' ) );
	}

	public function testExecuteErrorPreference() {
		$this->expectException( ErrorPageError::class );

		$this->executeSpecialPage(
			'',
			null,
			null,
			self::$sysop
		);
	}

	public function testExecuteErrorRevealIpPermission() {
		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage(
			'',
			null,
			null,
			self::$disallowedUser
		);
	}

	public function testExecuteErrorArchivePermission() {
		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage(
			'',
			new FauxRequest( [ 'isArchive' => '1' ] ),
			null,
			self::$checkuser
		);
	}

	public function testExecuteErrorBlock() {
		$this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				self::$checkuser->getName(),
				self::$sysop,
				'infinity'
			)
			->placeBlock();
		$this->expectException( UserBlockedError::class );

		$this->executeSpecialPage(
			'',
			null,
			null,
			self::$checkuser
		);
	}

	public function testUserCanExecute(): void {
		$this->assertTrue(
			$this->newSpecialPage()->userCanExecute( self::$checkuser )
		);
		$this->assertFalse(
			$this->newSpecialPage()->userCanExecute( self::$disallowedUser )
		);
	}

	public function testLinksToHelpPage(): void {
		// Ensure that the ipcontributions-helppage message is disabled,
		// so we can test the URL we provided without an override affecting it.
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'en' );
		$this->editPage(
			Title::newFromText( 'ipcontributions-helppage', NS_MEDIAWIKI ),
			'-'
		);
		$this->getServiceContainer()->getMessageCache()->enable();

		// Load the special page using English, as using the qqx language means
		// that the help message isn't disabled.
		[ $html ] = $this->executeSpecialPage(
			'127.0.0.1',
			null,
			'en',
			self::$checkuser,
			true
		);

		$doc = new DOMDocument();
		$doc->loadHTML( $html, LIBXML_NOERROR );
		$entries = ( new DOMXpath( $doc ) )->query(
			'//div[@id="mw-indicator-mw-helplink"]/a[@class="mw-helplink"]'
		);

		$this->assertNotEmpty( $entries );
		$this->assertEquals(
			"https://www.mediawiki.org/wiki/Special:MyLanguage/" .
				"Help:Extension:CheckUser#Special:IPContributions_usage",
			$entries[ 0 ]->getAttribute( 'href' )
		);
	}
}
