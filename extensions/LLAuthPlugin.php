<?php

$wgHooks['isValidPassword'][]	= 'LLAuthPlugin::isValidPassword';

$wgExtensionCredits['other'][] 	= array(
	'name' => 'LLAuthPlugin',
	'description' => 'Authentifizierung am LL-Forum',
	'author' => 'Pierre Schmitz',
	'url' => 'http://www.archlinux.de',
);

require_once('includes/AuthPlugin.php');

class LLAuthPlugin extends AuthPlugin {

	private $dbLink	= null;
	private $data 	= null;

	public static function isValidPassword($password) {
		$length = strlen($password);
		return ($length >= 6 && $length <= 25);
	}

	function __destruct()
		{
		if (!is_null($this->dbLink))
			{
			mysqli_close($this->dbLink);
			}
		}

	private function connect()
		{
		global $wgDBuser, $wgDBpassword;

		if (is_null($this->dbLink))
			{
			$this->dbLink = mysqli_connect('localhost', $wgDBuser, $wgDBpassword, 'current');
			}
		}

	private function getUserData($username) {
		if (is_null($this->data))
			{
			$this->connect();
			$result = mysqli_query($this->dbLink, 'SELECT id, name, email, realname FROM users WHERE name = \''.mysqli_escape_string($this->dbLink, $username).'\'');
			$data = mysqli_fetch_assoc($result);
			mysqli_free_result($result);

			$this->data = $data;
			}

		return $this->data;
	}

	public function userExists( $username ) {
		$this->connect();
		$result = mysqli_query($this->dbLink, 'SELECT id FROM users WHERE name = \''.mysqli_escape_string($this->dbLink, $username).'\'');
		$exists = mysqli_num_rows($result) > 0;
		mysqli_free_result($result);

 		return $exists;
	}

	public function authenticate( $username, $password ) {
		$this->connect();
		$result = mysqli_query($this->dbLink, 'SELECT id FROM users WHERE name = \''.mysqli_escape_string($this->dbLink, $username).'\' AND password = \''.mysqli_escape_string($this->dbLink, sha1($password)).'\' ');
		$authenticated = mysqli_num_rows($result) > 0;
		mysqli_free_result($result);

 		return $authenticated;
	}

	public function modifyUITemplate( &$template ) {
		$template->set( 'usedomain', false );
		$template->set('link', 'Um Dich hier anzumelden, nutze Deine Konto-Daten aus dem <a href="http://forum.archlinux.de/">archlinux.de-Forum</a>.');
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
		$data = $this->getUserData($user->getName());
		$user->setEmail($data['email']);
		$user->confirmEmail();
		$user->setRealName($data['realname']);
		return true;
	}

	public function getCanonicalName( $username ) {
		// fix bug #122
		$data = $this->getUserData($username);
		// needed for update.php
		if (is_null($data))
			{
			return $username;
			}
		else
			{
			// make sure that first char is uppercase
			return strtoupper(substr($data['name'], 0, 1)).substr($data['name'], 1);
			}
	}
}

?>
