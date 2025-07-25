<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use LoggedServiceOptions;
use MediaWiki\CheckUser\Investigate\Pagers\ComparePager;
use MediaWiki\CheckUser\Investigate\Services\CompareService;
use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\CheckUser\Tests\Integration\Investigate\CompareTabTestDataTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\Linker\Linker;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiIntegrationTestCase;
use TestAllServiceOptionsUsed;
use Wikimedia\IPUtils;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\ComparePager
 */
class ComparePagerTest extends MediaWikiIntegrationTestCase {
	use TestAllServiceOptionsUsed;
	use CompareTabTestDataTrait;
	use MockAuthorityTrait;

	private static User $hiddenUser;

	protected function setUp(): void {
		// Pin time to avoid failure when next second starts - T317411
		ConvertibleTimestamp::setFakeTime( '20220904094043' );
	}

	private function getObjectUnderTest( array $overrides = [] ): ComparePager {
		$services = $this->getServiceContainer();
		return new ComparePager(
			RequestContext::getMain(),
			$overrides['linkRenderer'] ?? $services->getLinkRenderer(),
			$overrides['tokenQueryManager'] ?? $services->get( 'CheckUserTokenQueryManager' ),
			$overrides['durationManager'] ?? $services->get( 'CheckUserDurationManager' ),
			$overrides['compareService'] ?? $services->get( 'CheckUserCompareService' ),
			$overrides['userFactory'] ?? $services->getUserFactory(),
			$services->getLinkBatchFactory()
		);
	}

	/** @dataProvider provideFormatValue */
	public function testFormatValue( array $row, $name, $expectedFormattedValue ) {
		// Set the user language to qqx so that we can compare against the message keys and not the english version of
		// the message key (which may change and then break the tests).
		$this->setUserLang( 'qqx' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $this->getObjectUnderTest() );
		// Set the $row as an object to mCurrentRow for access by ::formatValue.
		$objectUnderTest->mCurrentRow = (object)$row;
		/** @var $objectUnderTest ComparePager */
		$this->assertSame(
			$expectedFormattedValue,
			$objectUnderTest->formatValue( $name, $row[$name] ?? null ),
			'::formatRow did not return the expected HTML'
		);
	}

	public static function provideFormatValue() {
		return [
			'activity as $name' => [
				// The row set as $this->mCurrentRow in the object under test, provided as an array
				[ 'first_action' => '20240405060708', 'last_action' => '20240406060708' ],
				// The $name argument to ::formatValue
				'activity',
				// The expected formatted value
				'5 (april) 2024 - 6 (april) 2024'
			],
			'user agent is not null' => [ [ 'agent' => 'test' ], 'agent', 'test' ],
			'user agent is null' => [ [ 'agent' => null ], 'agent', '' ],
			'user agent contains unescaped HTML' => [
				[ 'agent' => '<b>test</b>' ], 'agent', '&lt;b&gt;test&lt;/b&gt;',
			],
			'ip is 1.2.3.4' => [
				[ 'ip' => '1.2.3.4', 'ip_hex' => IPUtils::toHex( '1.2.3.4' ), 'total_actions' => 1 ], 'ip',
				'<span class="ext-checkuser-compare-table-cell-ip">1.2.3.4</span>' .
				'<div>(checkuser-investigate-compare-table-cell-actions: 1) ' .
				'<span>(checkuser-investigate-compare-table-cell-other-actions: 11)</span></div>',
			],
			'unrecognised $name' => [ [], 'foo', '' ],
			'user_text as 1.2.3.5' => [
				[ 'user_text' => '1.2.3.5', 'user' => 0, 'actor' => 1 ], 'user_text',
				'(checkuser-investigate-compare-table-cell-unregistered)',
			],
			'user_id is null' => [
				[ 'user_text' => '1.2.3.5', 'user' => null, 'actor' => 1 ], 'user_text',
				'(checkuser-investigate-compare-table-cell-unregistered)',
			],
			'user_text as null' => [
				[ 'user_text' => null, 'user' => 0, 'actor' => null, 'ip' => '1.2.3.5' ], 'user_text',
				'(checkuser-investigate-compare-table-cell-unregistered)',
			],
		];
	}

	public function testFormatValueForHiddenUser() {
		// Assign the rights to the main context authority, which will be used by the object under test.
		RequestContext::getMain()->setAuthority( $this->mockRegisteredAuthorityWithoutPermissions( [ 'hideuser' ] ) );
		$this->testFormatValue(
			// The $hiddenUser static property may not be set when the data providers are called, so this needs to be
			// accessed in a test method.
			[
				'user_text' => self::$hiddenUser->getName(),
				'user' => self::$hiddenUser->getId(),
				'actor' => self::$hiddenUser->getActorId(),
			],
			'user_text', '(rev-deleted-user)'
		);
	}

	public function testFormatValueForUser() {
		// Assign the rights to the main context authority, which will be used by the object under test.
		RequestContext::getMain()->setAuthority(
			$this->mockRegisteredAuthorityWithPermissions( [ 'hideuser', 'checkuser' ] )
		);
		$this->testFormatValue(
			[
				'user_text' => self::$hiddenUser->getName(),
				'user' => self::$hiddenUser->getId(),
				'actor' => self::$hiddenUser->getActorId(),
			],
			'user_text',
			// We cannot mock a static method, so we have to use the real method here.
			// This also means this cannot be in a data provider.
			Linker::userLink( self::$hiddenUser->getId(), self::$hiddenUser->getName() )
		);
	}

	/** @dataProvider provideGetCellAttrs */
	public function testGetCellAttrs(
		array $row, array $filteredTargets, array $ipTotalActions, $name, $expectedClasses, $otherExpectedAttributes
	) {
		// Set the user language to qqx so that we can compare against the message keys and not the english version of
		// the message key (which may change and then break the tests).
		$this->setUserLang( 'qqx' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $this->getObjectUnderTest() );
		// Set the $row, $filteredTargets, and $ipTotalActions in the relevant properties of the object under test so
		// that ::getCellAttrs can access them.
		$objectUnderTest->mCurrentRow = (object)$row;
		$objectUnderTest->filteredTargets = $filteredTargets;
		$objectUnderTest->ipTotalActions = $ipTotalActions;
		/** @var $objectUnderTest ComparePager */
		$actualCellAttrs = $objectUnderTest->getCellAttrs( $name, $row[$name] ?? null );
		foreach ( $expectedClasses as $class ) {
			$this->assertStringContainsString(
				$class,
				$actualCellAttrs['class'],
				"The class $class was not in the actual classes for the cell"
			);
		}
		// Unset the 'class' so that we can test the other attributes using ::assertArrayEquals
		unset( $actualCellAttrs['class'] );
		// Add 'tabindex' as 0 to the expected attributes, as this is always added by ::getCellAttrs.
		$otherExpectedAttributes['tabindex'] = 0;
		$this->assertArrayEquals(
			$otherExpectedAttributes,
			$actualCellAttrs,
			false,
			true,
			'::getCellAttrs did not return the expected attributes'
		);
	}

	public static function provideGetCellAttrs() {
		return [
			'$name as ip when IP $value is inside a filtered target IP range' => [
				// The row set as $this->mCurrentRow in the object under test, provided as an array
				[ 'ip' => '1.2.3.4', 'ip_hex' => IPUtils::toHex( '1.2.3.4' ), 'total_actions' => 1 ],
				// The value of the filteredTargets property in the object under test (only used for ip and
				// user_text $name values).
				[ 'TestUser1', '1.2.3.0/24' ],
				// The value of the ipTotalActions property in the object under test (only used for ip $name values).
				[ IPUtils::toHex( '1.2.3.4' ) => 2 ],
				// The $name argument to ::getCellAttrs
				'ip',
				// The expected classes for the cell
				[
					'ext-checkuser-compare-table-cell-target', 'ext-checkuser-compare-table-cell-ip-target',
					'ext-checkuser-investigate-table-cell-pinnable',
					'ext-checkuser-investigate-table-cell-interactive',
				],
				// The expected attributes for the cell (minus the class, as this is tested above).
				[
					'data-field' => 'ip', 'data-value' => '1.2.3.4',
					'data-sort-value' => IPUtils::toHex( '1.2.3.4' ), 'data-actions' => 1, 'data-all-actions' => 2,
				],
			],
			'$name as ip when IP $value is a filtered target' => [
				[ 'ip' => '1.2.3.4', 'ip_hex' => IPUtils::toHex( '1.2.3.4' ), 'total_actions' => 1 ],
				[ 'TestUser1', '1.2.3.4' ], [ IPUtils::toHex( '1.2.3.4' ) => 2 ], 'ip',
				[
					'ext-checkuser-compare-table-cell-target', 'ext-checkuser-compare-table-cell-ip-target',
					'ext-checkuser-investigate-table-cell-pinnable',
					'ext-checkuser-investigate-table-cell-interactive',
				],
				[
					'data-field' => 'ip', 'data-value' => '1.2.3.4',
					'data-sort-value' => IPUtils::toHex( '1.2.3.4' ), 'data-actions' => 1, 'data-all-actions' => 2,
				],
			],
			'$name as ip when IP is not in filtered targets array' => [
				[ 'ip' => '1.2.3.4', 'ip_hex' => IPUtils::toHex( '1.2.3.4' ), 'total_actions' => 1 ],
				[], [ IPUtils::toHex( '1.2.3.4' ) => 2 ], 'ip',
				[
					'ext-checkuser-compare-table-cell-ip-target', 'ext-checkuser-investigate-table-cell-pinnable',
					'ext-checkuser-investigate-table-cell-interactive',
				],
				[
					'data-field' => 'ip', 'data-value' => '1.2.3.4',
					'data-sort-value' => IPUtils::toHex( '1.2.3.4' ), 'data-actions' => 1, 'data-all-actions' => 2,
				],
			],
			'$name as user_text for IP address' => [
				[ 'user_text' => '1.2.3.4', 'actor' => 1 ], [], [], 'user_text',
				[ 'ext-checkuser-investigate-table-cell-interactive' ],
				[ 'data-sort-value' => '1.2.3.4' ],
			],
			'$name as user_text for unregistered user that is a target' => [
				[ 'user_text' => 'TestUser1', 'actor' => 1 ], [ 'TestUser1' ], [], 'user_text',
				[ 'ext-checkuser-compare-table-cell-target', 'ext-checkuser-investigate-table-cell-interactive' ],
				[ 'data-field' => 'user_text', 'data-value' => 'TestUser1', 'data-sort-value' => 'TestUser1' ],
			],
			'$name as user_text with $value as null' => [
				[ 'user_text' => null, 'actor' => null, 'ip' => '1.2.3.5' ], [], [], 'user_text',
				[ 'ext-checkuser-investigate-table-cell-interactive' ],
				[ 'data-sort-value' => '1.2.3.5' ],
			],
			'$name as activity' => [
				[ 'first_action' => '20240405060708', 'last_action' => '20240406060708' ], [], [], 'activity',
				[ 'ext-checkuser-compare-table-cell-activity' ], [ 'data-sort-value' => '2024040520240406' ],
			],
			'$name as agent' => [
				[ 'agent' => 'test' ], [], [], 'agent',
				[
					'ext-checkuser-compare-table-cell-user-agent', 'ext-checkuser-investigate-table-cell-pinnable',
					'ext-checkuser-investigate-table-cell-interactive',
				],
				[ 'data-field' => 'agent', 'data-value' => 'test', 'data-sort-value' => 'test' ],
			],
		];
	}

	public function testGetCellAttrsForHiddenUser() {
		// Assign the rights to the main context authority, which will be used by the object under test.
		RequestContext::getMain()->setAuthority( $this->mockRegisteredAuthorityWithoutPermissions( [ 'hideuser' ] ) );
		$this->testGetCellAttrs(
			[ 'user_text' => self::$hiddenUser->getName(), 'actor' => self::$hiddenUser->getActorId() ],
			[], [], 'user_text', [ 'ext-checkuser-investigate-table-cell-interactive' ],
			[
				'data-field' => 'user_text',
				'data-value' => '(rev-deleted-user)', 'data-sort-value' => '(rev-deleted-user)',
			]
		);
	}

	/**
	 * @dataProvider provideDoQuery
	 */
	public function testDoQuery( $targets, $excludeTargets, $expected ) {
		$services = $this->getServiceContainer();

		$tokenQueryManager = $this->getMockBuilder( TokenQueryManager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getDataFromRequest' ] )
			->getMock();
		$tokenQueryManager->method( 'getDataFromRequest' )
			->willReturn( [
				'targets' => $targets,
				'exclude-targets' => $excludeTargets,
			] );

		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )
			->willReturn( 11111 );

		$user2 = $this->createMock( UserIdentity::class );
		$user2->method( 'getId' )
			->willReturn( 22222 );

		$user3 = $this->createMock( UserIdentity::class );
		$user3->method( 'getId' )
			->willReturn( 0 );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'getUserIdentityByName' )
			->willReturnMap(
				[
					[ 'User1', 0, $user, ],
					[ 'User2', 0, $user2, ],
					[ 'InvalidUser', 0, $user3, ],
					[ '', 0, $user3, ],
					[ '1.2.3.9/120', 0, $user3, ]
				]
			);

		$compareService = new CompareService(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				CompareService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancerFactory(),
			$userIdentityLookup,
			$services->get( 'CheckUserLookupUtils' )
		);

		$durationManager = $this->createMock( DurationManager::class );

		$pager = $this->getObjectUnderTest( [
			'tokenQueryManager' => $tokenQueryManager,
			'compareService' => $compareService,
			'durationManager' => $durationManager,
		] );
		$pager->doQuery();

		$this->assertSame( $expected, $pager->mResult->numRows() );
	}

	public static function provideDoQuery() {
		// $targets, $excludeTargets, $expected
		return [
			'Valid and invalid targets' => [ [ 'User1', 'InvalidUser', '1.2.3.9/120' ], [], 2 ],
			'Valid and empty targets' => [ [ 'User1', '' ], [], 2 ],
			'Valid user target' => [ [ 'User2' ], [], 1 ],
			'Valid user target with excluded name' => [ [ 'User2' ], [ 'User2' ], 0 ],
			'Valid user target with excluded IP' => [ [ 'User2' ], [ '1.2.3.4' ], 0 ],
			'Valid IP target' => [ [ '1.2.3.4' ], [], 4 ],
			'Valid IP target with users excluded' => [ [ '1.2.3.4' ], [ 'User1', 'User2' ], 2 ],
			'Valid IP range target' => [ [ '1.2.3.0/24' ], [], 7 ],
		];
	}

	public function addDBDataOnce() {
		$this->addTestingDataToDB();

		// Get a test user and apply a 'hideuser' block to that test user
		$hiddenUser = $this->getTestUser()->getUser();
		// Place a 'hideuser' block on the test user to hide the user
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$hiddenUser, $this->getTestUser( [ 'sysop', 'suppress' ] )->getUser(),
				'infinity', 'block to hide the test user', [ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		self::$hiddenUser = $hiddenUser;
	}
}
