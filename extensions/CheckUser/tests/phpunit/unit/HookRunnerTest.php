<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit;

use MediaWiki\Extension\CheckUser\Hook\HookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Hook\HookRunner
 */
class HookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield HookRunner::class => [ HookRunner::class ];
	}
}
