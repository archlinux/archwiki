<?php

namespace MediaWiki\Tests\HookContainer;

use MediaWiki\Api\ApiHookRunner;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\ResourceLoader as RL;

/**
 * Tests that all arguments passed into HookRunner are passed along to HookContainer.
 * @covers \MediaWiki\HookContainer\HookRunner
 * @covers \MediaWiki\Api\ApiHookRunner
 * @covers \MediaWiki\ResourceLoader\HookRunner
 * @package MediaWiki\Tests\HookContainer
 */
class HookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield ApiHookRunner::class => [ ApiHookRunner::class ];
		yield HookRunner::class => [ HookRunner::class ];
		yield RL\HookRunner::class => [ RL\HookRunner::class ];
	}
}
