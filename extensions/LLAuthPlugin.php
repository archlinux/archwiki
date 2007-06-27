<?php

$wgHooks['isValidPassword'][] = 'LLAuthPlugin::isValidPassword';

$wgExtensionCredits['other'][] = array(
    'name' => 'LLAuthPlugin',
    'description' => 'Authentifizierung am LL-Forum',
    'author' => 'Pierre Schmitz',
    'url' => 'http://www.archlinux.de',
);

require_once('includes/AuthPlugin.php');

class LLAuthPlugin extends AuthPlugin {

	public static function isValidPassword($password) {
		$length = strlen($password);
		return ($length >= 6 && $length <= 25);
	}

	private $dbLink = null;

	function __construct() {
		global $wgDBuser, $wgDBpassword;
		$this->dbLink = mysqli_connect('localhost', $wgDBuser, $wgDBpassword, 'current');
	}

	function __destruct() {
		mysqli_close($this->dbLink);
	}

	function getUserData($username) {
		$result = mysqli_query($this->dbLink, 'SELECT id, email, realname FROM users WHERE name = \''.mysqli_escape_string($this->dbLink, $username).'\'');
		$data = mysqli_fetch_assoc($result);
		mysqli_free_result($result);

		return $data;
	}

	function userExists( $username ) {
		$result = mysqli_query($this->dbLink, 'SELECT id FROM users WHERE name = \''.mysqli_escape_string($this->dbLink, $username).'\'');
		$exists = mysqli_num_rows($result) > 0;
		mysqli_free_result($result);

 		return $exists;
	}

	function authenticate( $username, $password ) {
		$result = mysqli_query($this->dbLink, 'SELECT id FROM users WHERE name = \''.mysqli_escape_string($this->dbLink, $username).'\' AND password = \''.mysqli_escape_string($this->dbLink, sha1($password)).'\' ');
		$authenticated = mysqli_num_rows($result) > 0;
		mysqli_free_result($result);

 		return $authenticated;
	}

	function modifyUITemplate( &$template ) {
		$template->set( 'usedomain', false );
		$template->set('link', 'Um Dich hier anzumelden, nutze Deine Konto-Daten aus dem <a href="http://forum.archlinux.de/">archlinux.de-Forum</a>.');
	}

	function setDomain( $domain ) {
		$this->domain = $domain;
	}

	function validDomain( $domain ) {
		return true;
	}

	function updateUser( &$user ) {
		return $this->initUser($user);
	}

	function autoCreate() {
		return true;
	}

	function allowPasswordChange() {
		return false;
	}

	function setPassword( $user, $password ) {
		return false;
	}

	function updateExternalDB( $user ) {
		// this way userdata is allways overwritten by external db
		return $this->initUser($user);
	}

	function canCreateAccounts() {
		return false;
	}

	function addUser( $user, $password, $email = '', $realname = '' ) {
		return false;
	}

	function strict() {
		return true;
	}

	function initUser( &$user ) {
		$data = $this->getUserData($user->getName());
		$user->setEmail($data['email']);
		$user->confirmEmail();
		$user->setRealName($data['realname']);
		return true;
	}

	function getCanonicalName( $username ) {
		return $username;
	}
}

?>