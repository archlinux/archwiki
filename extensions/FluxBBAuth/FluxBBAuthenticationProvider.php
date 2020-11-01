<?php

namespace MediaWiki\Extensions\FluxBBAuth;

use MediaWiki\Auth\AbstractPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\MediaWikiServices;
use StatusValue;
use User;

class FluxBBAuthenticationProvider extends AbstractPasswordPrimaryAuthenticationProvider
{
    /**
     * @param string $password
     * @return bool
     */
    public static function isValidPassword($password)
    {
        return strlen($password) >= 4;
    }

    /**
     * @param User $user
     */
    public static function onUserLoggedIn($user)
    {
        $userData = wfGetDB(DB_REPLICA)->selectRow(
            static::getFluxBBUserTable(),
            ['email', 'realname', 'registered'],
            ['username' => $user->getName()]
        );

        $userUpdated = false;

        if ($user->getEmail() != $userData->email) {
            $userUpdated = true;
            $user->setEmail($userData->email);
            $user->setEmailAuthenticationTimestamp($userData->registered);
        }

        if ($user->getRealName() != $userData->realname) {
            $userUpdated = true;
            $user->setRealName($userData->realname);
        }

        if ($userUpdated) {
            $user->saveSettings();
        }
    }

    /**
     * @return string
     */
    private static function getFluxBBUserTable()
    {
        return MediaWikiServices::getInstance()
                ->getConfigFactory()
                ->makeConfig('FluxBBAuth')
                ->get("FluxBBDatabase") . '.users';
    }

    /**
     * @inheritDoc
     */
    public function beginPrimaryAuthentication(array $reqs)
    {
        /** @var PasswordAuthenticationRequest $req */
        $req = AuthenticationRequest::getRequestByClass($reqs, PasswordAuthenticationRequest::class);
        if (!$req) {
            return AuthenticationResponse::newAbstain();
        }

        if ($req->username === null || $req->password === null) {
            return AuthenticationResponse::newAbstain();
        }

        $username = $this->getCanonicalName($req->username);
        if (!$username) {
            return AuthenticationResponse::newAbstain();
        }

        $row = wfGetDB(DB_REPLICA)->selectRow(
            static::getFluxBBUserTable(),
            'password',
            ['username' => $username]
        );
        if (!$row || $row->password !== sha1($req->password)) {
            return $this->failResponse($req);
        }

        return AuthenticationResponse::newPass($username);
    }

    /**
     * @param string $username
     * @return bool|string
     */
    private function getCanonicalName($username)
    {
        $userData = wfGetDB(DB_REPLICA)->selectRow(
            static::getFluxBBUserTable(),
            'username',
            ['username' => $username]
        );

        if (!$userData) {
            return false;
        }

        return strtoupper(substr($userData->username, 0, 1)) . substr($userData->username, 1);
    }

    /**
     * @inheritDoc
     */
    public function testUserExists($username, $flags = User::READ_NORMAL)
    {
        $username = User::getCanonicalName($username, 'usable');
        if ($username === false) {
            return false;
        }

        list($db, $options) = \DBAccessObjectUtils::getDBOptions($flags);
        return (bool)wfGetDB($db)->selectField(
            static::getFluxBBUserTable(),
            'id',
            ['username' => $username],
            __METHOD__,
            $options
        );
    }

    /**
     * @inheritDoc
     */
    public function providerAllowsAuthenticationDataChange(AuthenticationRequest $req, $checkData = true)
    {
        return StatusValue::newFatal('authentication data cannot be changed');
    }

    /**
     * @inheritDoc
     */
    public function providerChangeAuthenticationData(AuthenticationRequest $req)
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not implemented.');
    }

    /**
     * @inheritDoc
     */
    public function accountCreationType()
    {
        return self::TYPE_CREATE;
    }

    /**
     * @inheritDoc
     */
    public function beginPrimaryAccountCreation($user, $creator, array $reqs)
    {
        return AuthenticationResponse::newAbstain();
    }

    /**
     * @inheritDoc
     */
    public function providerAllowsPropertyChange($property)
    {
        return false;
    }
}
