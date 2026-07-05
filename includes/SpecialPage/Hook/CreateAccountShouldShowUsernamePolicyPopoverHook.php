<?php

namespace MediaWiki\SpecialPage\Hook;

use MediaWiki\SpecialPage\LoginSignupSpecialPage;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "CreateAccountShouldShowUsernamePolicyPopover" to register handlers.
 *
 * @unstable Experimental: used while the Minerva username policy popover is rolled out (e.g. via
 *   experiments). Afterward this may become default core behaviour, move to another extension, or be removed.
 *
 * @ingroup Hooks
 */
interface CreateAccountShouldShowUsernamePolicyPopoverHook {

	/**
	 * Whether to show the Minerva username policy popover on Special:CreateAccount.
	 * Core sets $show to false for Minerva signup;extensions may set it to true to opt in
	 * (e.g. experiment treatment). When $show stays false, the classic username help
	 * (createacct-username-help, label links) is used instead.
	 *
	 * @unstable
	 * @since 1.46
	 *
	 * @param LoginSignupSpecialPage $specialPage
	 * @param bool &$show
	 * @return bool|void True or no return value to continue, or false to abort the hook run
	 */
	public function onCreateAccountShouldShowUsernamePolicyPopover(
		LoginSignupSpecialPage $specialPage,
		bool &$show
	);
}
