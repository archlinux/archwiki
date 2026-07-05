<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthLogger
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 * @group Database
 */
class OATHAuthLoggerTest extends MediaWikiIntegrationTestCase {

	public function testLogImplicitVerification(): void {
		$logger = OATHAuthServices::getInstance()->getLogger();

		$this->assertNoLogs( 'verify' );

		$performer = $this->getTestSysop()->getUser();
		$target = $this->getTestUser()->getUser();
		$logger->logImplicitVerification( $performer, $target );

		$this->assertSingleLogRow( 'verify', $performer, $target );
	}

	public function testLogImplicitVerification_CheckUser(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$logger = OATHAuthServices::getInstance()->getLogger();

		$performer = $this->getTestSysop()->getUser();
		$target = $this->getTestUser()->getUser();

		// Creating test users may insert some CU logs, so we expect that the total count increases by one,
		// but we don't operate on actual numbers.
		$countBefore = $this->countCuleRows();

		$logger->logImplicitVerification( $performer, $target );

		$countAfter = $this->countCuleRows();

		$this->assertSame( 1, $countAfter - $countBefore );
	}

	public function testLogOATHRecovery(): void {
		$logger = OATHAuthServices::getInstance()->getLogger();

		$this->assertNoLogs( 'recover' );

		$performer = $this->getTestSysop()->getUser();
		$target = $this->getTestUser()->getUser();
		$logger->logOATHRecovery( $performer, $target, 'comment', 1 );

		$this->assertSingleLogRow( 'recover', $performer, $target, [ '4::count' => 1 ] );
	}

	public function testLogOATHRecovery_CheckUser(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$logger = OATHAuthServices::getInstance()->getLogger();

		$performer = $this->getTestSysop()->getUser();
		$target = $this->getTestUser()->getUser();

		// Creating test users may insert some CU logs, so we expect that the total count increases by one,
		// but we don't operate on actual numbers.
		$countBefore = $this->countCuleRows();

		$logger->logOATHRecovery( $performer, $target, 'reason', 1 );

		$countAfter = $this->countCuleRows();

		$this->assertSame( 1, $countAfter - $countBefore );
	}

	public function testLogFailedVerification(): void {
		$logger = OATHAuthServices::getInstance()->getLogger();

		$this->assertNoLogs( 'verify-failed' );

		$user = $this->getTestUser()->getUser();
		$logger->logFailedVerification( $user );

		$this->assertNoLogs( 'verify-failed' );
	}

	public function testLogFailedVerification_CheckUser(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$logger = OATHAuthServices::getInstance()->getLogger();

		$user = $this->getTestUser()->getUser();
		$countBefore = $this->countCuPrivateRows();

		$logger->logFailedVerification( $user );

		$countAfter = $this->countCuPrivateRows();

		$this->assertSame( 1, $countAfter - $countBefore );
	}

	public function testLogSuccessfulVerification(): void {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->with( 'CheckUser' )
			->willReturn( false );
		$logger = new OATHAuthLogger(
			$extensionRegistry,
			RequestContext::getMain(),
			LoggerFactory::getInstance( 'authentication' )
		);

		$this->assertNoLogs( 'verify-success' );

		$user = $this->getTestUser()->getUser();
		$logger->logSuccessfulVerification( $user );

		$this->assertNoLogs( 'verify-success' );
	}

	public function testLogSuccessfulVerification_CheckUser(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$logger = OATHAuthServices::getInstance()->getLogger();

		$user = $this->getTestUser()->getUser();
		$countBefore = $this->countCuPrivateRows();

		$logger->logSuccessfulVerification( $user );

		$countAfter = $this->countCuPrivateRows();

		$this->assertSame( 1, $countAfter - $countBefore );
	}

	private function assertNoLogs( string $subtype ): void {
		$this->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->where( [ 'log_type' => 'oath', 'log_action' => $subtype ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	private function assertSingleLogRow(
		string $subtype,
		UserIdentity $performer,
		UserIdentity $target,
		array $params = []
	): void {
		$rows = DatabaseLogEntry::newSelectQueryBuilder( $this->getDb() )
			->where( [ 'log_type' => 'oath', 'log_action' => $subtype ] )
			->limit( 2 )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->assertCount( 1, $rows, 'Expected exactly one log entry' );
		$row = $rows->fetchRow();
		$logEntry = DatabaseLogEntry::newFromRow( $row );
		$this->assertSame( $performer->getId(), $logEntry->getPerformerIdentity()->getId() );
		$this->assertSame( NS_USER, $logEntry->getTarget()->getNamespace() );
		$this->assertSame( $target->getName(), $logEntry->getTarget()->getText() );

		if ( $params ) {
			$logParams = $logEntry->getParameters();
			$this->assertArrayContains( $params, $logParams );
		}
	}

	private function countCuleRows(): int {
		return $this->newSelectQueryBuilder()
			->select( 'cule_id' )
			->from( 'cu_log_event' )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	private function countCuPrivateRows(): int {
		return $this->newSelectQueryBuilder()
			->select( 'cupe_id' )
			->from( 'cu_private_event' )
			->caller( __METHOD__ )
			->fetchRowCount();
	}
}
