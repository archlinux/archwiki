<?php

$wgExtensionCredits['other'][] = array(
	'name' => 'FluxBBAuthPlugin',
	'version' => '1.6',
	'description' => 'Use FluxBB accounts in MediaWiki',
	'author' => 'Pierre Schmitz',
	'url' => 'https://pierre-schmitz.com/'
);

require_once(__DIR__.'/../includes/AuthPlugin.php');

global $FluxBBDatabase;
$FluxBBDatabase = 'fluxbb';

class FluxBBAuthPlugin extends AuthPlugin {

	public static function isValidPassword($password) {
		$length = strlen($password);
		return $length >= 4;
	}

	private function getUserData($username) {
		global $FluxBBDatabase;
		$dbr = wfGetDB( DB_SLAVE );

		return $dbr->selectRow($FluxBBDatabase.'.users', array('username', 'email', 'realname'), array('username' => $username));
	}

	public function userExists( $username ) {
		global $FluxBBDatabase;
		$dbr = wfGetDB( DB_SLAVE );

		try {
			$result = $dbr->select($FluxBBDatabase.'.users', 'id', array('username' => $username));
			$exists = ($result->numRows() > 0 ? true : false);
			$result->free();
		} catch (DBQueryError $e) {
			$exists = false;
		}

		return $exists;
	}

	public function authenticate( $username, $password ) {
		global $FluxBBDatabase;
		$dbr = wfGetDB( DB_SLAVE );

		try {
			$result = $dbr->select($FluxBBDatabase.'.users', 'id', array('username' => $username, 'password' => sha1($password)));
			$authenticated = ($result->numRows() > 0 ? true : false);
			$result->free();
		} catch (DBQueryError $e) {
			$authenticated = false;
		}

		return $authenticated;
	}

	public function modifyUITemplate( &$template, &$type ) {
		$template->set( 'usedomain', false );
	}

	public function updateUser( &$user ) {
		return $this->initUser($user);
	}

	public function autoCreate() {
		return true;
	}

	protected function allowRealNameChange() {
		return false;
	}

	protected function allowEmailChange() {
		return false;
	}

	protected function allowNickChange() {
		return false;
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
		return false;
	}

	public function updateExternalDBGroups( $user, $addgroups, $delgroups = array() ) {
		return false;
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

	public function initUser( &$user, $autocreate = false ) {
		try {
			$data = $this->getUserData($user->getName());
			if (!$data) {
				return false;
			}
			$user->setEmail($data->email);
			$user->confirmEmail();
			$user->setRealName($data->realname);
			$user->saveSettings();
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

	public function getCanonicalName( $username ) {
		try {
			$data = $this->getUserData($username);
			if ($data !== false) {
				return strtoupper(substr($data->username, 0, 1)).substr($data->username, 1);
			}
		} catch (Exception $e) {
		}
		return $username;
	}

}

$wgAuth = new FluxBBAuthPlugin();
$wgHiddenPrefs[] = 'realname';
$wgHooks['isValidPassword'][] = 'FluxBBAuthPlugin::isValidPassword';

?>
