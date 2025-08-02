<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser;

use MediaWiki\CheckUser\CheckUser\SpecialCheckUserLog;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use SpecialPageTestBase;
use TestUser;
use Wikimedia\IPUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for SpecialCheckUserLog class and the CheckUserLogPager.
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUserLog
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserLogPager
 */
class SpecialCheckUserLogTest extends SpecialPageTestBase {

	private static User $testCheckUser;
	private static User $blockedCheckUser;
	private static User $firstTestUser;
	private static User $secondTestUser;
	private static User $testSuppressor;

	protected function newSpecialPage(): SpecialCheckUserLog {
		/** @var SpecialCheckUserLog */
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CheckUserLog' );
	}

	/**
	 * @dataProvider provideRequiredGroupAccess
	 */
	public function testRequiredRightsByGroup( $groups, $allowed ) {
		$checkUserLog = $this->newSpecialPage();
		$requiredRight = $checkUserLog->getRestriction();
		if ( !is_array( $groups ) ) {
			$groups = [ $groups ];
		}
		$rightsGivenInGroups = $this->getServiceContainer()->getGroupPermissionsLookup()
			->getGroupPermissions( $groups );
		if ( $allowed ) {
			$this->assertContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should allow it to access the CheckUserLog.'
			);
		} else {
			$this->assertNotContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should not include access to the CheckUserLog.'
			);
		}
	}

	public static function provideRequiredGroupAccess() {
		return [
			'No user groups' => [ '', false ],
			'Checkuser only' => [ 'checkuser', true ],
			'Checkuser and sysop' => [ [ 'checkuser', 'sysop' ], true ],
		];
	}

	/**
	 * @dataProvider provideRequiredRights
	 */
	public function testRequiredRights( $groups, $allowed ) {
		if ( ( is_array( $groups ) && isset( $groups['checkuser-log'] ) ) || $groups === "checkuser-log" ) {
			$this->setGroupPermissions(
				[ 'checkuser-log' => [ 'checkuser-log' => true, 'read' => true ] ]
			);
		}
		$this->testRequiredRightsByGroup( $groups, $allowed );
	}

	public static function provideRequiredRights() {
		return [
			'No user groups' => [ '', false ],
			'checkuser-log right only' => [ 'checkuser-log', true ],
		];
	}

	/**
	 * Gets a test user with the checkuser group and also assigns that user as the user for the main context.
	 *
	 * @return User
	 */
	private function getTestCheckUser(): User {
		$testCheckUser = self::$testCheckUser;
		RequestContext::getMain()->setUser( $testCheckUser );
		return $testCheckUser;
	}

	public function testLoadSpecialPageWhileBlocked() {
		$this->expectException( UserBlockedError::class );
		$testCheckUser = self::$blockedCheckUser;
		RequestContext::getMain()->setUser( $testCheckUser );
		// Execute the special page which should throw a UserBlockedError.
		$this->executeSpecialPage( '', new FauxRequest(), null, $testCheckUser );
	}

	public function testLoadSpecialPageWithNoFilters() {
		// Execute the special page. We need the full HTML to verify the subtitle links.
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $this->getTestCheckUser(), true );
		// Verify that the HTML includes the form fields needed to filter logs.
		$this->assertStringContainsString( '(checkuser-log-search-target', $html );
		$this->assertStringContainsString( '(checkuser-log-search-initiator', $html );
		$this->assertStringContainsString( '(checkuser-log-search-reason', $html );
		$this->assertStringContainsString( '(date-range-from', $html );
		$this->assertStringContainsString( '(date-range-to', $html );
		// Verify that all checks are shown if no filters are applied
		$this->assertStringContainsString( '(checkuser-log-entry-userips', $html );
		$this->assertStringContainsString( '(checkuser-log-entry-ipusers', $html );
		$this->assertStringContainsString( '(checkuser-log-entry-useractions', $html );
		$this->assertStringContainsString( '(checkuser-log-entry-ipactions', $html );
		$this->assertStringContainsString( '(checkuser-log-entry-ipactions-xff', $html );
		// Verify that all the check reasons are shown
		foreach ( range( 0, 6 ) as $i ) {
			$this->assertStringContainsString( "Check $i", $html );
		}
		// Verify that the subtitle links were correctly generated
		$this->assertStringContainsString( '(checkuser-showmain', $html );
		$this->assertStringContainsString( '(checkuser-show-investigate', $html );
		$this->assertStringNotContainsString( '(checkuser-check-this-user', $html );
		$this->assertStringNotContainsString( '(checkuser-investigate-this-user', $html );
	}

	public function testLoadSpecialPageWithTargetAndInitiatorFilter() {
		$request = new FauxRequest();
		$request->setVal( 'cuInitiator', self::$testCheckUser );
		$request->setVal( 'cuSearch', self::$firstTestUser );
		// Execute the special page. We need the full HTML to verify the subtitle links.
		[ $html ] = $this->executeSpecialPage( '', $request, null, $this->getTestCheckUser(), true );
		// Verify that logs only performed on the $firstTestUser are shown and performed by $testCheckUser
		foreach ( range( 0, 6 ) as $i ) {
			if ( in_array( $i, [ 2, 0 ] ) ) {
				$this->assertStringContainsString( "Check $i", $html );
			} else {
				$this->assertStringNotContainsString( "Check $i", $html );
			}
		}
	}

	public function testLoadSpecialPageForInvalidInitiator() {
		$request = new FauxRequest();
		$request->setVal( 'cuInitiator', 'InvalidUser' );
		// Execute the special page.
		[ $html ] = $this->executeSpecialPage( '', $request, null, $this->getTestCheckUser() );
		// Verify that an error is displayed indicating that the initiator is invalid
		$this->assertStringContainsString( "(checkuser-initiator-nonexistent", $html );
	}

	public function testLoadSpecialPageForInvalidTarget() {
		$request = new FauxRequest();
		$request->setVal( 'cuSearch', 'InvalidUser' );
		// Execute the special page.
		[ $html ] = $this->executeSpecialPage( '', $request, null, $this->getTestCheckUser(), true );
		// Verify that an error is displayed indicating that the target is invalid
		$this->assertStringContainsString( "(checkuser-target-nonexistent", $html );
	}

	public function testLoadSpecialPageForNoResults() {
		// Set the cuSearch parameter as an IP which has never been checked.
		$request = new FauxRequest();
		$request->setVal( 'cuSearch', '9.8.7.6' );
		// Execute the special page.
		[ $html ] = $this->executeSpecialPage( '', $request, null, $this->getTestCheckUser(), true );
		// Verify that the empty body message is displayed
		$this->assertStringContainsString( "(checkuser-empty", $html );
	}

	public function testLoadSpecialPageWithReasonFilter() {
		// Set the cuReasonSearch parameter to a reason used in one of the added logs with some wikitext that should be
		// ignored when searching for the reason.
		$request = new FauxRequest();
		$request->setVal( 'cuReasonSearch', '[[Check]] 3' );
		// Execute the special page.
		[ $html ] = $this->executeSpecialPage( '', $request, null, $this->getTestCheckUser(), true );
		// Verify that the correct check was displayed.
		foreach ( range( 0, 6 ) as $i ) {
			if ( $i === 3 ) {
				$this->assertStringContainsString( "Check $i", $html );
			} else {
				$this->assertStringNotContainsString( "Check $i", $html );
			}
		}
	}

	public function testLoadSpecialPageWithHighlightSet() {
		// Set the cuReasonSearch parameter to a reason used in one of the added logs with some wikitext that should be
		// ignored when searching for the reason.
		$request = new FauxRequest();
		$request->setVal( 'highlight', $this->getDb()->timestamp( '20240504030206' ) );
		// Execute the special page.
		[ $html ] = $this->executeSpecialPage( '', $request, null, $this->getTestCheckUser(), true );
		// Verify that one log entry has the highlight class
		$this->assertStringContainsString( 'mw-checkuser-log-highlight-entry', $html );
	}

	public function addDBDataOnce() {
		// Create two test users that will be referenced in the tests. These are constructed here to avoid creating the
		// users on each test.
		$firstTestUser = ( new TestUser( 'CheckUserLogTestUser1' ) )->getUser();
		$secondTestUser = ( new TestUser( 'CheckUserLogTestUser1' ) )->getUser();
		// Store some testing users for the tests to use to avoid them needing to call ::getTestUser and then
		// potentially causing the users table to be truncated.
		self::$testCheckUser = $this->getTestUser( [ 'checkuser', 'sysop' ] )->getUser();
		self::$blockedCheckUser = $this->getTestUser( [ 'checkuser' ] )->getUser();
		self::$firstTestUser = $firstTestUser;
		self::$secondTestUser = $secondTestUser;
		self::$testSuppressor = $this->getTestUser( [ 'suppress', 'sysop' ] )->getUser();
		// Actually block the blocked checkuser user
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				self::$blockedCheckUser,
				self::$testSuppressor,
				'infinity',
				'block to test user cannot view log if blocked',
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		// Create a few testing cu_log entries using the CheckUserLogService
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$logEntryData = [
			[
				'target' => self::$firstTestUser,
				'initiator' => self::$testCheckUser,
				'logType' => 'userips',
				'timestamp' => '20240504030201',
			],
			[
				'target' => UserIdentityValue::newAnonymous( '127.0.3.4' ),
				'initiator' => self::$testCheckUser,
				'logType' => 'ipusers',
				'timestamp' => '20240504030202',
			],
			[
				'target' => self::$secondTestUser,
				'initiator' => self::$testCheckUser,
				'logType' => 'useredits',
				'timestamp' => '20240504030203',
			],
			[
				'target' => UserIdentityValue::newAnonymous( '127.0.4.3' ),
				'initiator' => self::$testCheckUser,
				'logType' => 'ipedits',
				'timestamp' => '20240504030204',
			],
			[
				'target' => UserIdentityValue::newAnonymous( '1.2.3.4' ),
				'initiator' => self::$blockedCheckUser,
				'logType' => 'ipedits-xff',
				'timestamp' => '20240504030205',
			],
			[
				'target' => self::$firstTestUser,
				'initiator' => self::$blockedCheckUser,
				'logType' => 'ipedits-xff',
				'timestamp' => '20240504030206',
			],
			[
				'target' => self::$secondTestUser,
				'initiator' => self::$blockedCheckUser,
				'logType' => 'investigate',
				'timestamp' => '20240504030207',
			],
		];
		foreach ( $logEntryData as $i => $data ) {
			ConvertibleTimestamp::setFakeTime( $data['timestamp'] );
			/** @var UserIdentity $target */
			$target = $data['target'];
			$targetType = IPUtils::isIPAddress( $target->getName() ) ? 'ip' : 'user';
			$checkUserLogService->addLogEntry(
				$data['initiator'], $data['logType'], $targetType, $target->getName(), "Check $i", $target->getId()
			);
		}
		ConvertibleTimestamp::setFakeTime( false );
	}
}
