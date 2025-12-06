<?php

namespace MediaWiki\Extension\ConfirmEdit\Hooks;

use MediaWiki\User\User;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "ConfirmEditCanUserSkipCaptcha" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface ConfirmEditCanUserSkipCaptchaHook {
	/**
	 * @param User $user The user that may be presented a captcha
	 * @param bool &$result Whether the user should be presented a captcha
	 */
	public function onConfirmEditCanUserSkipCaptcha(
		User $user,
		bool &$result
	);
}
