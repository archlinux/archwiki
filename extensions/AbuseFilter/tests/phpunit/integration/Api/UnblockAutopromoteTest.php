<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use ApiTestCase;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\AbuseFilter\BlockAutopromoteStore;
use MediaWiki\MediaWikiServices;
use User;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Api\UnblockAutopromote
 * @covers ::__construct
 * @group medium
 */
class UnblockAutopromoteTest extends ApiTestCase {

	/**
	 * @covers ::execute
	 */
	public function testExecute_noPermissions() {
		$this->setExpectedApiException( [
			'apierror-permissiondenied',
			wfMessage( 'action-abusefilter-modify' )
		] );

		$store = $this->createMock( BlockAutopromoteStore::class );
		$store->expects( $this->never() )->method( 'unblockAutopromote' );
		$this->setService( BlockAutopromoteStore::SERVICE_NAME, $store );

		$this->doApiRequestWithToken( [
			'action' => 'abusefilterunblockautopromote',
			'user' => 'User'
		], null, self::getTestUser()->getUser(), 'csrf' );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_invalidUser() {
		$invalid = 'invalid#username';
		$this->setExpectedApiException( [
			'apierror-baduser',
			'user',
			$invalid
		] );

		$store = $this->createMock( BlockAutopromoteStore::class );
		$store->expects( $this->never() )->method( 'unblockAutopromote' );
		$this->setService( BlockAutopromoteStore::SERVICE_NAME, $store );

		$this->doApiRequestWithToken( [
			'action' => 'abusefilterunblockautopromote',
			'user' => $invalid
		], null, self::getTestUser()->getUser(), 'csrf' );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_blocked() {
		$this->setExpectedApiException( 'apierror-blocked', 'blocked' );
		$user = self::getTestUser( [ 'sysop' ] )->getUser();

		$store = $this->createMock( BlockAutopromoteStore::class );
		$store->expects( $this->never() )->method( 'unblockAutopromote' );
		$this->setService( BlockAutopromoteStore::SERVICE_NAME, $store );

		$block = new DatabaseBlock( [ 'expiry' => '1 day' ] );
		$block->setTarget( $user );
		$block->setBlocker( self::getTestSysop()->getUser() );
		MediaWikiServices::getInstance()->getDatabaseBlockStore()->insertBlock( $block );

		$this->doApiRequestWithToken( [
			'action' => 'abusefilterunblockautopromote',
			'user' => 'User'
		], null, $user, 'csrf' );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_nothingToDo() {
		$target = 'User';
		$user = self::getTestUser( [ 'sysop' ] )->getUser();
		$this->setExpectedApiException( [ 'abusefilter-reautoconfirm-none', $target ] );

		$store = $this->createMock( BlockAutopromoteStore::class );
		$store->expects( $this->once() )
			->method( 'unblockAutopromote' )
			->with( $this->isInstanceOf( User::class ), $user, $this->anything() )
			->willReturn( false );
		$this->setService( BlockAutopromoteStore::SERVICE_NAME, $store );

		$this->doApiRequestWithToken( [
			'action' => 'abusefilterunblockautopromote',
			'user' => $target
		], null, $user, 'csrf' );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_success() {
		$target = 'User';
		$user = self::getTestUser( [ 'sysop' ] )->getUser();

		$store = $this->createMock( BlockAutopromoteStore::class );
		$store->expects( $this->once() )
			->method( 'unblockAutopromote' )
			->with( $this->isInstanceOf( User::class ), $user, $this->anything() )
			->willReturn( true );
		$this->setService( BlockAutopromoteStore::SERVICE_NAME, $store );

		$result = $this->doApiRequestWithToken( [
			'action' => 'abusefilterunblockautopromote',
			'user' => $target
		], null, $user, 'csrf' );

		$this->assertArrayEquals(
			[ 'abusefilterunblockautopromote' => [ 'user' => $target ] ],
			$result[0],
			false,
			true
		);
	}
}
