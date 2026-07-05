<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Hook;

class RegistrationCallback {

	public static function onRegistration(): void {
		// Following the pattern in TorBlock, this is a string
		define( 'APCOND_OATH_HAS2FA', 'oath.has_2fa' );
	}
}
