<?php

use MediaWiki\Auth\AbstractPreAuthenticationProvider;

class SpamBlacklistPreAuthenticationProvider extends AbstractPreAuthenticationProvider {
	public function testForAccountCreation( $user, $creator, array $reqs ) {
		/** @var $blacklist EmailBlacklist */
		$blacklist = BaseBlacklist::getInstance( 'email' );
		if ( $blacklist->checkUser( $user ) ) {
			return StatusValue::newGood();
		}

		return StatusValue::newFatal( 'spam-blacklisted-email-signup' );
	}
}
