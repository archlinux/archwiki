<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterLogDetailsLookup
 * @group Database
 */
class AbuseFilterLogDetailsLookupTest extends MediaWikiIntegrationTestCase {
	use FilterFromSpecsTestTrait;
	use MockAuthorityTrait;
	use TempUserTestTrait;

	/** @dataProvider provideGetIPForAbuseFilterLogForFatalStatus */
	public function testGetIPForAbuseFilterLogForFatalStatus( $id, $authorityHasRights, $expectedErrorMessage ) {
		if ( $authorityHasRights ) {
			$authority = $this->mockRegisteredUltimateAuthority();
		} else {
			$authority = $this->mockRegisteredNullAuthority();
		}
		$this->assertStatusError(
			$expectedErrorMessage,
			AbuseFilterServices::getLogDetailsLookup()->getIPForAbuseFilterLog( $authority, $id )
		);
	}

	public static function provideGetIPForAbuseFilterLogForFatalStatus() {
		return [
			'Filter ID does not exist' => [ 1234, true, 'abusefilter-log-nonexistent' ],
			'Authority lacks rights' => [ 1, false, 'abusefilter-log-cannot-see-details' ],
		];
	}

	/** @dataProvider provideGetIPForAbuseFilterLog */
	public function testGetIPForAbuseFilterLog( $id, $expectedIP ) {
		$actualStatus = AbuseFilterServices::getLogDetailsLookup()->getIPForAbuseFilterLog(
			$this->mockRegisteredUltimateAuthority(), $id
		);
		$this->assertStatusGood( $actualStatus );
		$this->assertStatusValue( $expectedIP, $actualStatus );
	}

	public static function provideGetIPForAbuseFilterLog(): array {
		return [
			'When abuse_filter_log row has an IP set' => [ 1, '1.2.3.4' ],
			'When abuse_filter_log row does not have an IP set' => [ 2, '' ],
		];
	}

	/** @dataProvider provideGetIPsForAbuseFilterLogs */
	public function testGetIPsForAbuseFilterLogs( $ids, $expectedReturnArray ) {
		$actualArray = AbuseFilterServices::getLogDetailsLookup()->getIPsForAbuseFilterLogs(
			$this->mockRegisteredUltimateAuthority(), $ids
		);
		$this->assertArrayEquals( $expectedReturnArray, $actualArray, false, true );
	}

	public static function provideGetIPsForAbuseFilterLogs(): array {
		return [
			'Calling with one afl_id' => [ [ 1 ], [ 1 => '1.2.3.4' ] ],
			'Calling with multiple afl_id values' => [ [ 1, 2, 3 ], [ 1 => '1.2.3.4', 2 => '', 3 => '' ] ],
		];
	}

	public function testGetIPsForAbuseFilterLogsWhenUserLacksAuthority() {
		$actualArray = AbuseFilterServices::getLogDetailsLookup()->getIPsForAbuseFilterLogs(
			$this->mockRegisteredNullAuthority(), [ 1, 2, 3 ]
		);

		// The list of IPs should be false for afl_id values which corresponded to an existing abuse_filter_log row
		// and empty string if the afl_id does not exist.
		$this->assertArrayEquals( [ 1 => false, 2 => false, 3 => '' ], $actualArray, false, true );
	}

	public function testGroupAbuseFilterLogIdsByPerformer() {
		$actualArray = AbuseFilterServices::getLogDetailsLookup()->groupAbuseFilterLogIdsByPerformer( [ 1, 2, 3, 4 ] );

		// The list of IDs should not include any afl_id values which do not correspond to an existing abuse_filter_log
		// row and should group the performers for the afl_ids which correspond to existing abuse.
		$this->assertArrayEquals( [ '~2024-1' => [ 1 ], 'UTSysop' => [ 2 ] ], $actualArray, false, true );
	}

	public function addDBDataOnce() {
		$performer = $this->getTestSysop()->getUser();
		$this->assertStatusGood( AbuseFilterServices::getFilterStore()->saveFilter(
			$performer, null,
			$this->getFilterFromSpecs( [
				'id' => '1',
				'name' => 'Test filter',
				'privacy' => Flags::FILTER_HIDDEN,
				'rules' => 'old_wikitext = "abc"',
			] ),
			MutableFilter::newDefault()
		) );

		// Insert two hits on the filter.
		// The first has an IP set in the abuse_filter_log row
		$this->enableAutoCreateTempUser();
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$this->getServiceContainer()->getTempUserCreator()->create( '~2024-1', new FauxRequest() )->getUser(),
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => '~2024-1',
				'old_wikitext' => 'abc',
			] )
		)->addLogEntries( [ 1 => [] ] );

		// The second does not have an IP set in the abuse_filter_log row
		$this->overrideConfigValue( 'AbuseFilterLogIP', false );
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$testUser = $this->getTestSysop()->getUser();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$testUser,
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => $testUser->getName(),
				'old_wikitext' => 'abc',
			] )
		)->addLogEntries( [ 1 => [] ] );
		$this->overrideConfigValue( 'AbuseFilterLogIP', true );

		// Check that only two abuse_filter_log rows were created (we need to be sure that abuse_filter_log row with
		// afl_id of 1 and 2 exists for the above tests) with the correct IPs.
		$this->newSelectQueryBuilder()
			->select( [ 'afl_id', 'afl_ip_hex' ] )
			->from( 'abuse_filter_log' )
			->caller( __METHOD__ )
			->assertResultSet( [ [ '1', IPUtils::toHex( '1.2.3.4' ) ], [ '2', '' ] ] );
	}
}
