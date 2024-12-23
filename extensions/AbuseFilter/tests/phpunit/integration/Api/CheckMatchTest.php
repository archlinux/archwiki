<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\Parser\Exception\InternalException;
use MediaWiki\Extension\AbuseFilter\Parser\FilterEvaluator;
use MediaWiki\Extension\AbuseFilter\Parser\ParserStatus;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerStatus;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Json\FormatJson;
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

	public function testExecute_error() {
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
}
