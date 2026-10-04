<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

/**
 * Common helpers for integration tests involving protected AbuseFilter variables.
 *
 * Intended for use with `MediaWikiIntegrationTestCase`. `FilterFromSpecsTestTrait` is
 * internally used.
 */
trait ProtectedVarsTestTrait {
	use FilterFromSpecsTestTrait;

	/**
	 * Clear the protected access hooks, as in CI other extensions (such as CheckUser) may attempt to
	 * define additional restrictions or alter logging that cause the tests to fail.
	 */
	private function clearProtectedVarRelatedHooks(): void {
		$this->clearHooks( [
			'AbuseFilterCanViewProtectedVariables',
			'AbuseFilterLogProtectedVariableValueAccess',
		] );
	}

	private function mockFilterEditorAuthorityWithProtectedVarsAccess(): Authority {
		return new SimpleAuthority(
			$this->getMutableTestUser()->getUserIdentity(),
			[
				'abusefilter-access-protected-vars',
				'abusefilter-log-private',
				'abusefilter-view-private',
				'abusefilter-modify',
				'abusefilter-log-detail',
			]
		);
	}

	private function mockFilterEditorAuthorityWithoutProtectedVarsAccess(): Authority {
		return new SimpleAuthority(
			$this->getMutableTestUser()->getUserIdentity(),
			[
				'abusefilter-log-private',
				'abusefilter-view-private',
				'abusefilter-modify',
				'abusefilter-log-detail',
			]
		);
	}

	/**
	 * Create two filters:
	 *
	 * * `Filter 1`: A protected filter where first revision is public, and the second two are protected.
	 *   This filter has a hit count of 1, where the triggerer uses the IP address `1.2.3.4`.
	 * * `Filter 2`: A public fiter without protected variables.
	 *
	 * @return User The user who hit the protected filter 1.
	 */
	private function createFiltersWithProtectedVariables(): User {
		$filterStore = AbuseFilterServices::getFilterStore();
		$performer = $this->getTestSysop()->getUserIdentity();
		$userWhoHitFilter = $this->getTestUser()->getUser();
		$authority = new UltimateAuthority( $performer );

		// Create a test filter where first revision is public, and the second two are protected.
		// The public revision exists to test handling in AbuseFilterViewHistory.
		$firstFilterRevision = $this->getFilterFromSpecs( [
			'id' => '1',
			'rules' => 'user_name = "1.2.3.5"',
			'name' => 'Filter to be converted',
			'privacy' => Flags::FILTER_PUBLIC,
			'lastEditor' => $performer,
			'lastEditTimestamp' => '20190825000000',
		] );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, null, $firstFilterRevision, MutableFilter::newDefault()
		) );
		$secondFilterRevision = $this->getFilterFromSpecs( [
			'id' => '1',
			'rules' => 'user_unnamed_ip = "1.2.3.5"',
			'name' => 'Filter with protected variables',
			'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
			'lastEditor' => $performer,
			'lastEditTimestamp' => '20190826000000',
		] );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority, 1, $secondFilterRevision, $firstFilterRevision
		) );
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority,
			1,
			$this->getFilterFromSpecs( [
				'id' => '1',
				'rules' => 'user_unnamed_ip = "1.2.3.4"',
				'name' => 'Filter with protected variables',
				'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
				'lastEditor' => $performer,
				'lastEditTimestamp' => '20190827000000',
				'hitCount' => 1,
				'actions' => [ 'tags' => [ 'test' ] ]
			] ),
			$secondFilterRevision
		) );

		// Create a second filter which is public
		$this->assertStatusGood( $filterStore->saveFilter(
			$authority,
			null,
			$this->getFilterFromSpecs( [
				'id' => '2',
				'rules' => 'user_name = "1.2.3.4"',
				'name' => 'Filter without protected variables',
				'privacy' => Flags::FILTER_PUBLIC,
				'lastEditor' => $performer,
				'lastEditTimestamp' => '20000101000000',
			] ),
			MutableFilter::newDefault()
		) );

		// Add a log on the protected filter which has a hit count of 1
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$userWhoHitFilter,
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_unnamed_ip' => '1.2.3.4',
				'user_name' => $userWhoHitFilter->getName(),
			] )
		)->addLogEntries( [ 1 => [ 'warn' ] ] );

		// Verify that the expected number of DB rows were created
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter' )
			->caller( __METHOD__ )
			->assertFieldValue( 2 );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter_history' )
			->caller( __METHOD__ )
			->assertFieldValue( 4 );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter_log' )
			->caller( __METHOD__ )
			->assertFieldValue( 1 );

		return $userWhoHitFilter;
	}

	private function assertProtectedVariableAccessLogExists(
		UserIdentity $performer, string $target, array $variablesViewed
	): void {
		$result = $this->newSelectQueryBuilder()
			->select( 'log_params' )
			->from( 'logging' )
			->join( 'actor', null, 'actor_id=log_actor' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
				'actor_name' => $performer->getName(),
				'log_title' => Title::newFromText( $target )->getDBkey(),
				'log_namespace' => NS_USER,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertSame( 1, $result->numRows() );
		$result->rewind();
		$this->assertArrayEquals(
			[ 'variables' => $variablesViewed ],
			LogEntryBase::extractParams( $result->fetchRow()['log_params'] ),
			false,
			true
		);
	}

	private function addCustomProtectedVariableToGenericVars(
		?string $variableValue = 'custom_variable_value'
	): void {
		$this->setTemporaryHook( 'AbuseFilterCustomProtectedVariables', static function ( &$variables ) {
			$variables[] = 'custom_variable';
		} );
		$this->setTemporaryHook( 'AbuseFilter-builder', static function ( array &$realValues ) {
			$realValues['vars']['custom_variable'] = 'custom-variable-test';
		} );
		$this->setTemporaryHook(
			'AbuseFilter-generateGenericVars',
			static function ( VariableHolder $vars ) use ( $variableValue ) {
				$vars->setVar( 'custom_variable', $variableValue );
			}
		);
		$this->resetServices();
	}

	/**
	 * Drop the `view-protected-var-value` logs from the `logging` table.
	 *
	 * This may need to be called manually when the `logging` table is touched by `::addDBDataOnce()`
	 * and hence is not automatically reset between tests.
	 */
	private function dropProtectedVarAccessLogs(): void {
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->caller( __METHOD__ )
			->execute();
	}
}
