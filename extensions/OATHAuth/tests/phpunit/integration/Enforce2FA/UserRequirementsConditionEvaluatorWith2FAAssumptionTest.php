<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Enforce2FA;

use MediaWiki\Extension\OATHAuth\Enforce2FA\UserRequirementsConditionEvaluatorWith2FAAssumption;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Enforce2FA\UserRequirementsConditionEvaluatorWith2FAAssumption
 */
class UserRequirementsConditionEvaluatorWith2FAAssumptionTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideCheckConditionWith2FAAssumption */
	public function testCheckConditionWith2FAAssumption(
		array $condition,
		bool $has2FA,
		?bool $assumed2FAState,
		bool $expectedResult
	): void {
		$services = $this->getServiceContainer();

		$userRepository = $this->createMock( OATHUserRepository::class );
		$userRepository->method( 'findByUser' )
			->willReturn( $this->createMock( OATHUser::class ) );
		$userRepository->method( 'userHas2FAEnabled' )
			->willReturn( $has2FA );
		$this->setService( 'OATHAuth.UserRepository', $userRepository );

		$evaluator = new UserRequirementsConditionEvaluatorWith2FAAssumption();
		$checker = $services->getUserRequirementsConditionCheckerFactory()
			->getCheckerWithCustomConditions(
				$services->getUserGroupManager(),
				[ $evaluator ]
			);
		$evaluator->setAssumed2FAState( $assumed2FAState );

		$user = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$result = $checker->recursivelyCheckCondition( $condition, $user );
		$this->assertSame( $expectedResult, $result );
	}

	public static function provideCheckConditionWith2FAAssumption(): array {
		return [
			'No assumption and user has 2FA -> condition should pass' => [
				'condition' => [ APCOND_OATH_HAS2FA ],
				'has2FA' => true,
				'assumed2FAState' => null,
				'expectedResult' => true,
			],
			'No assumption and user does not have 2FA -> condition should fail' => [
				'condition' => [ APCOND_OATH_HAS2FA ],
				'has2FA' => false,
				'assumed2FAState' => null,
				'expectedResult' => false,
			],
			'Assumed that user has 2FA and user actually has 2FA -> condition should pass' => [
				'condition' => [ APCOND_OATH_HAS2FA ],
				'has2FA' => true,
				'assumed2FAState' => true,
				'expectedResult' => true,
			],
			'Assumed that user has 2FA but user does not have 2FA -> condition should pass due to assumption' => [
				'condition' => [ APCOND_OATH_HAS2FA ],
				'has2FA' => false,
				'assumed2FAState' => true,
				'expectedResult' => true,
			],
			'Assumed that user does not have 2FA and actually does not have 2FA -> condition should fail' => [
				'condition' => [ APCOND_OATH_HAS2FA ],
				'has2FA' => false,
				'assumed2FAState' => false,
				'expectedResult' => false,
			],
			'Assumed that user does not have 2FA but actually has 2FA -> condition should fail due to assumption' => [
				'condition' => [ APCOND_OATH_HAS2FA ],
				'has2FA' => true,
				'assumed2FAState' => false,
				'expectedResult' => false,
			],
			'Assumption should not affect other conditions' => [
				'condition' => [ '|', [ APCOND_EDITCOUNT, 0 ], APCOND_OATH_HAS2FA ],
				'has2FA' => true,
				'assumed2FAState' => false,
				'expectedResult' => true,
			],
		];
	}
}
