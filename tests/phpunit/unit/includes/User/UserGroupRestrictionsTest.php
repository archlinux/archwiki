<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Tests\User;

use MediaWiki\User\UserGroupRestrictions;
use MediaWiki\User\UserRequirementsConditionValidator;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\User\UserGroupRestrictions
 */
class UserGroupRestrictionsTest extends MediaWikiUnitTestCase {

	public function testFromFullSpec() {
		$spec = [
			'memberConditions' => [ 'cond1', 'cond2' ],
			'updaterConditions' => [ 'cond3' ],
			'canBeIgnored' => true,
		];
		$restrictions = new UserGroupRestrictions( $spec );
		$this->assertSame( [ 'cond1', 'cond2' ], $restrictions->getMemberConditions() );
		$this->assertSame( [ 'cond3' ], $restrictions->getUpdaterConditions() );
		$this->assertTrue( $restrictions->canBeIgnored() );
		$this->assertFalse( $restrictions->continuouslyEnforced() );
		$this->assertTrue( $restrictions->hasAnyConditions() );
		$this->assertFalse( $restrictions->allowsAutomaticDemotion() );
	}

	public function testFromFullSpecWithScalarConditions() {
		$spec = [
			'memberConditions' => 'cond1',
			'updaterConditions' => 'cond3',
			'canBeIgnored' => true,
		];
		$restrictions = new UserGroupRestrictions( $spec );
		$this->assertSame( [ 'cond1' ], $restrictions->getMemberConditions() );
		$this->assertSame( [ 'cond3' ], $restrictions->getUpdaterConditions() );
		$this->assertTrue( $restrictions->canBeIgnored() );
		$this->assertFalse( $restrictions->continuouslyEnforced() );
		$this->assertTrue( $restrictions->hasAnyConditions() );
	}

	public function testFromEmptySpec() {
		$restrictions = new UserGroupRestrictions( [] );
		$this->assertSame( [], $restrictions->getMemberConditions() );
		$this->assertSame( [], $restrictions->getUpdaterConditions() );
		$this->assertFalse( $restrictions->canBeIgnored() );
		$this->assertFalse( $restrictions->continuouslyEnforced() );
		$this->assertFalse( $restrictions->hasAnyConditions() );
	}

	public function testContinuouslyEnforced() {
		$spec = [
			'memberConditions' => [ 'cond1' ],
		];
		$restrictions = new UserGroupRestrictions( $spec );
		$this->assertTrue( $restrictions->continuouslyEnforced() );
		$this->assertFalse( $restrictions->allowsAutomaticDemotion() );
	}

	public function testAllowsAutomaticDemotion() {
		$spec = [
			'memberConditions' => [ 'cond1' ],
			'demote' => true,
		];
		$restrictions = new UserGroupRestrictions( $spec );
		$this->assertTrue( $restrictions->continuouslyEnforced() );
		$this->assertTrue( $restrictions->allowsAutomaticDemotion() );
	}

	public function testAllowsAutomaticDemotion_ignorable() {
		$spec = [
			'memberConditions' => [ 'cond1' ],
			'canBeIgnored' => true,
			'demote' => true,
		];
		$restrictions = new UserGroupRestrictions( $spec );
		$this->assertFalse( $restrictions->continuouslyEnforced() );
		$this->assertFalse( $restrictions->allowsAutomaticDemotion() );
	}

	/** @dataProvider provideValidation_everythingInvalid */
	public function testValidation_everythingInvalid( array $spec ) {
		$validator = $this->createMock( UserRequirementsConditionValidator::class );
		$validator->method( 'isValid' )
			->willReturn( false );

		$restrictions = UserGroupRestrictions::newFromSpecValidated( $spec, $validator );
		$this->assertSame( [], $restrictions->getMemberConditions() );
		$this->assertSame( [], $restrictions->getUpdaterConditions() );
	}

	public static function provideValidation_everythingInvalid(): iterable {
		yield 'Both member and updater conditions set' => [
			'spec' => [
				'memberConditions' => 'cond1',
				'updaterConditions' => 'cond2',
			],
		];
		yield 'Only member conditions set' => [
			'spec' => [
				'memberConditions' => 'cond1',
			],
		];
		yield 'Only updater conditions set' => [
			'spec' => [
				'updaterConditions' => 'cond2',
			],
		];
	}

	public function testValidation_partiallyInvalid() {
		$validator = $this->createMock( UserRequirementsConditionValidator::class );
		$validator->expects( $this->exactly( 2 ) )
			->method( 'isValid' )
			->willReturnCallback( static fn ( $cond ) => $cond === 'cond1' );
		$spec = [
			'memberConditions' => 'cond1',
			'updaterConditions' => 'cond2',
		];

		$restrictions = UserGroupRestrictions::newFromSpecValidated( $spec, $validator );
		$this->assertSame( [ 'cond1' ], $restrictions->getMemberConditions() );
		$this->assertSame( [], $restrictions->getUpdaterConditions() );
	}
}
