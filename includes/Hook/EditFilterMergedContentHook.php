<?php

namespace MediaWiki\Hook;

use Content;
use IContextSource;
use Status;
use User;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "EditFilterMergedContent" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface EditFilterMergedContentHook {
	/**
	 * Use this hook for a post-section-merge edit filter. This may be triggered by
	 * the EditPage or any other facility that modifies page content. Use the return value
	 * to indicate whether the edit should be allowed, and use the $status object to provide
	 * a reason for disallowing it. $status->apiHookResult can be set to an array to be returned
	 * by api.php action=edit. This is used to deliver captchas.
	 *
	 * @since 1.35
	 *
	 * @param IContextSource $context
	 * @param Content $content Content of the edit box
	 * @param Status $status Status object to represent errors, etc.
	 * @param string $summary Edit summary for page
	 * @param User $user User whois performing the edit
	 * @param bool $minoredit Whether the edit was marked as minor by the user.
	 * @return bool|void False or no return value with not $status->isOK() to abort the edit
	 *   and show the edit form, true to continue. But because multiple triggers of this hook
	 *   may have different behavior in different version (T273354), you'd better return false
	 *   and set $status->value to EditPage::AS_HOOK_ERROR_EXPECTED or any other customized value.
	 */
	public function onEditFilterMergedContent( IContextSource $context, Content $content, Status $status,
		$summary, User $user, $minoredit
	);
}
