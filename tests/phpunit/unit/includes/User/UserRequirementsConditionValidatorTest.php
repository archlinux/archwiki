<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Tests\User;

use MediaWiki\User\UserRequirementsConditionValidator;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * @covers \MediaWiki\User\UserRequirementsConditionValidator
 */
class UserRequirementsConditionValidatorTest extends MediaWikiUnitTestCase {

	private function createValidator( int $expectedWarnings ): UserRequirementsConditionValidator {
		$loggerMock = $this->createMock( LoggerInterface::class );
		$loggerMock->expects( $this->exactly( $expectedWarnings ) )
			->method( 'warning' );
		return new UserRequirementsConditionValidator( $loggerMock );
	}

	/** @dataProvider provideValidate */
	public function testValidate( mixed $value, bool $isValid ): void {
		$validator = $this->createValidator( $isValid ? 0 : 1 );
		$this->assertEquals( $isValid, $validator->isValid( $value ) );
	}

	public static function provideValidate(): iterable {
		yield 'Empty array (as root) is valid condition' => [
			'value' => [],
			'isValid' => true,
		];
		yield 'A single condition (int) is valid condition' => [
			'value' => 1,
			'isValid' => true,
		];
		yield 'A single condition (string) is valid condition' => [
			'value' => 'condition',
			'isValid' => true,
		];
		yield 'A single condition (int) with arguments is valid condition' => [
			'value' => [ 1, 'arg1', 2, false, [] ],
			'isValid' => true,
		];
		yield 'A single condition (string) with arguments is valid condition' => [
			'value' => [ 'condition', 'arg1', 2, false, [] ],
			'isValid' => true,
		];
		yield 'A compound condition with atomic conditions is valid' => [
			'value' => [ '&', 'cond1', 2 ],
			'isValid' => true,
		];
		yield 'A compound condition with conditions with arguments is valid' => [
			'value' => [
				'&',
				[ 'cond1', 'arg1', 2 ],
				[ 2, 'arg1', 2 ],
			],
			'isValid' => true,
		];

		yield 'Null is invalid condition' => [
			'value' => null,
			'isValid' => false,
		];
		yield 'False is invalid condition' => [
			'value' => [ false ],
			'isValid' => false,
		];
		yield 'Non-string and non-int inside complex condition are invalid' => [
			'value' => [ '|', false, null, new stdClass(), 3.14 ],
			'isValid' => false,
		];
		yield 'Empty array in complex condition is invalid' => [
			'value' => [ '|', 'cond1', [], 3 ],
			'isValid' => false,
		];
		yield 'Empty array cannot be the condition type in declaration with arguments' => [
			'value' => [ [], 'arg1', 'arg2' ],
			'isValid' => false,
		];
		yield 'AND cannot have zero arguments' => [
			'value' => [ '&' ],
			'isValid' => false,
		];
		yield 'XOR cannot have one argument' => [
			'value' => [ '^', 'cond1' ],
			'isValid' => false,
		];
		yield 'XOR cannot have three arguments' => [
			'value' => [ '^', 'cond1', 'cond2', 'cond3' ],
			'isValid' => false,
		];
	}
}
