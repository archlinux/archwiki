<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Filter;

use MediaWiki\Extension\AbuseFilter\Filter\LastEditInfo;
use MediaWikiUnitTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @covers \MediaWiki\Extension\AbuseFilter\Filter\LastEditInfo
 */
class LastEditInfoTest extends MediaWikiUnitTestCase {
	public function testGetters() {
		$userID = 42;
		$userName = 'Admin';
		$timestamp = '20181016155634';
		$lastEditInfo = new LastEditInfo( $userID, $userName, $timestamp );

		$this->assertSame( $userID, $lastEditInfo->getUserID(), 'user ID' );
		$this->assertSame( $userName, $lastEditInfo->getUserName(), 'user name' );
		$this->assertSame( $timestamp, $lastEditInfo->getTimestamp(), 'timestamp' );
	}

	/**
	 * @param mixed $value
	 * @param string $setter
	 * @param string $getter
	 * @dataProvider provideSetters
	 */
	public function testSetters( $value, string $setter, string $getter ) {
		$lastEditInfo = new LastEditInfo( 1, 'x', '123' );

		$lastEditInfo->$setter( $value );
		$this->assertSame( $value, $lastEditInfo->$getter() );
	}

	/**
	 * @return array
	 */
	public static function provideSetters() {
		return [
			'user ID' => [ 163, 'setUserID', 'getUserID' ],
			'username' => [ 'Sysop', 'setUserName', 'getUserName' ],
			'timestamp' => [ '123456', 'setTimestamp', 'getTimestamp' ],
		];
	}
}
