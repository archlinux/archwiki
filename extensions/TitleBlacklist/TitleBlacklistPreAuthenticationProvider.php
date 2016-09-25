<?php

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;

class TitleBlacklistPreAuthenticationProvider extends AbstractPreAuthenticationProvider {
	protected $blockAutoAccountCreation;

	public function __construct( $params = [] ) {
		global $wgTitleBlacklistBlockAutoAccountCreation;

		$params += [
			'blockAutoAccountCreation' => $wgTitleBlacklistBlockAutoAccountCreation
		];

		$this->blockAutoAccountCreation = (bool)$params['blockAutoAccountCreation'];
	}

	public function getAuthenticationRequests( $action, array $options ) {
		$needOverrideOption = false;
		switch ( $action ) {
			case AuthManager::ACTION_CREATE:
				$user = User::newFromName( $options['username'] ) ?: new User();
				$needOverrideOption = TitleBlacklist::userCanOverride( $user, 'new-account' );
				break;
		}

		return $needOverrideOption ? [ new TitleBlacklistAuthenticationRequest() ] : [];
	}

	public function testForAccountCreation( $user, $creator, array $reqs ) {
		/** @var TitleBlacklistAuthenticationRequest $req */
		$req = AuthenticationRequest::getRequestByClass( $reqs,
			TitleBlacklistAuthenticationRequest::class );
		$override = $req && $req->ignoreTitleBlacklist;
		return TitleBlacklistHooks::testUserName( $user->getName(), $creator, $override, true );
	}

	public function testUserForCreation( $user, $autocreate ) {
		$sv = StatusValue::newGood();
		// only check autocreation here, testForAccountCreation will catch the rest
		if ( $autocreate && $this->blockAutoAccountCreation ) {
			$sv->merge( TitleBlacklistHooks::testUserName( $user->getName(), $user, false, true ) );
		}
		return $sv;
	}
}
