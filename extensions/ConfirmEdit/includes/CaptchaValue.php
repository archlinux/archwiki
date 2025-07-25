<?php

namespace MediaWiki\Extension\ConfirmEdit;

/**
 * Simple value object for storing a captcha question + answer.
 */
class CaptchaValue {
	/**
	 * ID that is used to store the captcha in cache.
	 */
	protected string $id;

	/**
	 * Answer to the captcha.
	 */
	protected string $solution;

	/**
	 * @var mixed
	 */
	protected $data;

}
