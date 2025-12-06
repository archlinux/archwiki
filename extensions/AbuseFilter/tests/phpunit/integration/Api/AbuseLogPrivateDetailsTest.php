<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\FilterFromSpecsTestTrait;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Api\AbuseLogPrivateDetails
 * @group medium
 * @group Database
 */
class AbuseLogPrivateDetailsTest extends ApiTestCase {
	use MockAuthorityTrait;
	use FilterFromSpecsTestTrait;

	private static UserIdentity $userIdentity;

	public function testRequestForInexistentLogEntry() {
		$authority = $this->mockRegisteredUltimateAuthority();

		$this->expectExceptionMessage( 'An entry with the provided ID does not exist.' );
		$this->doApiRequestWithToken( [
			'action' => 'abuselogprivatedetails',
			'logid' => 2,
			'reason' => 'Lorem ipsum'
		], null, $authority, 'csrf' );
	}

	/** @dataProvider provideScenariosForRequestingExistingLog */
	public function testRequestForExistingLog( $permissions, $errorMessage ) {
		$authority = $this->mockRegisteredAuthorityWithPermissions( $permissions );

		if ( $errorMessage !== null ) {
			$this->expectExceptionMessage( $errorMessage );
		}

		[ $result ] = $this->doApiRequestWithToken( [
			'action' => 'abuselogprivatedetails',
			'logid' => 1,
			'reason' => 'Lorem ipsum'
		], null, $authority, 'csrf' );

		if ( $errorMessage !== null ) {
			$this->fail( 'The request succeeded but it should have failed with message: ' . $errorMessage );
		}

		$this->assertSame(
			[
				'log-id' => 1,
				'user' => self::$userIdentity->getName(),
				'filter-id' => 1,
				'filter-description' => 'Hidden filter',
				'ip-address' => '1.2.3.4',
			],
			$result['abuselogprivatedetails']
		);
	}

	public static function provideScenariosForRequestingExistingLog() {
		return [
			'User cannot see private details' => [
				'permissions' => [
					'abusefilter-view',
					'abusefilter-log',
					'abusefilter-log-detail',
				],
				'errorMessage' => 'You do not have permission to see private details of this entry.',
			],
			'User can see private details but not private filters (querying for private filter)' => [
				'permissions' => [
					'abusefilter-view',
					'abusefilter-log',
					'abusefilter-log-detail',
					'abusefilter-privatedetails',
					'abusefilter-privatedetails-log',
				],
				'errorMessage' => 'You do not have permission to see details of this entry.',
			],
			'User can see private details and private filters' => [
				'permissions' => [
					'abusefilter-view',
					'abusefilter-log',
					'abusefilter-log-detail',
					'abusefilter-privatedetails',
					'abusefilter-privatedetails-log',
					'abusefilter-view-private',
					'abusefilter-log-private',
				],
				'errorMessage' => null,
			],
		];
	}

	/** @dataProvider provideReasons */
	public function testRequirementOfReason( $requireReason, $passedReason, $shouldSucceed ) {
		$authority = $this->mockRegisteredUltimateAuthority();
		$this->overrideConfigValue( 'AbuseFilterPrivateDetailsForceReason', $requireReason );

		if ( !$shouldSucceed ) {
			$this->expectExceptionMessage( 'The "reason" parameter must be set.' );
		}

		[ $result ] = $this->doApiRequestWithToken( [
			'action' => 'abuselogprivatedetails',
			'logid' => 1,
			'reason' => $passedReason
		], null, $authority, 'csrf' );

		$this->assertSame(
			[
				'log-id' => 1,
				'user' => self::$userIdentity->getName(),
				'filter-id' => 1,
				'filter-description' => 'Hidden filter',
				'ip-address' => '1.2.3.4',
			],
			$result['abuselogprivatedetails']
		);
	}

	public static function provideReasons() {
		return [
			'Reason set but not required' => [
				'requireReason' => false,
				'passedReason' => 'Lorem ipsum',
				'shouldSucceed' => true,
			],
			'Reason not set but not required' => [
				'requireReason' => false,
				'passedReason' => null,
				'shouldSucceed' => true,
			],
			'Reason set and required' => [
				'requireReason' => true,
				'passedReason' => 'Lorem ipsum',
				'shouldSucceed' => true,
			],
			'Reason set to empty string but required' => [
				'requireReason' => true,
				'passedReason' => '',
				'shouldSucceed' => false,
			],
			'Reason not set but required' => [
				'requireReason' => true,
				'passedReason' => null,
				'shouldSucceed' => false,
			],
		];
	}

	public function addDBDataOnce() {
		$user = $this->getTestUser()->getUser();
		// Add the filter
		$performer = $this->getTestSysop()->getUserIdentity();
		$authority = new UltimateAuthority( $performer );
		$this->assertStatusGood( AbuseFilterServices::getFilterStore()->saveFilter(
			$authority, null,
			$this->getFilterFromSpecs( [
				'id' => '1',
				'rules' => 'user_name = "Lorem ipsum"',
				'name' => 'Hidden filter',
				'privacy' => Flags::FILTER_HIDDEN,
				'lastEditor' => $performer,
				'lastEditTimestamp' => $this->getDb()->timestamp( '20250701000000' ),
			] ),
			MutableFilter::newDefault()
		) );

		// Add filter hit
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$user,
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => $user->getName(),
			] )
		)->addLogEntries( [ 1 => [ 'warn' ] ] );

		// Set IP address to a known value so it can be tested against
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'abuse_filter_log' )
			->set( [ 'afl_ip_hex' => IPUtils::toHex( '1.2.3.4' ), 'afl_id' => 1 ] )
			->where( [ 'afl_filter_id' => 1 ] )
			->caller( __METHOD__ )->execute();

		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter_log' )
			->caller( __METHOD__ )
			->assertFieldValue( 1 );

		self::$userIdentity = $user;
	}
}
