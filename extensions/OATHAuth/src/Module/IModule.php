<?php

namespace MediaWiki\Extension\OATHAuth\Module;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\HTMLForm\OATHAuthOOUIHTMLForm;
use MediaWiki\Extension\OATHAuth\Key\AuthKey;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Message\Message;

interface IModule {
	/**
	 * Name of the module, as declared in the OATHAuth.Modules extension.json attribute.
	 */
	public function getName(): string;

	public function getDisplayName(): Message;

	public function newKey( array $data ): AuthKey;

	public function getSecondaryAuthProvider(): AbstractSecondaryAuthenticationProvider;

	/**
	 * Is this module currently enabled for the given user?
	 *
	 * Arguably, the module is enabled just by the fact it's set on a user,
	 * but it might not be true for all future modules
	 */
	public function isEnabled( OATHUser $user ): bool;

	/**
	 * Run the validation
	 */
	public function verify( OATHUser $user, array $data ): bool;

	public function getManageForm(
		string $action,
		OATHUser $user,
		OATHUserRepository $repo,
		IContextSource $context,
		OATHAuthModuleRegistry $registry,
	): ?OATHAuthOOUIHTMLForm;

	/**
	 * Return Message object for the short text to be displayed as the description
	 */
	public function getDescriptionMessage(): Message;

	/**
	 * Module-specific text that will be shown when the user is disabling
	 * this module to warn of data-loss.
	 *
	 * This will be shown alongside the generic warning message.
	 *
	 * @return ?Message null if no additional text is needed
	 */
	public function getDisableWarningMessage(): ?Message;

	/**
	 * Module-specific text for the label of the button to add a new key in this module.
	 *
	 * @return ?Message Message object, or null if an add key button should not be shown
	 */
	public function getAddKeyMessage(): ?Message;

	/**
	 * Return Message object for button text to be displayed on the login page
	 */
	public function getLoginSwitchButtonMessage(): Message;

	/**
	 * ext:OATHAuth makes a lot of assumptions in different portions of its
	 * code about standard modules - things like totp and webauthn. Certain
	 * basic actions (enable, disable) and user workflows fit neatly into this
	 * paradigm.  But there will exist modules (such as recovery codes) which
	 * will need to accommodate display logic, workflows and use cases.  This
	 * method provides a conditional divide for these special modules.
	 */
	public function isSpecial(): bool;
}
