<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use MediaWiki\CheckUser\Services\CheckUserDataPurger;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\Services\CheckUserDataPurger
 */
class CheckUserDataPurgerTest extends MediaWikiUnitTestCase {
	public function testGetPurgeLockKey() {
		$this->assertSame( 'enwiki:PruneCheckUserData', CheckUserDataPurger::getPurgeLockKey( 'enwiki' ) );
	}
}
