<?php

use MediaWiki\Extension\TemplateData\Hooks;
use MediaWiki\User\UserIdentity;

/**
 * @group Database
 * @covers \MediaWiki\Extension\TemplateData\Hooks
 * @license GPL-2.0-or-later
 */
class TemplateDataHooksTest extends MediaWikiIntegrationTestCase {

	private Hooks $hookHandler;

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
		// TODO: Am I meant to mock the config?
		$this->hookHandler = new Hooks( $this->getServiceContainer()->getMainConfig() );
	}

	/**
	 * Test situations where Hooks::onSaveUserOptions will abort saving
	 *
	 * @param array $originalOptions
	 * @param array $modifiedOptions
	 * @param array $overrideConfig
	 *
	 * @covers \MediaWiki\Extension\TemplateData\Hooks::onSaveUserOptions
	 * @dataProvider provideOnSaveUserOptionsAbort
	 */
	public function testOnSaveUserOptionsAbort( $originalOptions, $modifiedOptions, $overrideConfig = [] ) {
		if ( $overrideConfig ) {
			$this->overrideConfigValues( $overrideConfig );
		}
		$user = $this->createMock( UserIdentity::class );
		$this->hookHandler->onSaveUserOptions( $user, $modifiedOptions, $originalOptions );
		// Assert that the array key 'templatedata-favorite-templates' is not set
		$this->assertArrayNotHasKey( 'templatedata-favorite-templates', $modifiedOptions );
	}

	/**
	 * Test situations where Hooks::onSaveUserOptions will modify the options
	 *
	 * @param array $originalOptions
	 * @param array $modifiedOptions
	 * @param array $expectedOptions
	 * @param array $overrideConfig
	 *
	 * @covers \MediaWiki\Extension\TemplateData\Hooks::onSaveUserOptions
	 * @dataProvider provideOnSaveUserOptions
	 */
	public function testOnSaveUserOptions(
		$originalOptions,
		$modifiedOptions,
		$expectedOptions,
		$overrideConfig = []
	) {
		if ( $overrideConfig ) {
			$this->overrideConfigValues( $overrideConfig );
		}
		$user = $this->createMock( UserIdentity::class );
		$this->hookHandler->onSaveUserOptions( $user, $modifiedOptions, $originalOptions );
		// Assert that the array key 'templatedata-favorite-templates' is set
		$this->assertArrayHasKey( 'templatedata-favorite-templates', $modifiedOptions );
		// Assert that the options have been modified as expected
		$this->assertSame(
			$expectedOptions[ 'templatedata-favorite-templates' ],
			$modifiedOptions[ 'templatedata-favorite-templates' ]
		);
	}

	/**
	 * Provide data for testOnSaveUserOptionsAbort
	 * Arrays are, in order:
	 * - original options
	 * - modified options
	 * - config overrides (optional)
	 *
	 * @return array
	 */
	public static function provideOnSaveUserOptionsAbort() {
		return [
			'more than TemplateDataMaxFavorites' => [
				[
					'templatedata-favorite-templates' => '[7,15,24]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24,1,2,3,4]'
				],
				[
					'TemplateDataMaxFavorites' => 5
				],
			],
			'invalid JSON' => [
				[
					'templatedata-favorite-templates' => '[7,15,24]'
				],
				[
					'templatedata-favorite-templates' => 'fox'
				],
			],
		];
	}

	/**
	 * Provide data for testOnSaveUserOptions
	 * Arrays are, in order:
	 * - original options
	 * - modified options
	 * - expected options after onSaveUserOptions has validated
	 * - config overrides (optional)
	 *
	 * @return array
	 */
	public static function provideOnSaveUserOptions() {
		return [
			'no change' => [
				[
					'templatedata-favorite-templates' => '[7,15,24]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24]'
				],
			],
			'added first favorite' => [
				[
					'templatedata-favorite-templates' => '[]'
				],
				[
					'templatedata-favorite-templates' => '[7]'
				],
				[
					'templatedata-favorite-templates' => '[7]'
				],
			],
			'removed all favorites' => [
				[
					'templatedata-favorite-templates' => '[7,15,24]'
				],
				[
					'templatedata-favorite-templates' => '[]'
				],
				[
					'templatedata-favorite-templates' => '[]'
				],
			],
			'removed duplicate' => [
				[
					'templatedata-favorite-templates' => '[7,15,24]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24,24,17]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24,17]'
				],
			],
			'removed non-integers' => [
				[
					'templatedata-favorite-templates' => '[7,15,24]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24,"fox",8]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24,8]'
				],
			],
			'removed integers less than 1' => [
				[
					'templatedata-favorite-templates' => '[7,15,24]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24,-1,8]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24,8]'
				],
			],
			'combination' => [
				[
					'templatedata-favorite-templates' => '[7]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24,24,17,"fox",-1,8]'
				],
				[
					'templatedata-favorite-templates' => '[7,15,24,17,8]'
				],
			],
		];
	}

}
