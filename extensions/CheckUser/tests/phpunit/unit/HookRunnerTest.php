<?php

namespace MediaWiki\CheckUser\Tests\Unit;

use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \MediaWiki\CheckUser\Hook\HookRunner
 */
class HookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield HookRunner::class => [ HookRunner::class ];
	}
}
