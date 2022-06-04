<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\MediaWikiServices;
use SpecialPage;
use StatusValue;
use User;

class AbuseFilterPreAuthenticationProvider extends AbstractPreAuthenticationProvider {
	/**
	 * @param User $user
	 * @param User $creator
	 * @param AuthenticationRequest[] $reqs
	 * @return StatusValue
	 */
	public function testForAccountCreation( $user, $creator, array $reqs ) {
		return $this->testUser( $user, $creator, false );
	}

	/**
	 * @param User $user
	 * @param bool|string $autocreate
	 * @param array $options
	 * @return StatusValue
	 */
	public function testUserForCreation( $user, $autocreate, array $options = [] ) {
		// if this is not an autocreation, testForAccountCreation already handled it
		if ( $autocreate ) {
			// FIXME Using the constructor directly here a bit hacky but needed for T272244
			return $this->testUser( $user, new User, true );
		}
		return StatusValue::newGood();
	}

	/**
	 * @param User $user The user being created or autocreated
	 * @param User $creator The user who caused $user to be created (can be anonymous)
	 * @param bool $autocreate Is this an autocreation?
	 * @return StatusValue
	 */
	protected function testUser( $user, $creator, $autocreate ) {
		$startTime = microtime( true );
		if ( $user->getName() === wfMessage( 'abusefilter-blocker' )->inContentLanguage()->text() ) {
			return StatusValue::newFatal( 'abusefilter-accountreserved' );
		}

		$title = SpecialPage::getTitleFor( 'Userlogin' );
		$builder = AbuseFilterServices::getVariableGeneratorFactory()->newRunGenerator( $creator, $title );
		$vars = $builder->getAccountCreationVars( $user, $autocreate );

		$runnerFactory = AbuseFilterServices::getFilterRunnerFactory();
		// pass creator in explicitly to prevent recording the current user on autocreation - T135360
		$runner = $runnerFactory->newRunner( $creator, $title, $vars, 'default' );
		$status = $runner->run();

		MediaWikiServices::getInstance()->getStatsdDataFactory()
			->timing( 'timing.createaccountAbuseFilter', microtime( true ) - $startTime );

		return $status->getStatusValue();
	}
}
