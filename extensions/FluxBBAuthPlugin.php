<?php

$wgHooks['isValidPassword'][] = 'FluxBBAuthPlugin::isValidPassword';

$wgExtensionCredits['other'][] = array(
	'name' => 'FluxBBAuthPlugin',
	'version' => '1.2',
	'description' => 'Use FluxBB accounts in MediaWiki',
	'author' => 'Pierre Schmitz',
	'url' => 'https://pierre-schmitz.com/'
);

require_once(__DIR__.'/../includes/AuthPlugin.php');


class FluxBBAuthPlugin extends AuthPlugin {

public static function isValidPassword($password) {
	$length = strlen($password);
	return ($length >= 4 && $length <= 25);
}

private function getUserData($username) {
	$dbr = wfGetDB( DB_SLAVE );

	$result = $dbr->safeQuery('SELECT id, username, email, realname FROM fluxbb.users WHERE username = ?', $username);
	$data = $result->fetchRow();
	$result->free();

	return $data;
}

public function userExists( $username ) {
	$dbr = wfGetDB( DB_SLAVE );

	try {
		$result = $dbr->safeQuery('SELECT id FROM fluxbb.users WHERE username = ?', $username);
		$exists = ($result->numRows() > 0 ? true : false);
		$result->free();
	} catch (Exception $e) {
		$exists = false;
	}

	return $exists;
}

public function authenticate( $username, $password ) {
	$dbr = wfGetDB( DB_SLAVE );

	try {
		$result = $dbr->safeQuery('SELECT id FROM fluxbb.users WHERE username = ? AND password = ?', $username, sha1($password));
		$authenticated = ($result->numRows() > 0 ? true : false);
		$result->free();
	} catch (Exception $e) {
		$authenticated = false;
	}

	return $authenticated;
}

public function modifyUITemplate( &$template, &$type ) {
	$template->set( 'usedomain', false );
	$template->set('link', 'Um Dich hier anzumelden, nutze Deine Konto-Daten aus dem <a href="https://bbs.archlinux.de/">archlinux.de-Forum</a>.');
}

public function setDomain( $domain ) {
	$this->domain = $domain;
}

public function validDomain( $domain ) {
	return true;
}

public function updateUser( &$user ) {
	return $this->initUser($user);
}

public function autoCreate() {
	return true;
}

public function allowPropChange( $prop = '' ) {
	if( $prop == 'realname' ) {
		return false;
	} elseif( $prop == 'emailaddress' ) {
		return false;
	} elseif( $prop == 'nickname' ) {
		return false;
	} else {
		return true;
	}
}
public function allowPasswordChange() {
	return false;
}

public function allowSetLocalPassword() {
	return false;
}

public function setPassword( $user, $password ) {
	return false;
}

public function updateExternalDB( $user ) {
	// this way userdata is allways overwritten by external db
	return $this->initUser($user);
}

public function canCreateAccounts() {
	return false;
}

public function addUser( $user, $password, $email = '', $realname = '' ) {
	return false;
}

public function strict() {
	return true;
}

public function strictUserAuth( $username ) {
	return true;
}

public function initUser( &$user, $autocreate=false ) {
	try {
		$data = $this->getUserData($user->getName());
		$user->setEmail($data['email']);
		$user->confirmEmail();
		$user->setRealName($data['realname']);
	} catch (Exception $e) {
		return false;
	}
	return true;
}

public function getCanonicalName( $username ) {
	if ($username != 'MediaWiki default') {
		try {
			$data = $this->getUserData($username);
			return strtoupper(substr($data['username'], 0, 1)).substr($data['username'], 1);
		} catch (Exception $e) {
			return false;
		}
	} else {
		return $username;
	}
}

}

?>
