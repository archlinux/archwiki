<?php

namespace MediaWiki\CheckUser\Tests\Integration\Logging;

use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\Logging\TemporaryAccountLogger
 * @covers \MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob
 * @group CheckUser
 * @group Database
 */
class TemporaryAccountLoggerTest extends MediaWikiIntegrationTestCase {
	use TempUserTestTrait;

	public function testLogViewIPs() {
		// Make temporary accounts generate with a space in their username to test that spaces don't cause issues
		// with log-deduplication (T389854).
		$this->enableAutoCreateTempUser( [ 'genPattern' => '~ $1' ] );

		/** @var TemporaryAccountLogger $logger */
		$logger = $this->getServiceContainer()->get( 'CheckUserTemporaryAccountLoggerFactory' )->getLogger();

		// Call the method under test once
		$performer = $this->getTestSysop()->getUser();
		$tempUser = $this->getServiceContainer()->getTempUserCreator()->create( null, new FauxRequest() )->getUser();
		$logger->logViewIPs(
			$performer, $tempUser->getName(), ConvertibleTimestamp::convert( TS_UNIX, '20240405060709' )
		);

		// Check that the call to the method under test caused one log entry with the correct parameters
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_timestamp' => $this->getDb()->timestamp( '20240405060709' ),
				'log_actor' => $this->getServiceContainer()->getActorStore()
					->findActorId( $performer, $this->getDb() ),
				'log_title' => $tempUser->getUserPage()->getDBkey(),
				'log_namespace' => NS_USER,
			] )
			->caller( __METHOD__ )
			->assertFieldValue( 1 );

		// Call the method under test again to check that the code properly debounces the log entry.
		$logger->logViewIPs(
			$performer, $tempUser->getName(), ConvertibleTimestamp::convert( TS_UNIX, '20240405060711' )
		);
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'logging' )
			->where( [ 'log_timestamp' => $this->getDb()->timestamp( '20240405060711' ) ] )
			->assertEmptyResult();
	}

	/** @dataProvider provideLogViewTemporaryAccountsOnIP */
	public function testLogViewTemporaryAccountsOnIP( $targetIP ) {
		ConvertibleTimestamp::setFakeTime( '20240405060709' );
		$performer = $this->getTestSysop()->getUser();
		// Call the method under test using the LogTemporaryAccountAccessJob to do this for us.
		$this->getServiceContainer()->getJobQueueGroup()->push(
			new JobSpecification(
				'checkuserLogTemporaryAccountAccess',
				[
					'performer' => $performer->getName(), 'target' => $targetIP, 'timestamp' => (int)wfTimestamp(),
					'type' => TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP,
				]
			)
		);
		$this->getServiceContainer()->getJobRunner()->run( [ 'type' => 'checkuserLogTemporaryAccountAccess' ] );
		// Verify that a log exists with the correct title, type and performer.
		$this->assertSame(
			1,
			$this->getDb()->newSelectQueryBuilder()
				->from( 'logging' )
				->where( [
					'log_type' => TemporaryAccountLogger::LOG_TYPE,
					'log_action' => TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP,
					'log_actor' => $performer->getActorId(),
					'log_namespace' => NS_USER,
					'log_title' => IPUtils::prettifyIP( $targetIP ),
					'log_timestamp' => $this->getDb()->timestamp( '20240405060709' ),
				] )
				->fetchRowCount(),
			'The expected log was not written to the database.'
		);
		// Call the method under test again (directly this time to avoid testing extra code again) with the same
		// parameters and verify that no new log is created, because it is debounced.
		ConvertibleTimestamp::setFakeTime( '20240405060711' );
		/** @var TemporaryAccountLogger $logger */
		$logger = $this->getServiceContainer()->get( 'CheckUserTemporaryAccountLoggerFactory' )->getLogger();
		$logger->logViewTemporaryAccountsOnIP( $performer, $targetIP, (int)wfTimestamp() );
		$this->assertSame(
			0,
			$this->getDb()->newSelectQueryBuilder()
				->from( 'logging' )
				->where( [ 'log_timestamp' => $this->getDb()->timestamp( '20240405060711' ) ] )
				->fetchRowCount(),
			'The expected log was not written to the database.'
		);
	}

	public static function provideLogViewTemporaryAccountsOnIP() {
		return [
			'Viewed temporary accounts on single IP' => [ '1.2.3.4' ],
			'Viewed temporary accounts on IP range' => [ '1.2.3.0/24' ],
		];
	}

	/**
	 * @dataProvider provideTestLogAccessChanged
	 */
	public function testLogAccessChanged( $logMethod, $expectedAction ) {
		$user = $this->getTestUser()->getUser();
		$logger = $this->getServiceContainer()->get( 'CheckUserTemporaryAccountLoggerFactory' )->getLogger();
		$logger->$logMethod( $user );

		$result = $this->getDb()->newSelectQueryBuilder()
			->select( 'log_params' )
			->from( 'logging' )
			->where( [
				'log_type' => 'checkuser-temporary-account',
				'log_title' => $user->getUserPage()->getDBkey(),
				'log_namespace' => NS_USER,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->assertSame( 1, $result->numRows() );
		$result->rewind();

		$this->assertArrayEquals(
			[ '4::changeType' => $expectedAction ],
			LogEntryBase::extractParams( $result->fetchRow()['log_params'] ),
			false, true
		);
	}

	public static function provideTestLogAccessChanged() {
		return [
			'Local access enabled' => [
				'logAccessEnabled',
				TemporaryAccountLogger::ACTION_ACCESS_ENABLED
			],
			'Local access disabled' => [
				'logAccessDisabled',
				TemporaryAccountLogger::ACTION_ACCESS_DISABLED
			],
			'Global access enabled' => [
				'logGlobalAccessEnabled',
				TemporaryAccountLogger::ACTION_GLOBAL_ACCESS_ENABLED
			],
			'Global access disabled' => [
				'logGlobalAccessDisabled',
				TemporaryAccountLogger::ACTION_GLOBAL_ACCESS_DISABLED
			],
		];
	}

	public function testLogAutoRevealEnabled() {
		ConvertibleTimestamp::setFakeTime( '20240405060709' );
		$expiry = ConvertibleTimestamp::time() + 1800;
		$logger = $this->getServiceContainer()->get( 'CheckUserTemporaryAccountLoggerFactory' )->getLogger();
		$logger->logAutoRevealAccessEnabled( $this->getTestUser()->getUserIdentity(), $expiry );

		$row = $this->getDb()->newSelectQueryBuilder()
			->select( 'log_params' )
			->from( 'logging' )
			->where( [ 'log_type' => 'checkuser-temporary-account' ] )
			->caller( __METHOD__ )
			->fetchRow();

		$this->assertStringContainsString( TemporaryAccountLogger::ACTION_AUTO_REVEAL_ENABLED, $row->log_params );
		$this->assertStringContainsString( $expiry, $row->log_params );
	}

	public function testLogAutoRevealDisabled() {
		$logger = $this->getServiceContainer()->get( 'CheckUserTemporaryAccountLoggerFactory' )->getLogger();
		$logger->logAutoRevealAccessDisabled( $this->getTestUser()->getUserIdentity() );

		$row = $this->getDb()->newSelectQueryBuilder()
			->select( 'log_params' )
			->from( 'logging' )
			->where( [ 'log_type' => 'checkuser-temporary-account' ] )
			->caller( __METHOD__ )
			->fetchRow();

		$this->assertStringContainsString( TemporaryAccountLogger::ACTION_AUTO_REVEAL_DISABLED, $row->log_params );
	}
}
