<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Status\Status;
use MediaWiki\User\User;

/**
 * Integration with Extension:ConfirmEdit, if loaded.
 */
class ConfirmEditHandler implements EditFilterMergedContentHook {

	/** @inheritDoc */
	public function onEditFilterMergedContent(
		IContextSource $context, Content $content, Status $status, $summary, User $user, $minoredit
	) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ) ) {
			return true;
		}
		$simpleCaptcha = Hooks::getInstance();
		// In WMF production, AbuseFilter is loaded after ConfirmEdit. That means,
		// Extension:ConfirmEdit's EditFilterMergedContent hook has already run, and that hook
		// is responsible for deciding whether to show a CAPTCHA via the SimpleCaptcha::confirmEditMerged
		// method.
		// Here, we look to see if:
		// 1. CaptchaConsequence in AbuseFilter modified the global SimpleCaptcha instance to say that
		//    we should force showing a Captcha
		// 2. that the Captcha hasn't yet been solved
		// 3. ConfirmEdit's EditFilterMergedContent handler has already run (ConfirmEdit was loaded
		//    ahead of AbuseFilter via wfLoadExtension())
		// If all conditions are true, we invoke SimpleCaptcha's ConfirmEditMerged method, which
		// will run in a narrower scope (not invoking ConfirmEdit's onConfirmEditTriggersCaptcha hook,
		// for example), and will just make sure that the status is modified to present a CAPTCHA to
		// the user.
		if ( $simpleCaptcha->shouldForceShowCaptcha() &&
			!$simpleCaptcha->isCaptchaSolved() &&
			$simpleCaptcha->editFilterMergedContentHandlerAlreadyInvoked() ) {
			return $simpleCaptcha->confirmEditMerged(
				$context,
				$content,
				$status,
				$summary,
				$user,
				$minoredit
			);
		}
		return true;
	}

}
