<?php

namespace MediaWiki\Extension\ConfirmEdit;

/**
 * A class with constants of the CAPTCHA triggers built-in in ConfirmEdit. Other extensions may
 * add more possible triggers, which are not included in this class.
 *
 * @stable to access - Constants defined here may be used in places not visible in codesearch
 */
abstract class CaptchaTriggers {
	public const EDIT = 'edit';
	public const CREATE = 'create';
	public const SENDEMAIL = 'sendemail';
	public const ADD_URL = 'addurl';
	public const CREATE_ACCOUNT = 'createaccount';
	public const LOGIN_ATTEMPT = 'loginattempt';
	public const BAD_LOGIN = 'badlogin';
	public const BAD_LOGIN_PER_USER = 'badloginperuser';

	public const CAPTCHA_TRIGGERS = [
		self::EDIT,
		self::CREATE,
		self::SENDEMAIL,
		self::ADD_URL,
		self::CREATE_ACCOUNT,
		self::LOGIN_ATTEMPT,
		self::BAD_LOGIN,
		self::BAD_LOGIN_PER_USER,
	];
	public const EXT_REG_ATTRIBUTE_NAME = 'CaptchaTriggers';
}
