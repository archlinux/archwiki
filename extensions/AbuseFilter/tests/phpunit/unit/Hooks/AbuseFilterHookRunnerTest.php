<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Hooks;

use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner
 */
class AbuseFilterHookRunnerTest extends HookRunnerTestBase {

	public function provideHookRunners() {
		yield AbuseFilterHookRunner::class => [ AbuseFilterHookRunner::class ];
	}
}
