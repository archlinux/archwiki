<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\CreateFakeSuggestedInvestigationCases;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Maintenance\CreateFakeSuggestedInvestigationCases
 */
class CreateFakeSuggestedInvestigationCasesTest extends MaintenanceBaseTestCase {
	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return CreateFakeSuggestedInvestigationCases::class;
	}

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( 'CheckUserDeveloperMode', true );
	}

	public function testExecuteWhenCheckUserDeveloperModeIsFalse() {
		$this->overrideConfigValue( 'CheckUserDeveloperMode', false );

		$this->expectOutputRegex( '/CheckUser development mode must be enabled to use this script/' );
		$this->expectCallToFatalError();
		$this->maintenance->execute();
	}

	/** @dataProvider provideExecuteWhenProvidedOptionsAreIntegers */
	public function testExecuteWhenProvidedOptionsAreNotIntegers( $options, $expectedOutputRegex ) {
		foreach ( $options as $name => $value ) {
			$this->maintenance->setOption( $name, $value );
		}

		$this->expectOutputRegex( $expectedOutputRegex );
		$this->expectCallToFatalError();
		$this->maintenance->execute();
	}

	public static function provideExecuteWhenProvidedOptionsAreIntegers(): array {
		return [
			'The --num-cases option is a string' => [
				[ 'num-cases' => 'abc' ], '/Number of cases must be an integer/',
			],
			'The --max-users-per-case option is a string' => [
				[ 'max-users-per-case' => 'abc' ], '/Maximum number of users in any created case must be an integer/',
			],
		];
	}
}
