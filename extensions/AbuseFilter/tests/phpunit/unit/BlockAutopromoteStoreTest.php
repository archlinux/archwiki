<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use BagOStuff;
use Generator;
use HashBagOStuff;
use MediaWiki\Extension\AbuseFilter\BlockAutopromoteStore;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\BlockAutopromoteStore
 * @covers ::__construct
 */
class BlockAutopromoteStoreTest extends MediaWikiUnitTestCase {

	private function getStore( BagOStuff $cache ): BlockAutopromoteStore {
		return new BlockAutopromoteStore(
			$cache,
			new NullLogger(),
			$this->createMock( FilterUser::class )
		);
	}

	public function provideBlockAutopromote(): Generator {
		$cache = $this->createMock( BagOStuff::class );
		$cache->expects( $this->once() )->method( 'set' )->willReturn( false );
		$cache->method( 'makeKey' )->willReturn( 'foo' );
		yield 'cannot set' => [ $cache, false ];

		yield 'success' => [ new HashBagOStuff(), true ];
	}

	/**
	 * @covers ::blockAutoPromote
	 * @dataProvider provideBlockAutopromote
	 */
	public function testBlockAutopromote( BagOStuff $cache, bool $expected ) {
		$store = $this->getStore( $cache );
		$target = new UserIdentityValue( 1, 'Blocked user' );
		$this->assertSame( $expected, $store->blockAutoPromote( $target, '', 1 ) );
	}

	public function provideUnblockAutopromote(): Generator {
		yield 'not blocked' => [ new HashBagOStuff(), false ];

		$cache = $this->createMock( BagOStuff::class );
		$cache->expects( $this->once() )->method( 'changeTTL' )->willReturn( true );
		$cache->method( 'makeKey' )->willReturn( 'foo' );
		yield 'success' => [ $cache, true ];
	}

	/**
	 * @covers ::unblockAutoPromote
	 * @dataProvider provideUnblockAutopromote
	 */
	public function testUnblockAutopromote( BagOStuff $cache, bool $expected ) {
		$store = $this->getStore( $cache );
		$target = new UserIdentityValue( 1, 'Blocked user' );
		$this->assertSame( $expected, $store->unblockAutoPromote( $target, $target, '' ) );
	}

	/**
	 * @covers ::blockAutoPromote
	 * @covers ::getAutoPromoteBlockStatus
	 * @covers ::unblockAutopromote
	 * @covers ::getAutoPromoteBlockKey
	 */
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
