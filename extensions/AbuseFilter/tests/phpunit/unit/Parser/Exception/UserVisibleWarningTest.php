<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Parser\Exception;

use MediaWiki\Extension\AbuseFilter\Parser\Exception\UserVisibleWarning;
use MediaWikiUnitTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @group AbuseFilterParser
 *
 * @covers \MediaWiki\Extension\AbuseFilter\Parser\Exception\UserVisibleWarning
 */
class UserVisibleWarningTest extends MediaWikiUnitTestCase {

	public function testGetMessageObj() {
		$excID = 'abusefilter-foo';
		$position = 42;
		$params = [ 'foo' ];
		$message = ( new UserVisibleWarning( $excID, $position, $params ) )->getMessageObj();
		$this->assertSame( 'abusefilter-parser-warning-' . $excID, $message->getKey(), 'msg key' );
		$this->assertArrayEquals( [ $position, ...$params ], $message->getParams(), 'msg params' );
	}
}
