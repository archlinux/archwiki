<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use ApiTestCase;
use MediaWiki\Extension\AbuseFilter\Parser\Exception\UserVisibleException;
use MediaWiki\Extension\AbuseFilter\Parser\Exception\UserVisibleWarning;
use MediaWiki\Extension\AbuseFilter\Parser\FilterEvaluator;
use MediaWiki\Extension\AbuseFilter\Parser\ParserStatus;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Api\CheckSyntax
 * @covers ::__construct
 * @group medium
 */
class CheckSyntaxTest extends ApiTestCase {
	use AbuseFilterApiTestTrait;

	/**
	 * @covers ::execute
	 */
	public function testExecute_noPermissions() {
		$this->setExpectedApiException( 'apierror-abusefilter-cantcheck', 'permissiondenied' );

		$this->setService( RuleCheckerFactory::SERVICE_NAME, $this->getRuleCheckerFactory() );

		$this->doApiRequest( [
			'action' => 'abusefilterchecksyntax',
			'filter' => 'sampleFilter',
		], null, null, self::getTestUser()->getUser() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_Ok() {
		$input = 'sampleFilter';
		$status = new ParserStatus( null, [], 1 );
		$ruleChecker = $this->createMock( FilterEvaluator::class );
		$ruleChecker->method( 'checkSyntax' )->with( $input )
			->willReturn( $status );
		$this->setService( RuleCheckerFactory::SERVICE_NAME, $this->getRuleCheckerFactory( $ruleChecker ) );

		$result = $this->doApiRequest( [
			'action' => 'abusefilterchecksyntax',
			'filter' => $input,
		], null, null, self::getTestSysop()->getUser() );

		$this->assertArrayEquals(
			[ 'abusefilterchecksyntax' => [ 'status' => 'ok' ] ],
			$result[0],
			false,
			true
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_OkAndWarnings() {
		$input = 'sampleFilter';
		$warnings = [
			new UserVisibleWarning( 'exception-1', 3, [] ),
			new UserVisibleWarning( 'exception-2', 8, [ 'param' ] ),
		];
		$status = new ParserStatus( null, $warnings, 1 );
		$ruleChecker = $this->createMock( FilterEvaluator::class );
		$ruleChecker->method( 'checkSyntax' )->with( $input )
			->willReturn( $status );
		$this->setService( RuleCheckerFactory::SERVICE_NAME, $this->getRuleCheckerFactory( $ruleChecker ) );

		$result = $this->doApiRequest( [
			'action' => 'abusefilterchecksyntax',
			'filter' => $input,
		], null, null, self::getTestSysop()->getUser() );

		$this->assertArrayEquals(
			[
				'abusefilterchecksyntax' => [
					'status' => 'ok',
					'warnings' => [
						[
							'message' => wfMessage(
								'abusefilter-parser-warning-exception-1',
								3
							)->text(),
							'character' => 3,
						],
						[
							'message' => wfMessage(
								'abusefilter-parser-warning-exception-2',
								8,
								'param'
							)->text(),
							'character' => 8,
						],
					]
				]
			],
			$result[0],
			false,
			true
		);
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_error() {
		$input = 'sampleFilter';
		$exception = new UserVisibleException( 'error-id', 4, [] );
		$status = new ParserStatus( $exception, [], 1 );
		$ruleChecker = $this->createMock( FilterEvaluator::class );
		$ruleChecker->method( 'checkSyntax' )->with( $input )
			->willReturn( $status );
		$this->setService( RuleCheckerFactory::SERVICE_NAME, $this->getRuleCheckerFactory( $ruleChecker ) );

		$result = $this->doApiRequest( [
			'action' => 'abusefilterchecksyntax',
			'filter' => $input,
		], null, null, self::getTestSysop()->getUser() );

		$this->assertArrayEquals(
			[
				'abusefilterchecksyntax' => [
					'status' => 'error',
					'message' => wfMessage(
						'abusefilter-exception-error-id',
						4
					)->text(),
					'character' => 4
				]
			],
			$result[0],
			false,
			true
		);
	}
}
