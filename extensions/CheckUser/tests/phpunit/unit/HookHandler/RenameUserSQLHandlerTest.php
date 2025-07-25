<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\RenameUserSQLHandler;
use MediaWiki\RenameUser\RenameuserSQL;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\HookHandler\RenameUserSQLHandler
 */
class RenameUserSQLHandlerTest extends MediaWikiUnitTestCase {

	public function testOnRenameUserSQL() {
		$mockRenameUserSqlObject = $this->getMockBuilder( RenameuserSQL::class )
			->disableOriginalConstructor()
			->getMock();
		$mockRenameUserSqlObject->tables = [];
		( new RenameUserSQLHandler() )->onRenameUserSQL( $mockRenameUserSqlObject );
		$this->assertArrayEquals(
			[ 'cu_log' => [ 'cul_target_text', 'cul_target_id' ] ],
			$mockRenameUserSqlObject->tables,
			true,
			true,
			'RenameUserSQL hook handler did not add the correct tables and fields.'
		);
	}
}
