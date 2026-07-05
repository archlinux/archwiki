<?php

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Notifications\Manager;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Mail\IEmailer;
use MediaWiki\Mail\MailAddress;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use OutOfRangeException;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat as TS;

class Recover2FAForUser extends FormSpecialPage {

	private int $codesCount;
	private readonly int $codeValidityDays;
	private ?UserIdentity $targetUser = null;

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly OATHAuthModuleRegistry $moduleRegistry,
		private readonly OATHAuthLogger $oathLogger,
		private readonly UserFactory $userFactory,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly LinkRenderer $linkRenderer,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly IEmailer $emailer,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
		// messages used: recover2faforuser (display "name" on Special:SpecialPages)
		parent::__construct( 'Recover2FAForUser' );

		$this->codesCount = $this->getConfig()->get( 'OATHRecoveryCodesCount' );
		$this->codeValidityDays = $this->getConfig()->get( 'OATHAdditionalRecoveryCodesValidityDays' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		// messages used: right-oathauth-recover-for-user, action-oathauth-recover-for-user
		return 'oathauth-recover-for-user';
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/** @inheritDoc */
	protected function getLoginSecurityLevel() {
		return $this->getName();
	}

	/**
	 * Set the page title and add JavaScript RL modules
	 */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );

		$legendMsg = $this->msg( 'oathauth-recover-for-user-legend', $this->codesCount );
		$introMsg = $this->msg( 'oathauth-recover-intro' )
			->params( $this->codesCount )
			->numParams( $this->codesCount )
			->parse();
		$form->setWrapperLegendMsg( $legendMsg );
		$form->setPreHtml( $introMsg );
		$form->getOutput()->setPageTitleMsg( $this->msg( 'oathauth-recover-for-user' ) );
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/** @inheritDoc */
	protected function checkExecutePermissions( User $user ) {
		$this->requireNamedUser();

		parent::checkExecutePermissions( $user );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$this->getOutput()->disallowUserJs();
		parent::execute( $par );
	}

	/** @inheritDoc */
	protected function getFormFields() {
		$user = $this->getUserByName( $this->getRequest()->getText( 'user' ) );
		$showEmailField = false;
		if ( $user ) {
			$userEmail = $this->getUserEmail( $user );

			$showEmailField = $userEmail === null
				&& $this->userRepo->findByUser( $user )?->isTwoFactorAuthEnabled();
		}

		return [
			'user' => [
				'type' => 'user',
				'default' => '',
				'label-message' => 'oathauth-enteruser',
				'name' => 'user',
				'required' => true,
				'excludetemp' => true,
			],
			'reason' => [
				'type' => 'text',
				'default' => '',
				'label-message' => 'oathauth-enterrecoverreason',
				'name' => 'reason',
				'required' => true,
			],
			'email' => [
				'type' => $showEmailField ? 'email' : 'hidden',
				'default' => '',
				'label-message' => 'oathauth-enterrecoveremail',
				'help-message' => 'oathauth-enterrecoveremail-help',
				'name' => 'email',
				'required' => false,
			],
		];
	}

	/** @inheritDoc */
	public function onSubmit( array $formData ): Status {
		$user = $this->getUserByName( $formData['user'] );
		if ( !$user ) {
			return Status::newFatal( 'oathauth-user-not-found' );
		}
		$this->targetUser = $user;

		// This page requires the performer to submit twice if the target user has no email. We count such cases
		// as two attempts for rate limiting. Otherwise, the special page could be gamed into either unlimited
		// 2FA verificator for email-less users or unlimited 2FA recovery for email-less users.
		// We could also use tokens for ensuring that resubmitting the page is counted once, but let's do it only
		// if needed.
		// Given that the page is normally not used frequently, we defer to the system administrator to set
		// appropriate limits to account for that behavior.
		if ( $this->getUser()->pingLimiter( 'recover-2fa' ) ) {
			return Status::newFatal( 'oathauth-throttled' );
		}

		$oathUser = $this->userRepo->findByUser( $user );
		if ( !$oathUser->isTwoFactorAuthEnabled() ) {
			return Status::newFatal( 'oathauth-recover-fail-no-2fa' );
		}

		$userEmail = $this->getUserEmail( $user );
		if ( $userEmail === null ) {
			// Attempt to use the one provided in the form
			$userEmail = $formData['email'];
			if ( !Sanitizer::validateEmail( $userEmail ) ) {
				return Status::newFatal( 'oathauth-recover-fail-email-required' );
			}
		}

		/** @var RecoveryCodes $recoveryCodesModule */
		$recoveryCodesModule = $this->moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );
		'@phan-var RecoveryCodes $recoveryCodesModule';

		$expiryTimestamp = ConvertibleTimestamp::convert(
			TS::MW,
			(int)ConvertibleTimestamp::now( TS::UNIX ) + $this->codeValidityDays * 86400
		);
		$key = $recoveryCodesModule->ensureExistence( $oathUser );
		try {
			$newRecoveryCodes = $key->generateAdditionalRecoveryCodeKeys(
				$this->codesCount,
				[ 'expiry' => $expiryTimestamp ]
			);
		} catch ( OutOfRangeException ) {
			// If there's no room for that many recovery codes, first invalidate all existing temporary codes
			// (which are likely not needed, since user asked for recovery again)
			$key->removeTemporaryCodes();
			$newRecoveryCodes = $key->generateAdditionalRecoveryCodeKeys(
				$this->codesCount,
				[ 'expiry' => $expiryTimestamp ]
			);
			$this->codesCount = count( $newRecoveryCodes );

			// If no codes can be generated, that's a problem with the configuration
			if ( $this->codesCount === 0 ) {
				return Status::newFatal( 'oathauth-recover-fail-max-codes-reached' );
			}
		}
		$this->userRepo->updateKey( $oathUser, $key );

		// Send notification even if recovery codes were not sent via email
		Manager::notifyRecoveryTokensGeneratedForUser( $user, $this->codesCount );
		$this->oathLogger->logOATHRecovery( $this->getUser(), $user, $formData['reason'], $this->codesCount );

		$emailStatus = $this->sendEmailWithRecoveryCodes( $userEmail, $newRecoveryCodes, $user, $expiryTimestamp );
		if ( !$emailStatus->isOK() ) {
			return $emailStatus;
		}

		return Status::newGood();
	}

	public function onSuccess() {
		$targetUserName = $this->targetUser->getName();
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable; already proven not-null: line above not fatalling
		$targetUserLink = $this->linkRenderer->makeUserLink( $this->targetUser, $this->getContext() );

		$successMsg = $this->msg( 'oathauth-recoveredoath' )
			->params( $this->codesCount, $targetUserName )
			->rawParams( $targetUserLink );
		$this->getOutput()->addWikiMsg( $successMsg );
		$this->getOutput()->returnToMain();
	}

	/**
	 * @return string|null Non empty string if the user has an email address, null otherwise.
	 *  If $wgEmailAuthentication = true, email confirmation is respected.
	 */
	public function getUserEmail( User $user ): ?string {
		$emailAuth = $this->getConfig()->get( 'EmailAuthentication' );

		// Prefer email from CentralAuth if loaded
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$centralUser = CentralAuthUser::getInstanceByName( $user->getName() );

			$email = $centralUser->getEmail();
			if (
				( !$emailAuth && $email !== '' ) ||
				$centralUser->getEmailAuthenticationTimestamp()
			) {
				return $email;
			}
		}

		$email = $user->getEmail();
		if (
			( !$emailAuth && $email !== '' ) ||
			$user->isEmailConfirmed()
		) {
			return $email;
		}

		return null;
	}

	private function getUserByName( string $username ): ?User {
		$user = $this->userFactory->newFromName( $username );
		// T393253 - Check the username is valid, but don't check if it exists on the local wiki.
		// Instead, check there is a valid central ID.
		if ( !$user || $this->centralIdLookup->centralIdFromName( $username ) === 0 ) {
			return null;
		}
		return $user;
	}

	private function sendEmailWithRecoveryCodes(
		string $emailAddress,
		array $recoveryCodes,
		User $targetUser,
		string $expiryTimestamp
	): Status {
		// PasswordSender is used across MediaWiki for different purposes, that's why we use it here as well.
		$passwordSender = $this->getConfig()->get( MainConfigNames::PasswordSender );
		$sender = new MailAddress(
			$passwordSender,
			$this->msg( 'emailsender' )->inContentLanguage()->text()
		);
		$to = new MailAddress( $emailAddress );

		$recoveryCodesText = implode( "\n", $recoveryCodes );

		if ( $targetUser->isRegistered() ) {
			$userLanguage = $this->userOptionsLookup->getOption( $targetUser, 'language' );
		} else {
			$userLanguage = $this->getContext()->getLanguage()->getCode();
		}

		$siteAdminContact = trim(
			$this->msg( 'oathauth-recover-email-text-site-admin-contact' )
				->inContentLanguage()
				->text()
		);
		if ( $siteAdminContact ) {
			$siteAdminContact = '<' . $siteAdminContact . '>';
			$contactAdminLine = $this->msg( 'oathauth-recover-email-text-please-contact-with-address' )
				->inLanguage( $userLanguage )
				->params( $targetUser->getName(), $siteAdminContact )
				->text();
		} else {
			$contactAdminLine = $this->msg( 'oathauth-recover-email-text-please-contact' )
				->inLanguage( $userLanguage )
				->params( $targetUser->getName() )
				->text();
		}

		$now = ConvertibleTimestamp::now();

		$subject = $this->msg( 'oathauth-recover-email-title' )
			->inLanguage( $userLanguage )
			->params( count( $recoveryCodes ) )
			->text();
		$body = $this->msg( 'oathauth-recover-email-text' )
			->inLanguage( $userLanguage )
			->params( $targetUser->getName(), count( $recoveryCodes ), $recoveryCodesText, $contactAdminLine )
			->dateTimeParams( $now )
			->dateParams( $now )
			->timeParams( $now )
			->dateParams( $expiryTimestamp )
			->text();

		return Status::wrap( $this->emailer->send( $to, $sender, $subject, $body ) );
	}
}
