<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\SpecialLogResolveIPRevealLogTypeHandler;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\SpecialLogResolveIPRevealLogTypeHandler
 */
class SpecialLogResolveIPRevealLogTypeHandlerTest extends MediaWikiUnitTestCase {
	/**
	 * @dataProvider rewritesTypeForIPRevealDataProvider
	 */
	public function testRewritesTypeForIPReveal(
		string $expected,
		string $source
	): void {
		$handler = new SpecialLogResolveIPRevealLogTypeHandler();
		$handler->onSpecialLogResolveLogType( [], $source );

		$this->assertSame( $expected, $source );
	}

	public function rewritesTypeForIPRevealDataProvider(): array {
		return [
			'ipreveal get rewritten as checkuser-temporary-account' => [
				'expected' => TemporaryAccountLogger::LOG_TYPE,
				'source' => 'ipreveal',
			],
			'other log types are kept as-is' => [
				'expected' => 'something-else',
				'source' => 'something-else',
			],
		];
	}
}
