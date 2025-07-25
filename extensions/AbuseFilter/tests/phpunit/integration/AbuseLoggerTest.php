<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseLogger
 * @group Database
 */
class AbuseLoggerTest extends MediaWikiIntegrationTestCase {
	use FilterFromSpecsTestTrait;

	/**
	 * Tests that the AbuseFilter logs are sent to CheckUser, and that CheckUser actually inserts the row to the
	 * cu_private_event table.
	 */
	public function testSendingLogsToCheckUser() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		AbuseFilterServices::getAbuseLoggerFactory()->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$this->getTestUser()->getUser(),
			VariableHolder::newFromArray( [ 'action' => 'edit' ] )
		)->addLogEntries( [ 1 => [ 'warn' ] ] );
		// Sending the log to CheckUser happens on a DeferredUpdate, so we need to run the updates.
		DeferredUpdates::doUpdates();
		// Assert that an insert into the cu_private_event table occurred for the abusefilter hit.
		// This may be a bit brittle if the cu_private_event table schema changes, but as AbuseFilter is
		// a dependency of CheckUser in CI and the columns checked are unlikely to change, this is acceptable.
		$this->assertSame(
			1,
			(int)$this->getDb()->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'cu_private_event' )
				->where( [ 'cupe_log_type' => 'abusefilter', 'cupe_log_action' => 'hit' ] )
				->fetchField()
		);
	}

	public function testVarDumpExcludesProtectedVariablesNotUsedByAssociatedFilter() {
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );

		// Call our method under test to create two log entries, one for a filter that uses user_unnamed_ip and the
		// other which does not.
		$generatedLogIds = AbuseFilterServices::getAbuseLoggerFactory()->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$this->getTestUser()->getUser(),
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_type' => 'temp',
				'user_unnamed_ip' => '1.2.3.4',
				'user_name' => '~2024-1',
			] )
		)->addLogEntries( [ 1 => [ 'warn' ], 2 => [ 'warn' ] ] );
		$this->assertCount( 2, $generatedLogIds['local'] );

		/** @var VariablesBlobStore $variablesBlobStore */
		$variablesBlobStore = $this->getServiceContainer()->get( VariablesBlobStore::SERVICE_NAME );

		// Assert that the log for the first filter has all the variables used in the first filter.
		$logsForFirstFilter = $this->newSelectQueryBuilder()
			->select( [ 'afl_var_dump', 'afl_ip' ] )
			->from( 'abuse_filter_log' )
			->where( [ 'afl_filter_id' => 1 ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertSame( 1, $logsForFirstFilter->numRows() );
		$logsForFirstFilter->rewind();
		$varDumpForFirstLog = $variablesBlobStore->loadVarDump( $logsForFirstFilter->fetchObject() );
		$this->assertArrayEquals(
			[ 'user_unnamed_ip', 'user_type', 'user_name', 'action' ],
			array_keys( $varDumpForFirstLog->getVars() )
		);

		// Assert that the log for the second filter does not have user_unnamed_ip in the var dump, as it
		// is a protected variable that was not used in the second filter.
		$logsForSecondFilter = $this->newSelectQueryBuilder()
			->select( [ 'afl_var_dump', 'afl_ip' ] )
			->from( 'abuse_filter_log' )
			->where( [ 'afl_filter_id' => 2 ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertSame( 1, $logsForSecondFilter->numRows() );
		$logsForSecondFilter->rewind();
		$varDumpForSecondLog = $variablesBlobStore->loadVarDump( $logsForSecondFilter->fetchObject() );
		$this->assertArrayEquals(
			[ 'user_type', 'user_name', 'action' ],
			array_keys( $varDumpForSecondLog->getVars() )
		);
	}

	public function addDBDataOnce() {
		// Clear the protected access hooks, as in CI other extensions (such as CheckUser) may attempt to
		// define additional restrictions that cause the tests to fail.
		$this->clearHooks( [
			'AbuseFilterCanViewProtectedVariables',
		] );

		// Get two testing filters, one with protected variables and one without protected variables
		$performer = $this->getTestSysop()->getUser();
		$this->assertStatusGood( AbuseFilterServices::getFilterStore()->saveFilter(
			$performer, null,
			$this->getFilterFromSpecs( [
				'id' => '1',
				'name' => 'Filter with protected variables',
				'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
				'rules' => 'user_unnamed_ip = "1.2.3.4" & user_name = "~2024-1" & user_type = "temp"',
			] ),
			MutableFilter::newDefault()
		) );
		$this->assertStatusGood( AbuseFilterServices::getFilterStore()->saveFilter(
			$performer, null,
			$this->getFilterFromSpecs( [
				'id' => '2',
				'name' => 'Filter without protected variables',
				'privacy' => Flags::FILTER_PUBLIC,
				'rules' => 'user_name = "~2024-1"',
			] ),
			MutableFilter::newDefault()
		) );
	}
}
