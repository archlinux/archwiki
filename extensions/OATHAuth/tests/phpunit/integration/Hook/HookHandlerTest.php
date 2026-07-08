<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Hook;

use MediaWiki\Extension\OATHAuth\Hook\HookHandler;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Hook\HookHandler
 */
class HookHandlerTest extends MediaWikiIntegrationTestCase {

	protected function createHookHandler(): HookHandler {
		$services = MediaWikiServices::getInstance();
		return new HookHandler(
			$services->get( 'OATHAuth.UserRepository' ),
			$services->get( 'OATHAuth.ModuleRegistry' ),
			$services->get( 'OATHAuth.Logger' ),
			$services->getPermissionManager(),
			$services->getMainConfig(),
			$services->getUserGroupManager()
		);
	}

	/** @dataProvider provideOnUserRequirementsCondition */
	public function testOnUserRequirementsCondition( string $condition, bool $has2FA, ?bool $expectedResult ) {
		$userIdentity = UserIdentityValue::newRegistered( 1, 'WikiUser' );

		$userRepoMock = $this->createMock( OATHUserRepository::class );
		$userRepoMock->method( 'findByUser' )
			->with( $userIdentity )
			->willReturn( $this->createMock( OATHUser::class ) );
		$userRepoMock->method( 'userHas2FAEnabled' )
			->willReturn( $has2FA );

		$this->setService( 'OATHAuth.UserRepository', $userRepoMock );

		$hookHandler = $this->createHookHandler();
		$result = null;
		$hookHandler->onUserRequirementsCondition(
			$condition,
			[],
			$userIdentity,
			false,
			$result
		);

		$this->assertSame( $expectedResult, $result );
	}

	public static function provideOnUserRequirementsCondition() {
		return [
			'User has 2FA' => [
				'condition' => APCOND_OATH_HAS2FA,
				'has2FA' => true,
				'expectedResult' => true,
			],
			'User does not have 2FA' => [
				'condition' => APCOND_OATH_HAS2FA,
				'has2FA' => false,
				'expectedResult' => false,
			],
			'Handler does not handle other conditions' => [
				'condition' => 'undefined condition',
				'has2FA' => false,
				'expectedResult' => null,
			],
		];
	}

	/** @dataProvider provideOnReadPrivateUserRequirementsCondition */
	public function testOnReadPrivateUserRequirementsCondition( array $conditions, bool $shouldLog ) {
		$performer = UserIdentityValue::newRegistered( 1, 'Admin' );
		$target = UserIdentityValue::newRegistered( 2, 'User' );

		$loggerMock = $this->createMock( OATHAuthLogger::class );
		if ( $shouldLog ) {
			$loggerMock->expects( $this->once() )
				->method( 'logImplicitVerification' )
				->with( $performer, $target );
		} else {
			$loggerMock->expects( $this->never() )
				->method( 'logImplicitVerification' );
		}

		$oathServices = OATHAuthServices::getInstance();
		$mwServices = MediaWikiServices::getInstance();
		$hookHandler = new HookHandler(
			$oathServices->getUserRepository(),
			$oathServices->getModuleRegistry(),
			$loggerMock,
			$mwServices->getPermissionManager(),
			$mwServices->getMainConfig(),
			$mwServices->getUserGroupManager()
		);

		$hookHandler->onReadPrivateUserRequirementsCondition( $performer, $target, $conditions );
	}

	public static function provideOnReadPrivateUserRequirementsCondition(): array {
		return [
			'Only 2FA condition was read' => [
				'conditions' => [ APCOND_OATH_HAS2FA ],
				'shouldLog' => true,
			],
			'2FA and some other condition were read' => [
				'conditions' => [ APCOND_OATH_HAS2FA, 'other condition' ],
				'shouldLog' => true,
			],
			'Only some other condition was read' => [
				'conditions' => [ 'other condition' ],
				'shouldLog' => false,
			],
			'No condition was read' => [
				'conditions' => [],
				'shouldLog' => false,
			]
		];
	}
}
