<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use MediaWiki\Extension\AbuseFilter\BlockAutopromoteStore;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\BlockAutopromoteStore
 */
class BlockAutopromoteStoreTest extends MediaWikiUnitTestCase {

	private function getStore( BagOStuff $cache ): BlockAutopromoteStore {
		return new BlockAutopromoteStore(
			$cache,
			new NullLogger(),
			$this->createMock( FilterUser::class )
		);
	}

	public function testBlockAutopromote_success() {
		$store = $this->getStore( new HashBagOStuff() );
		$target = new UserIdentityValue( 1, 'Blocked user' );
		$this->assertTrue( $store->blockAutoPromote( $target, '', 1 ) );
	}

	public function testBlockAutopromote_cannotSet() {
		$cache = $this->createMock( BagOStuff::class );
		$cache->expects( $this->once() )->method( 'set' )->willReturn( false );
		$cache->method( 'makeKey' )->willReturn( 'foo' );
		$store = $this->getStore( $cache );
		$target = new UserIdentityValue( 1, 'Blocked user' );
		$this->assertFalse( $store->blockAutoPromote( $target, '', 1 ) );
	}

	public function testUnblockAutopromote_success() {
		$cache = $this->createMock( BagOStuff::class );
		$cache->expects( $this->once() )->method( 'changeTTL' )->willReturn( true );
		$cache->method( 'makeKey' )->willReturn( 'foo' );
		$store = $this->getStore( $cache );
		$target = new UserIdentityValue( 1, 'Blocked user' );
		$this->assertTrue( $store->unblockAutoPromote( $target, $target, '' ) );
	}

	public function testUnblockAutopromote_notBlocked() {
		$store = $this->getStore( new HashBagOStuff() );
		$target = new UserIdentityValue( 1, 'Blocked user' );
		$this->assertFalse( $store->unblockAutoPromote( $target, $target, '' ) );
	}

	public function testRoundTrip() {
		$cache = new HashBagOStuff();
		$store = $this->getStore( $cache );
		$target = new UserIdentityValue( 1, 'Blocked user' );
		$this->assertTrue( $store->blockAutoPromote( $target, '', 3000 ), 'block' );
		$this->assertSame( 1, $store->getAutoPromoteBlockStatus( $target ), 'should be blocked' );
		$this->assertTrue( $store->unblockAutoPromote( $target, $target, '' ), 'can unblock' );
		$this->assertSame( 0, $store->getAutoPromoteBlockStatus( $target ), 'should not be blocked' );
	}
}
