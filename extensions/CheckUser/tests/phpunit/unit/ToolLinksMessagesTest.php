<?php

namespace MediaWiki\CheckUser\Tests\Unit;

use MediaWiki\CheckUser\ToolLinksMessages;
use MediaWiki\Config\HashConfig;
use MediaWiki\Message\Message;
use MediaWiki\ResourceLoader\Context;
use MediaWikiUnitTestCase;

/**
 * @author DannyS712
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\ToolLinksMessages
 */
class ToolLinksMessagesTest extends MediaWikiUnitTestCase {

	public function testGetParsedMessage() {
		$msg = $this->createMock( Message::class );
		$msg->method( 'parse' )->willReturn( 'Parsed result' );

		$context = $this->createMock( Context::class );
		$context->method( 'msg' )
			->with( 'message key' )
			->willReturn( $msg );

		$res = ToolLinksMessages::getParsedMessage(
			$context,
			new HashConfig( [] ),
			'message key'
		);
		$this->assertEquals(
			[ 'message key' => 'Parsed result' ],
			$res
		);
	}

}
