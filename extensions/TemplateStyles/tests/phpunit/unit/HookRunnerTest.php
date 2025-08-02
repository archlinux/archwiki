<?php

namespace MediaWiki\Extension\TemplateStyles\Tests\Unit;

use MediaWiki\Extension\TemplateStyles\Hooks\HookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \MediaWiki\Extension\TemplateStyles\Hooks\HookRunner
 */
class HookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield HookRunner::class => [ HookRunner::class ];
	}
}
