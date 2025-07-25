<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Widgets;

use MediaWiki\CheckUser\CheckUser\Widgets\CIDRCalculatorResultBox;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\CheckUser\Widgets\CIDRCalculatorResultBox
 */
class CIDRCalculatorResultBoxTest extends MediaWikiUnitTestCase {

	/** @dataProvider provideIsAlwaysDisabled */
	public function testIsAlwaysDisabled( $config ) {
		$resultBox = TestingAccessWrapper::newFromObject( new CIDRCalculatorResultBox( $config ) );
		$this->assertSame(
			'disabled',
			$resultBox->input->getAttribute( 'disabled' ),
			'The input should always have the disabled attribute set.'
		);
	}

	public static function provideIsAlwaysDisabled() {
		return [
			'Disabled is not set in the caller\'s config' => [
				[]
			],
			'Disabled is set in the caller\'s config' => [
				[ 'disabled' => true ]
			]
		];
	}

	public function testSetDisabled() {
		$resultBox = TestingAccessWrapper::newFromObject( new CIDRCalculatorResultBox( [] ) );
		$this->assertSame(
			$resultBox->object,
			$resultBox->setDisabled( false ),
			'setDisabled should return the result box object'
		);
		$this->assertSame(
			'disabled',
			$resultBox->input->getAttribute( 'disabled' ),
			'The input should not have been un-disabled by the setDisabled call.'
		);
	}
}
