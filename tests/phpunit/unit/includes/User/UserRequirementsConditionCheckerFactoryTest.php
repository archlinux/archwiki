<?php

namespace MediaWiki\Tests\User;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\GroupPermissionsLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserRequirementsConditionChecker;
use MediaWiki\User\UserRequirementsConditionCheckerFactory;
use MediaWiki\User\UserRequirementsConditionEvaluatorBase;
use MediaWiki\User\UserRequirementsConditionValidator;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\User\UserRequirementsConditionCheckerFactory
 */
class UserRequirementsConditionCheckerFactoryTest extends MediaWikiUnitTestCase {

	public function testCreatesConditionChecker() {
		$factory = $this->getFactory();
		$userGroupManager = $this->createMock( UserGroupManager::class );

		$checker = $factory->getUserRequirementsConditionChecker( $userGroupManager );
		$this->assertInstanceOf( UserRequirementsConditionChecker::class, $checker );
	}

	public function testCheckersAreCachedPerWiki() {
		$factory = $this->getFactory();
		$userGroupManager = $this->createMock( UserGroupManager::class );

		$checker1 = $factory->getUserRequirementsConditionChecker( $userGroupManager );
		$checker2 = $factory->getUserRequirementsConditionChecker( $userGroupManager );
		$this->assertSame( $checker1, $checker2 );

		$checker3 = $factory->getUserRequirementsConditionChecker( $userGroupManager, 'otherwiki' );
		$this->assertNotSame( $checker1, $checker3 );
	}

	public function testCreatingCustomCheckerUsesDefaultEvaluatorsAsWell() {
		$factory = $this->getFactory();
		$userGroupManager = $this->createMock( UserGroupManager::class );

		$evaluator = $this->createMock( UserRequirementsConditionEvaluatorBase::class );

		$checker = $factory->getCheckerWithCustomConditions( $userGroupManager, [ $evaluator ] );
		$wrappedChecker = TestingAccessWrapper::newFromObject( $checker );
		$this->assertCount( 2, $wrappedChecker->evaluators );
	}

	private function getFactory(): UserRequirementsConditionCheckerFactory {
		$options = new ServiceOptions(
			UserRequirementsConditionCheckerFactory::CONSTRUCTOR_OPTIONS,
			[
				MainConfigNames::AutoConfirmAge => 4,
				MainConfigNames::AutoConfirmCount => 10,
				MainConfigNames::EmailAuthentication => 0,
				MainConfigNames::UserRequirementsPrivateConditions => [],
			]
		);

		return new UserRequirementsConditionCheckerFactory(
			$options,
			$this->createMock( GroupPermissionsLookup::class ),
			$this->createMock( HookContainer::class ),
			$this->createMock( UserEditTracker::class ),
			$this->createMock( UserRegistrationLookup::class ),
			$this->createMock( UserFactory::class ),
			$this->createMock( IContextSource::class ),
			$this->createMock( UserRequirementsConditionValidator::class ),
		);
	}
}
