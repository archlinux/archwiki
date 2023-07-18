<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Hooks;

use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\Extension\AbuseFilter\Hooks\Handlers\CheckUserHandler;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Hooks\Handlers\CheckUserHandler
 * @covers ::__construct
 */
class CheckUserHandlerTest extends MediaWikiUnitTestCase {

	private function getCheckUserHandler(): CheckUserHandler {
		$filterUser = $this->createMock( FilterUser::class );
		$filterUser->method( 'getUserIdentity' )
			->willReturn( new UserIdentityValue( 1, 'Abuse filter' ) );
		return new CheckUserHandler( $filterUser );
	}

	private function commonInsertHookAssertions( $shouldChange, $agentField, $ip, $xff, $row ) {
		if ( $shouldChange ) {
			$this->assertSame(
				'127.0.0.1',
				$ip,
				'IP should have changed to 127.0.0.1 because the abuse filter user is making the action.'
			);
			$this->assertFalse(
				$xff,
				'XFF string should have been blanked because the abuse filter user is making the action.'
			);
			$this->assertSame(
				'',
				$row[$agentField],
				'User agent should have been blanked because the abuse filter is making the action.'
			);
		} else {
			$this->assertSame(
				'1.2.3.4',
				$ip,
				'IP should have not been modified by AbuseFilter handling the checkuser insert row hook.'
			);
			$this->assertSame(
				'1.2.3.5',
				$xff,
				'XFF should have not been modified by AbuseFilter handling the checkuser insert row hook.'
			);
			$this->assertArrayNotHasKey(
				$agentField,
				$row,
				'User agent should have not been modified by AbuseFilter handling the checkuser insert row hook.'
			);
		}
	}

	/**
	 * @covers ::onCheckUserInsertChangesRow
	 * @dataProvider provideDataForCheckUserInsertHooks
	 */
	public function testOnCheckUserInsertChangesRow( $user, $shouldChange ) {
		$checkUserHandler = $this->getCheckUserHandler();
		$ip = '1.2.3.4';
		$xff = '1.2.3.5';
		$row = [];
		$checkUserHandler->onCheckUserInsertChangesRow( $ip, $xff, $row, $user, null );
		$this->commonInsertHookAssertions( $shouldChange, 'cuc_agent', $ip, $xff, $row );
	}

	/**
	 * @covers ::onCheckUserInsertPrivateEventRow
	 * @dataProvider provideDataForCheckUserInsertHooks
	 */
	public function testOnCheckUserInsertPrivateEventRow( $user, $shouldChange ) {
		$checkUserHandler = $this->getCheckUserHandler();
		$ip = '1.2.3.4';
		$xff = '1.2.3.5';
		$row = [];
		$checkUserHandler->onCheckUserInsertPrivateEventRow( $ip, $xff, $row, $user, null );
		$this->commonInsertHookAssertions( $shouldChange, 'cupe_agent', $ip, $xff, $row );
	}

	/**
	 * @covers ::onCheckUserInsertLogEventRow
	 * @dataProvider provideDataForCheckUserInsertHooks
	 */
	public function testOnCheckUserInsertLogEventRow( $user, $shouldChange ) {
		$checkUserHandler = $this->getCheckUserHandler();
		$ip = '1.2.3.4';
		$xff = '1.2.3.5';
		$row = [];
		$checkUserHandler->onCheckUserInsertLogEventRow( $ip, $xff, $row, $user, 1, null );
		$this->commonInsertHookAssertions( $shouldChange, 'cule_agent', $ip, $xff, $row );
	}

	public function provideDataForCheckUserInsertHooks() {
		return [
			'Anonymous user' => [ UserIdentityValue::newAnonymous( '127.0.0.1' ), false ],
			'Registered user' => [ new UserIdentityValue( 2, 'Test' ), false ],
			'Abuse filter user' => [ new UserIdentityValue( 1, 'Abuse filter' ), true ],
		];
	}

}
