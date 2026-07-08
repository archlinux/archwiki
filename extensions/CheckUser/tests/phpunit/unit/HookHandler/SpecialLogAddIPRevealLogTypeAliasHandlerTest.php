<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\HookHandler\SpecialLogAddIPRevealLogTypeAliasHandler;
use MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SpecialLogAddIPRevealLogTypeAliasHandler
 */
class SpecialLogAddIPRevealLogTypeAliasHandlerTest extends MediaWikiUnitTestCase {

	private SpecialLogAddIPRevealLogTypeAliasHandler $sut;

	public function setUp(): void {
		$this->sut = new SpecialLogAddIPRevealLogTypeAliasHandler();
	}

	/**
	 * @dataProvider rewritesTypeForIPRevealDataProvider
	 */
	public function testRewritesTypeForIPReveal(
		string $expected,
		string $source
	): void {
		$this->sut->onSpecialLogResolveLogType( [], $source );

		$this->assertSame( $expected, $source );
	}

	public static function rewritesTypeForIPRevealDataProvider(): array {
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

	/**
	 * @dataProvider addsIPRevealAliasToPrefixSearchDataProvider
	 */
	public function testAddsIPRevealAliasToPrefixSearch(
		bool $expected,
		bool $logRestrictionsAreConfigured,
		bool $performerHasAccess
	): void {
		$logRestrictions = [];

		if ( $logRestrictionsAreConfigured ) {
			$logRestrictions[ TemporaryAccountLogger::LOG_TYPE ] =
				'ipreveal-log-permission-name';
		}

		$performer = $this->createMock( Authority::class );
		$performer
			->method( 'isAllowed' )
			->with( 'ipreveal-log-permission-name' )
			->willReturn( $performerHasAccess );

		$config = new HashConfig(
			[ MainConfigNames::LogRestrictions => $logRestrictions ],
		);

		$context = $this->createMock( IContextSource::class );
		$context
			->expects( $this->once() )
			->method( 'getAuthority' )
			->willReturn( $performer );
		$context
			->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( $config );

		$subpages = [];

		$this->sut->onSpecialLogGetSubpagesForPrefixSearch(
			$context,
			$subpages
		);

		$this->assertEquals(
			$expected ? [ 'ipreveal' ] : [],
			$subpages
		);
	}

	public static function addsIPRevealAliasToPrefixSearchDataProvider(): array {
		return [
			'When the target log type is not restricted' => [
				'expected' => true,
				'logRestrictionsAreConfigured' => false,
				'performerHasAccess' => false,
			],
			'When the performer is not allowed to access the target log type' => [
				'expected' => false,
				'logRestrictionsAreConfigured' => true,
				'performerHasAccess' => false,
			],
			'When the performer is allowed to access the target log type' => [
				'expected' => true,
				'logRestrictionsAreConfigured' => true,
				'performerHasAccess' => true,
			],
		];
	}
}
