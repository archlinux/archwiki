<?php

use MediaWiki\Extension\AbuseFilter\AbuseFilterPreAuthenticationProvider;

/**
 * This trait can be used to create accounts in integration tests.
 * NOTE: The implementing classes MUST extend MediaWikiIntegrationTestCase
 * @todo This might be moved to MediaWikiIntegrationTestCase
 */
trait AbuseFilterCreateAccountTestTrait {
	/**
	 * @param string $accountName
	 * @param User|null $creator Defaults to the newly created user
	 * @param bool $autocreate
	 * @return StatusValue
	 */
	protected function createAccount(
		string $accountName,
		User $creator = null,
		bool $autocreate = false
	): StatusValue {
		$user = User::newFromName( $accountName );
		// A creatable username must exist to be passed to $logEntry->setPerformer(),
		// so create the account.
		$user->addToDatabase();

		$creator = $creator ?? $user;

		$provider = new AbuseFilterPreAuthenticationProvider();
		$status = $provider->testForAccountCreation( $user, $creator, [] );

		// FIXME This is a bit hacky, but AuthManager doesn't expose any methods for logging
		$subType = $autocreate ? 'autocreate' : 'create2';
		$logEntry = new \ManualLogEntry( 'newusers', $subType );
		$logEntry->setPerformer( $creator );
		$logEntry->setTarget( Title::makeTitle( NS_USER, $accountName ) );
		$logEntry->setComment( 'Fooobarcomment' );
		$logEntry->setParameters( [
			'4::userid' => $user->getId(),
		] );
		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
		return $status;
	}
}
