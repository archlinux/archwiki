<?php

$wgHooks['isValidPassword'][] = 'LLAuthPlugin::isValidPassword';

$wgExtensionCredits['other'][] = array(
	'name' => 'LLAuthPlugin',
	'version' => '3.2',
	'description' => 'Authentifizierung am LL-Forum',
	'author' => 'Pierre Schmitz',
	'url' => 'https://www.archlinux.de'
);

require_once('includes/AuthPlugin.php');


class LLAuthPlugin extends AuthPlugin {

public static function isValidPassword($password) {
	$length = strlen($password);
	return ($length >= 6 && $length <= 25);
}

private function getUserData($username) {
	$dbr = wfGetDB( DB_SLAVE );

	$result = $dbr->safeQuery('SELECT id, name, email, realname FROM ll.users WHERE name = ?', $username);
	$data = $result->fetchRow();
	$result->free();

	return $data;
}

public function userExists( $username ) {
	$dbr = wfGetDB( DB_SLAVE );

	try {
		$result = $dbr->safeQuery('SELECT id FROM ll.users WHERE name = ?', $username);
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
		$result = $dbr->safeQuery('SELECT id FROM ll.users WHERE name = ? AND password = ?', $username, sha1($password));
		$authenticated = ($result->numRows() > 0 ? true : false);
		$result->free();
	} catch (Exception $e) {
		$authenticated = false;
	}

	return $authenticated;
}

public function modifyUITemplate( &$template ) {
	$template->set( 'usedomain', false );
	$template->set('link', 'Um Dich hier anzumelden, nutze Deine Konto-Daten aus dem <a href="https://forum.archlinux.de/">archlinux.de-Forum</a>.');
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

public function allowPasswordChange() {
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
	return strtoupper(substr($username, 0, 1)).substr($username, 1);
}

}

?>
