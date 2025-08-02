<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Html\FormOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for AbstractCheckUserPager class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager
 */
class AbstractCheckUserPagerTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( 'CheckUserCIDRLimit', [
			'IPv4' => 16,
			'IPv6' => 19,
		] );
	}

	protected function setUpObjectArguments( $params = [] ) {
		$opts = new FormOptions();
		$opts->add( 'reason', $params['reason'] ?? '' );
		$opts->add( 'period', $params['period'] ?? 0 );
		$opts->add( 'limit', $params['limit'] ?? 0 );
		$opts->add( 'dir', $params['dir'] ?? '' );
		$opts->add( 'offset', $params['offset'] ?? '' );
		$services = $this->getServiceContainer();
		return [
			$opts,
			UserIdentityValue::newAnonymous( '1.2.3.4' ),
			'userips',
			$services->getService( 'CheckUserTokenQueryManager' ),
			$services->getUserGroupManager(),
			$services->getCentralIdLookup(),
			$services->getDBLoadBalancerFactory(),
			$services->getSpecialPageFactory(),
			$services->getUserIdentityLookup(),
			$services->getService( 'CheckUserLogService' ),
			$services->getUserFactory(),
			$services->get( 'CheckUserLookupUtils' ),
			$services->getUserOptionsLookup(),
			$services->getDatabaseBlockStore()
		];
	}

	/**
	 * @param array $params
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject( $params = [] ) {
		RequestContext::getMain()->setUser( $this->getTestUser( 'checkuser' )->getUser() );
		$object = new DeAbstractedCheckUserPagerTest(
			...$this->setUpObjectArguments( $params )
		);
		return TestingAccessWrapper::newFromObject( $object );
	}

	public function testSetPeriodConditionCalledInConstructor() {
		# Tests that the ::setPeriodCondition is called by the constructor.
		# Unit tests exist for ::setPeriodCondition.
		ConvertibleTimestamp::setFakeTime( '1653077137' );
		$object = $this->setUpObject( [ 'period' => 7 ] );
		$object->setPeriodCondition();
		$this->assertArrayEquals(
			[ $object->mDb->timestamp( '20220513000000' ), '' ],
			$object->getRangeOffsets(),
			false,
			false,
			'::setPeriodCondition may not have been called by the constructor. This method needs ' .
			'to be called by the constructor.'
		);
	}

	/** @dataProvider provideUserWasBlocked */
	public function testUserWasBlocked( $block ) {
		$testUser = $this->getTestUser()->getUser();
		if ( $block ) {
			$userAuthority = $this->mockRegisteredUltimateAuthority();
			$this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
				$testUser,
				$userAuthority,
				'1 second'
			)->placeBlock();
		}
		$object = $this->setUpObject();
		$this->assertSame(
			$block,
			$object->userWasBlocked( $testUser->getName() )
		);
	}

	public static function provideUserWasBlocked() {
		return [
			'User was previously blocked' => [ true ],
			'User never previously blocked' => [ false ]
		];
	}

	public function testGetEmptyBodyNoCheckLast() {
		$object = $this->setUpObject();
		$object->target = UserIdentityValue::newRegistered( 1, 'test' );
		$object->xfor = false;
		$this->assertSame(
			wfMessage( 'checkuser-nomatch' )->parseAsBlock() . "\n",
			$object->getEmptyBody(),
			'The checkuser-nomatch message should have been returned.'
		);
	}

	public function testUserBlockFlagsForGloballyBlockedIP() {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );
		$object = $this->setUpObject();
		$ip = '1.2.3.4';
		$user = UserIdentityValue::newAnonymous( $ip );
		// Globally block the IP
		GlobalBlockingServices::wrap( $this->getServiceContainer() )->getGlobalBlockManager()->block(
			$ip, 'test', '1 week', $this->getTestUser( [ 'steward' ] )->getUserIdentity()
		);
		$this->assertSame(
			[ '<strong>(' . wfMessage( 'checkuser-gblocked' )->escaped() . ')</strong>' ],
			$object->userBlockFlags( $ip, $user ),
			'The checkuser-gblocked flag should have been returned as the IP is globally blocked.'
		);
	}

	public function testUserBlockFlagsForGloballyBlockedUser() {
		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );
		$object = $this->setUpObject();
		$ip = '1.2.3.4';
		$user = $this->getMutableTestUser()->getUserIdentity();
		// Globally block the test user
		GlobalBlockingServices::wrap( $this->getServiceContainer() )->getGlobalBlockManager()->block(
			$user->getName(), 'test', '1 week', $this->getTestUser( [ 'steward' ] )->getUserIdentity()
		);
		$this->assertContains(
			'<strong>(' . wfMessage( 'checkuser-gblocked' )->escaped() . ')</strong>',
			$object->userBlockFlags( $ip, $user ),
			'The checkuser-gblocked flag should have been returned as the IP is globally blocked.'
		);
	}

	public function testUserBlockFlagsTorExitNode() {
		$this->markTestSkippedIfExtensionNotLoaded( 'TorBlock' );
		$object = $this->setUpObject();
		// TEST-NET-1
		$ip = '192.0.2.111';
		$user = UserIdentityValue::newAnonymous( $ip );
		$this->assertSame(
			[ '<strong>(' . wfMessage( 'checkuser-torexitnode' )->escaped() . ')</strong>' ],
			$object->userBlockFlags( $ip, $user ),
			'The checkuser-torexitnode message should have been returned; the IP was not detected as an exit node'
		);
	}

	/** @dataProvider provideTestFormOptionsLimitValue */
	public function testFormOptionsLimitValue( $formSubmittedLimit, $maximumLimit, $expectedLimit ) {
		$this->overrideConfigValue( 'CheckUserMaximumRowCount', $maximumLimit );
		$object = $this->setUpObject( [ 'limit' => $formSubmittedLimit ] );
		$this->assertSame(
			$expectedLimit,
			$object->mLimit,
			'The limit used for running the check was not the expected value given the user defined and maximum limit.'
		);
	}

	public static function provideTestFormOptionsLimitValue() {
		return [
			'Empty limit' => [ 0, 5000, 5000 ],
			'Limit under maximum limit' => [ 200, 5000, 200 ],
			'Limit over maximum limit' => [ 500, 200, 200 ],
		];
	}

	/** @dataProvider provideGetCheckUserHelperFieldset */
	public function testGetCheckUserHelperFieldset(
		$collapseByDefaultConfigValue, $userPreferenceValue, $shouldBeByDefaultCollapsed, $resultRowCount
	) {
		$this->overrideConfigValue( 'CheckUserCollapseCheckUserHelperByDefault', $collapseByDefaultConfigValue );
		$object = $this->setUpObject();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			RequestContext::getMain()->getUser(),
			'checkuser-helper-table-collapse-by-default',
			$userPreferenceValue
		);
		$userOptionsManager->saveOptions( RequestContext::getMain()->getUser() );
		$object->mResult = $this->createMock( IResultWrapper::class );
		$object->mResult->method( 'numRows' )->willReturn( $resultRowCount );
		$fieldset = TestingAccessWrapper::newFromObject( $object->getCheckUserHelperFieldset() );
		$this->assertSame(
			'mw-checkuser-helper-fieldset',
			$fieldset->outerClass,
			'CheckUser fieldset should have surrounding class.'
		);
		$this->assertSame(
			wfMessage( 'checkuser-helper-label' )->text(),
			$fieldset->mWrapperLegend,
			'Wrapper legend text is incorrect.'
		);
		$this->assertFalse(
			$fieldset->mShowSubmit,
			'The fieldset should not show a submit button.'
		);
		$this->assertTrue(
			$fieldset->mCollapsible,
			'The fieldset should be collapsable.'
		);
		$this->assertSame(
			$shouldBeByDefaultCollapsed,
			$fieldset->mCollapsed,
			'The default collapsed state for the fieldset is not correct.'
		);
	}

	public static function provideGetCheckUserHelperFieldset() {
		return [
			'wgCheckUserCollapseCheckUserHelperByDefault set to true' => [
				// The value of wgCheckUserCollapseCheckUserHelperByDefault
				true,
				// The value for the user's "checkuser-helper-table-collapse-by-default" preference
				Preferences::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT,
				// Whether the summary table should be collapsed by default
				true,
				// The number of results in the mResult object
				1,
			],
			'wgCheckUserCollapseCheckUserHelperByDefault set to false' => [
				false, Preferences::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT, false, 2,
			],
			'wgCheckUserCollapseCheckUserHelperByDefault set to an integer less than the row count' => [
				3, Preferences::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT, true, 5,
			],
			'wgCheckUserCollapseCheckUserHelperByDefault set to an integer greater than the row count' => [
				10, Preferences::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT, false, 3,
			],
			'User preference set to always' => [
				false, Preferences::CHECKUSER_HELPER_ALWAYS_COLLAPSE_BY_DEFAULT, true, 1,
			],
			'User preference set to never' => [
				true, Preferences::CHECKUSER_HELPER_NEVER_COLLAPSE_BY_DEFAULT, false, 2,
			],
			'User preference set to integer less than row count' => [ false, 3, true, 5 ],
			'User preference set to integer more than row count' => [ true, 10, false, 3 ],
		];
	}
}
