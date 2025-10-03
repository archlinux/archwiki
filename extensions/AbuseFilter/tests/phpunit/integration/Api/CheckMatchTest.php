<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\Parser\Exception\InternalException;
use MediaWiki\Extension\AbuseFilter\Parser\FilterEvaluator;
use MediaWiki\Extension\AbuseFilter\Parser\ParserStatus;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerStatus;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Json\FormatJson;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Api\CheckMatch
 * @group Database
 * @group medium
 */
class CheckMatchTest extends ApiTestCase {
	use AbuseFilterApiTestTrait;
	use MockAuthorityTrait;

	private static int $recentChangeId;

	public function testExecute_noPermissions() {
		$this->expectApiErrorCode( 'permissiondenied' );

		$this->setService( RuleCheckerFactory::SERVICE_NAME, $this->getRuleCheckerFactory() );

		$this->doApiRequest( [
			'action' => 'abusefiltercheckmatch',
			'filter' => 'sampleFilter',
			'vars' => FormatJson::encode( [] ),
		], null, null, $this->mockRegisteredNullAuthority() );
	}

	public static function provideExecuteOk() {
		return [
			'matched' => [ true ],
			'no match' => [ false ],
		];
	}

	/**
	 * @dataProvider provideExecuteOk
	 */
	public function testExecute_Ok( bool $expected ) {
		$filter = 'sampleFilter';
		$checkStatus = new ParserStatus( null, [], 1 );
		$resultStatus = new RuleCheckerStatus( $expected, false, null, [], 1 );
		$ruleChecker = $this->createMock( FilterEvaluator::class );
		$ruleChecker->expects( $this->once() )
			->method( 'checkSyntax' )->with( $filter )
			->willReturn( $checkStatus );
		$ruleChecker->expects( $this->once() )
			->method( 'checkConditions' )->with( $filter )
			->willReturn( $resultStatus );
		$this->setService( RuleCheckerFactory::SERVICE_NAME, $this->getRuleCheckerFactory( $ruleChecker ) );

		$result = $this->doApiRequest( [
			'action' => 'abusefiltercheckmatch',
			'filter' => $filter,
			'vars' => FormatJson::encode( [] ),
		] );

		$this->assertArrayEquals(
			[
				'abusefiltercheckmatch' => [
					'result' => $expected
				]
			],
			$result[0],
			false,
			true
		);
	}

	public function testExecuteForBadFilterSyntax() {
		$this->expectApiErrorCode( 'badsyntax' );
		$filter = 'sampleFilter';
		$status = new ParserStatus( $this->createMock( InternalException::class ), [], 1 );
		$ruleChecker = $this->createMock( FilterEvaluator::class );
		$ruleChecker->expects( $this->once() )
			->method( 'checkSyntax' )->with( $filter )
			->willReturn( $status );
		$this->setService( RuleCheckerFactory::SERVICE_NAME, $this->getRuleCheckerFactory( $ruleChecker ) );

		$this->doApiRequest( [
			'action' => 'abusefiltercheckmatch',
			'filter' => $filter,
			'vars' => FormatJson::encode( [] ),
		] );
	}

	public function testExecuteWhenPerformerCannotSeeLogId() {
		// Mock the FilterLookup service to return that the filter with the ID 1 is hidden.
		$mockLookup = $this->createMock( FilterLookup::class );
		$mockLookup->method( 'getFilter' )
			->with( 1, false )
			->willReturnCallback( function () {
				$filterObj = $this->createMock( ExistingFilter::class );
				$filterObj->method( 'getPrivacyLevel' )->willReturn( Flags::FILTER_HIDDEN );
				return $filterObj;
			} );
		$this->setService( FilterLookup::SERVICE_NAME, $mockLookup );
		// Create an AbuseFilter log entry for the hidden filter
		AbuseFilterServices::getAbuseLoggerFactory()->newLogger(
			Title::newFromText( 'Testing' ),
			$this->getTestUser()->getUser(),
			VariableHolder::newFromArray( [ 'action' => 'edit' ] )
		)->addLogEntries( [ 1 => [ 'warn' ] ] );
		// Execute the API using a user with the 'abusefilter-modify' right but without the
		// 'abusefilter-log-detail' right, while specifying a filter abuse filter log ID of 1
		$this->expectApiErrorCode( 'cannotseedetails' );
		$this->doApiRequest(
			[
				'action' => 'abusefiltercheckmatch',
				'logid' => 1,
				'filter' => 'invalidfilter=======',
			],
			null, false, $this->mockRegisteredAuthorityWithPermissions( [ 'abusefilter-modify' ] )
		);
	}

	private function addCustomProtectedVariable(): void {
		$this->setTemporaryHook( 'AbuseFilterCustomProtectedVariables', static function ( &$variables ) {
			$variables[] = 'custom_variable';
		} );
		$this->setTemporaryHook( 'AbuseFilter-builder', static function ( array &$realValues ) {
			$realValues['vars']['custom_variable'] = 'custom-variable-test';
		} );
		$this->setTemporaryHook( 'AbuseFilter-generateGenericVars', static function ( VariableHolder $vars ) {
			$vars->setVar( 'custom_variable', 'custom_variable_value' );
		} );
	}

	public function testExecuteWhenLogIdContainsProtectedVariablesUserCannotSee() {
		// Mock the FilterLookup service to return that the filter with the ID 1 is protected.
		$mockLookup = $this->createMock( FilterLookup::class );
		$mockLookup->method( 'getFilter' )
			->with( 1, false )
			->willReturnCallback( function () {
				$filterObj = $this->createMock( ExistingFilter::class );
				$filterObj->method( 'getPrivacyLevel' )->willReturn( Flags::FILTER_USES_PROTECTED_VARS );
				$filterObj->method( 'isProtected' )->willReturn( true );
				$filterObj->method( 'getRules' )->willReturn( 'custom_variable = 1' );
				return $filterObj;
			} );
		$this->setService( FilterLookup::SERVICE_NAME, $mockLookup );

		$this->addCustomProtectedVariable();
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( Authority $performer, array $variables, AbuseFilterPermissionStatus $returnStatus ) {
				if ( in_array( 'custom_variable', $variables ) ) {
					$returnStatus->setPermission( 'test-permission' );
				}
			}
		);

		// Create an AbuseFilter log entry for the protected filter that has a protected variable the user cannot see.
		AbuseFilterServices::getAbuseLoggerFactory()->newLogger(
			Title::newFromText( 'Testing' ),
			$this->getTestUser()->getUser(),
			VariableHolder::newFromArray( [ 'action' => 'edit', 'custom_variable' => 'abc' ] )
		)->addLogEntries( [ 1 => [ 'warn' ] ] );

		$this->expectApiErrorCode( 'cannotseedetails' );
		$this->doApiRequest(
			[
				'action' => 'abusefiltercheckmatch',
				'logid' => 1,
				'filter' => 'invalidfilter=======',
			],
			null, false,
			$this->mockRegisteredAuthorityWithoutPermissions( [ 'test-permission' ] )
		);
	}

	public function testExecuteWhenProvidedTestPatternUsesProtectedVariableUserCannotSee() {
		$this->addCustomProtectedVariable();
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( Authority $performer, array $variables, AbuseFilterPermissionStatus $returnStatus ) {
				if ( in_array( 'custom_variable', $variables ) ) {
					$returnStatus->setPermission( 'test-permission' );
				}
			}
		);

		// The API request should fail because it uses a protected variable the user cannot see the values of,
		// so testing using it could allow them to trial-and-error work out the value.
		$this->expectApiErrorCode( 'cannotseeprotectedvariables' );
		$this->doApiRequest(
			[
				'action' => 'abusefiltercheckmatch',
				'rcid' => self::$recentChangeId,
				'filter' => 'custom_variable = 1',
			],
			null, false,
			$this->mockRegisteredAuthorityWithoutPermissions( [ 'test-permission' ] )
		);
	}

	public function testExecuteWhenProvidedTestPatternUsesProtectedVariables() {
		$this->addCustomProtectedVariable();
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( Authority $performer, array $variables, AbuseFilterPermissionStatus $returnStatus ) {
				if ( in_array( 'custom_variable', $variables ) ) {
					$returnStatus->setPermission( 'test-permission' );
				}
			}
		);

		// The API request should fail because it uses a protected variable the user cannot see the values of,
		// so testing using it could allow them to trial-and-error work out the value.
		$this->expectApiErrorCode( 'cannotseeprotectedvariables' );
		$this->doApiRequest(
			[
				'action' => 'abusefiltercheckmatch',
				'rcid' => self::$recentChangeId,
				'filter' => 'custom_variable = 1',
			],
			null, false, $this->mockRegisteredUltimateAuthority()
		);
	}

	public function testViewExamineForRecentChangeWhenUserCanSeeRecentChange() {
		$this->addCustomProtectedVariable();

		[ $result ] = $this->doApiRequest(
			[
				'action' => 'abusefiltercheckmatch',
				'rcid' => self::$recentChangeId,
				'filter' => "custom_variable = 'custom_variable_value'",
			],
			null, false, $this->mockRegisteredUltimateAuthority()
		);

		$this->assertSame( [ 'abusefiltercheckmatch' => [ 'result' => true ] ], $result );

		// Verify that a protected variable access log was created as protected variable values were viewed.
		$result = $this->newSelectQueryBuilder()
			->select( 'log_params' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$this->assertSame( 1, $result->numRows() );
		$result->rewind();
		$this->assertArrayEquals(
			[ 'variables' => [ 'custom_variable' ] ],
			LogEntryBase::extractParams( $result->fetchRow()['log_params'] ),
			false,
			true
		);

		$this->dropProtectedVarAccessLogs();
	}

	/**
	 * Drops the 'view-protected-var-value' logs from the 'logging' table.
	 *
	 * This is needed because in {@link self::addDBDataOnce} we added rows to the 'logging' table and so the table is
	 * not reset between tests.
	 *
	 * @return void
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

	public function addDBDataOnce() {
		// Create a testing recentchanges table row by creating a logging table row that is sent to recentchanges.
		$logEntry = new ManualLogEntry( 'move', 'move' );
		$logEntry->setPerformer( $this->getTestUser()->getUserIdentity() );
		$logEntry->setTarget( $this->getExistingTestPage()->getTitle() );
		$logEntry->setComment( 'A very good reason' );
		$logEntry->setParameters( [
			'4::target' => wfRandomString(),
			'5::noredir' => '0'
		] );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId );

		// Check that the recentchanges row for the log entry exists and get the ID for it.
		$recentChangeId = $this->newSelectQueryBuilder()
			->select( 'rc_id' )
			->from( 'recentchanges' )
			->where( [ 'rc_logid' => $logId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertNotFalse( $recentChangeId );
		self::$recentChangeId = $recentChangeId;
	}
}
