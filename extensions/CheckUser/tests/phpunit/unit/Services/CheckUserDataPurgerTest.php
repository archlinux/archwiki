<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Services;

use MediaWiki\Extension\CheckUser\Services\CheckUserDataPurger;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Services\CheckUserDataPurger
 */
class CheckUserDataPurgerTest extends MediaWikiUnitTestCase {
	public function testGetPurgeLockKey() {
		$this->assertSame( 'enwiki:PruneCheckUserData', CheckUserDataPurger::getPurgeLockKey( 'enwiki' ) );
	}
}
