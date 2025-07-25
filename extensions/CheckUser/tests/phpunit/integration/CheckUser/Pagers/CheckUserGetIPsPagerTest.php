<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers\Mocks\MockTemplateParser;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\UserIdentityValue;
use TestUser;
use Wikimedia\IPUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserGetIPsPager class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager
 */
class CheckUserGetIPsPagerTest extends CheckUserPagerTestBase {

	use CheckUserTempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->checkSubtype = SpecialCheckUser::SUBTYPE_GET_IPS;
		$this->defaultUserIdentity = $this->getTestUser()->getUserIdentity();
		$this->defaultCheckType = 'userips';
		// Set a fake time to avoid the test data being purged.
		ConvertibleTimestamp::setFakeTime( '20230405060808' );
	}

	/**
	 * Tests that the template parameters provided to the GetIPsLine.mustache match
	 * the expected values. Does not test the mustache file which includes some
	 * conditional logic, HTML and whitespace.
	 *
	 * @dataProvider provideFormatRow
	 */
	public function testFormatRow( $row, $expectedTemplateParams ) {
		$object = $this->setUpObject();
		$object->templateParser = new MockTemplateParser();
		$row = array_merge( $this->getDefaultRowFieldValues(), $row );
		$object->formatRow( (object)$row );
		$this->assertNotNull(
			$object->templateParser->lastCalledWith,
			'The template parser was not called by formatRow.'
		);
		$this->assertSame(
			'GetIPsLine',
			$object->templateParser->lastCalledWith[0],
			'formatRow did not call the correct mustache file.'
		);
		$this->assertArrayEquals(
			$expectedTemplateParams,
			array_filter(
				$object->templateParser->lastCalledWith[1],
				static function ( $key ) use ( $expectedTemplateParams ) {
					return array_key_exists( $key, $expectedTemplateParams );
				},
				ARRAY_FILTER_USE_KEY
			),
			false,
			true,
			'The template parameters do not match the expected template parameters. If changes have been ' .
			'made to the template parameters make sure you update the tests.'
		);
	}

	public static function provideFormatRow() {
		// @todo test the rest of the template parameters.
		return [
			'Test ipEditCount' => [
				// The $row provided to ::formatRow as an array (this will be converted to an object).
				[],
				// The expected template parameters (other template keys are ignored for this test case).
				[
					'showIpCounts' => true,
					'ipEditCount' => wfMessage( 'checkuser-ipeditcount' )->numParams( 2 )->escaped()
				]
			],
			'Test edit count' => [ [ 'count' => 555 ], [ 'editCount' => 555 ] ],
			'Test ip64EditCount' => [
				[ 'ip' => '2001:db8::1', 'ip_hex' => IPUtils::toHex( '2001:db8::1' ) ],
				[
					'showIpCounts' => true,
					'ip64EditCount' => wfMessage( 'checkuser-ipeditcount-64' )->numParams( 2 )->escaped()
				]
			],
			'Test toolLinks' => [
				[], [ 'toolLinks' => wfMessage( 'checkuser-toollinks', urlencode( '127.0.0.1' ) )->parse() ]
			],
		];
	}

	/** @inheritDoc */
	public function getDefaultRowFieldValues(): array {
		return [
			'ip' => '127.0.0.1',
			'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
			'count' => 1,
			'first' => $this->getDb()->timestamp(),
			'last' => $this->getDb()->timestamp(),
		];
	}

	public function testUserBlockFlagsTorExitNode() {
		$this->markTestSkippedIfExtensionNotLoaded( 'TorBlock' );
		$object = $this->setUpObject();
		// TEST-NET-1
		$ip = '192.0.2.111';
		$this->assertSame(
			'<strong>(' . wfMessage( 'checkuser-torexitnode' )->escaped() . ')</strong>',
			$object->getIPBlockInfo( $ip ),
			'The checkuser-torexitnode message should have been returned; the IP was not detected as an exit node'
		);
	}

	/** @dataProvider provideGetQueryInfo */
	public function testGetQueryInfo( $table, $expectedQueryInfo ) {
		$this->commonTestGetQueryInfo(
			UserIdentityValue::newRegistered( 1, 'Testing' ), null, $table, $expectedQueryInfo
		);
	}

	public static function provideGetQueryInfo() {
		return [
			'cu_changes table' => [
				// The $table argument to ::getQueryInfo
				'cu_changes',
				// The expected query info returned by ::getQueryInfo (we are only interested in testing the query info
				// added by ::getQueryInfo and not the info added by the table specific methods).
				[
					'tables' => [ 'cu_changes' ],
					'conds' => [ 'actor_user' => 1 ],
					'options' => [
						'USE INDEX' => [ 'cu_changes' => 'cuc_actor_ip_time' ],
						'GROUP BY' => [ 'ip', 'ip_hex' ]
					],
					// Verify that fields and join_conds set as arrays, but we are not testing their values.
					'fields' => [], 'join_conds' => [],
				]
			],
			'cu_log_event table' => [
				'cu_log_event',
				[
					'tables' => [ 'cu_log_event' ],
					'conds' => [ 'actor_user' => 1 ],
					'options' => [
						'USE INDEX' => [ 'cu_log_event' => 'cule_actor_ip_time' ],
						'GROUP BY' => [ 'ip', 'ip_hex' ]
					],
					'fields' => [], 'join_conds' => [],
				]
			],
			'cu_private_event table' => [
				'cu_private_event',
				[
					'tables' => [ 'cu_private_event' ],
					'conds' => [ 'actor_user' => 1 ],
					'options' => [
						'USE INDEX' => [ 'cu_private_event' => 'cupe_actor_ip_time' ],
						'GROUP BY' => [ 'ip', 'ip_hex' ]
					],
					'fields' => [], 'join_conds' => [],
				]
			],
		];
	}

	public function testGetCountForIPActionsPerTableWithStartOffset() {
		$this->overrideConfigValue( 'CheckUserCIDRLimit', [ 'IPv4' => 16, 'IPv6' => 19 ] );
		$object = $this->setUpObject();
		$object->startOffset = $this->getDb()->timestamp( '20230405060709' );
		$object->target = $this->getServiceContainer()->getUserIdentityLookup()
			->getUserIdentityByName( 'CheckUserGetIPsPagerTestUser' );
		// The 'total' would be 2 if the startOffset value was not used in the query.
		$this->assertSame(
			[ 'total' => 1, 'by_this_target' => 1 ],
			$object->getCountForIPActionsPerTable( '127.0.0.1', 'cu_changes' ),
			'::getCountForIPActionsPerTable did not return the expected array.'
		);
	}

	/** @dataProvider provideInvalidIPsAndRanges */
	public function testGetCountForIPActionsPerTableReturnsNullOnInvalidIP( $invalidIPOrInvalidRange, $table ) {
		$this->overrideConfigValue( 'CheckUserCIDRLimit', [ 'IPv4' => 16, 'IPv6' => 19 ] );
		$object = $this->setUpObject();
		$this->assertNull(
			$object->getCountForIPActionsPerTable( $invalidIPOrInvalidRange, $table ),
			'::getCountForIPActionsPerTable should return false on invalid range or invalid IP.'
		);
	}

	public static function provideInvalidIPsAndRanges() {
		return [
			'Invalid IPv4' => [ '123454.5.4.3', 'cu_changes' ],
			'Invalid IPv6' => [ '123123:123123123123:12', 'cu_changes' ],
			'Invalid IPv6 for cu_private_event' => [ '123123:123123123123:12', 'cu_private_event' ],
			'Invalid IP' => [ 'test', 'cu_changes' ],
			'Invalid IP for cu_log_event table' => [ 'test', 'cu_log_event' ],
			'Invalid IPv4 range' => [ '127.0.0.1/45', 'cu_changes' ],
		];
	}

	public function addDBDataOnce() {
		$this->disableAutoCreateTempUser();
		// Add two testing edits (one performed by an IPv4 and one performed by a user).
		$testUser = ( new TestUser( 'CheckUserGetIPsPagerTestUser' ) )->getUser();
		$testPage = $this->getExistingTestPage();
		// Clear any events inserted to CheckUser tables by getting the test page and test user.
		$this->truncateTables( [ 'cu_changes', 'cu_log_event', 'cu_private_event' ] );
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$this->editPage(
			$testPage, 'Test content', 'Test summary', NS_MAIN,
			$this->getServiceContainer()->getUserFactory()
				->newFromUserIdentity( UserIdentityValue::newAnonymous( '127.0.0.1' ) )
		);
		ConvertibleTimestamp::setFakeTime( '20230405060709' );
		$this->editPage( $testPage, 'Test content2', 'Test summary', NS_MAIN, $testUser );

		// Add two testing edits made by two IPv6 addresses on the same /64 range.
		RequestContext::getMain()->getRequest()->setIP( '2001:db8::1' );
		ConvertibleTimestamp::setFakeTime( '20230405060710' );
		$this->editPage(
			$testPage, 'Test content3', 'Test summary', NS_MAIN,
			$this->getServiceContainer()->getUserFactory()
				->newFromUserIdentity( UserIdentityValue::newAnonymous( '2001:db8::1' ) )
		);
		RequestContext::getMain()->getRequest()->setIP( '2001:db8::2' );
		ConvertibleTimestamp::setFakeTime( '20230405060711' );
		$this->editPage(
			$testPage, 'Test content4', 'Test summary', NS_MAIN,
			$this->getServiceContainer()->getUserFactory()
				->newFromUserIdentity( UserIdentityValue::newAnonymous( '2001:db8::2' ) )
		);
		// Reset the fake time to avoid affecting other tests.
		ConvertibleTimestamp::setFakeTime( false );
	}
}
