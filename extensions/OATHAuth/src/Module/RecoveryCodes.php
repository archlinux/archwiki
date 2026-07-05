<?php

namespace MediaWiki\Extension\OATHAuth\Module;

use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\Auth\RecoveryCodesSecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\HTMLForm\OATHAuthOOUIHTMLForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesRemoveTemporaryForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesStatusForm;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Notifications\Manager;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use UnexpectedValueException;

class RecoveryCodes implements IModule {
	public const MODULE_NAME = "recoverycodes";

	public const ACTION_REMOVE_TEMPORARY = "recoverycodes-temporary-remove";

	/**
	 * Number of recovery code module instances allowed per user in oathauth_devices
	 */
	public const RECOVERY_CODE_MODULE_COUNT = 1;

	/** Threshold number of recovery codes to trigger notification */
	private const RECOVERY_CODE_LEFT = 2;

	public function __construct( private readonly OATHUserRepository $userRepository ) {
	}

	/** @inheritDoc */
	public function getName(): string {
		return self::MODULE_NAME;
	}

	/** @inheritDoc */
	public function getDisplayName(): Message {
		return wfMessage( 'oathauth-module-recoverycodes-label' );
	}

	/** @inheritDoc */
	public function newKey( array $data ): RecoveryCodeKeys {
		if ( !isset( $data['recoverycodekeys'] ) ) {
			throw new UnexpectedValueException( 'oathauth-invalid-recovery-code-data-format' );
		}
		return RecoveryCodeKeys::newFromArray( $data );
	}

	public function getSecondaryAuthProvider(): RecoveryCodesSecondaryAuthenticationProvider {
		return new RecoveryCodesSecondaryAuthenticationProvider(
			$this,
			$this->userRepository,
			OATHAuthServices::getInstance()->getLogger()
		);
	}

	public function verify( OATHUser $user, array $data ): bool {
		if ( !isset( $data['recoverycode'] ) ) {
			return false;
		}

		$recoveryCodeKeys = $user->getKeysForModule( self::MODULE_NAME );

		if ( $recoveryCodeKeys === [] ) {
			return false;
		}

		/** @var $recoveryCodeKey RecoveryCodeKeys */
		$recoveryCodeKey = $recoveryCodeKeys[0];
		'@phan-var RecoveryCodeKeys $recoveryCodeKey';

		if ( !$recoveryCodeKey->verify( $user, $data ) ) {
			return false;
		}

		// Remove the key that was used
		$recoveryCodeKey->removeRecoveryCode( $user, $data['recoverycode'] );

		if ( count( $recoveryCodeKey->getRecoveryCodes() ) <= self::RECOVERY_CODE_LEFT ) {
			Manager::notifyRecoveryTokensRemaining(
				$user,
				count( $recoveryCodeKey->getRecoveryCodes() ),
				MediaWikiServices::getInstance()->getMainConfig()->get( 'OATHRecoveryCodesCount' )
			);
		}

		// Save the key removal to the database
		$this->userRepository->updateKey( $user, $recoveryCodeKey );

		return true;
	}

	/**
	 * Ensure that a RecoveryCodeKeys key exists for the given user, creating a new one if needed.
	 *
	 * @param OATHUser $user
	 * @param ?array $recoveryCodesData Use this data to create the new RecoveryCodesKey if needed
	 * @return RecoveryCodeKeys Pre-existing or newly created RecoveryCodeKeys key
	 */
	public function ensureExistence( OATHUser $user, ?array $recoveryCodesData = null ): RecoveryCodeKeys {
		$rcKeys = $user->getKeysForModule( self::MODULE_NAME );
		if ( count( $rcKeys ) > self::RECOVERY_CODE_MODULE_COUNT ) {
			throw new UnexpectedValueException( wfMessage( 'oathauth-recoverycodes-too-many-instances' ) );
		}
		$recoveryCodeKey = $rcKeys[ 0 ] ?? null;
		if ( $recoveryCodeKey instanceof RecoveryCodeKeys ) {
			// User already has recovery codes, nothing to do
			return $recoveryCodeKey;
		}

		// Use the provided $recoveryCodesData if there is one, otherwise create an empty key
		// and generate new codes
		$recoveryCodeKey = $this->newKey( $recoveryCodesData ?? [ 'recoverycodekeys' => [] ] );
		if ( $recoveryCodeKey->getRecoveryCodes() === [] ) {
			$recoveryCodeKey->regenerateRecoveryCodeKeys();
		}

		// Save the new key to the database
		$oathRepo = OATHAuthServices::getInstance()->getUserRepository();
		/** @var RecoveryCodeKeys $newKey */
		$newKey = $oathRepo->createKey(
			$user,
			$this,
			$recoveryCodeKey->jsonSerialize(),
			RequestContext::getMain()->getRequest()->getIP()
		);
		'@phan-var RecoveryCodeKeys $newKey';
		return $newKey;
	}

	/**
	 * Is this module currently enabled for the given user?
	 */
	public function isEnabled( OATHUser $user ): bool {
		return $user->getKeysForModule( self::MODULE_NAME ) !== [];
	}

	/** @inheritDoc */
	public function getManageForm(
		string $action,
		OATHUser $user,
		OATHUserRepository $repo,
		IContextSource $context,
		OATHAuthModuleRegistry $registry
	): ?OATHAuthOOUIHTMLForm {
		if ( $action === self::ACTION_REMOVE_TEMPORARY ) {
			return new RecoveryCodesRemoveTemporaryForm( $user, $repo, $this, $context, $registry );
		}
		return new RecoveryCodesStatusForm( $user, $repo, $this, $context, $registry );
	}

	/** @inheritDoc */
	public function getDescriptionMessage(): Message {
		return wfMessage( 'oathauth-recoverycodes-description' );
	}

	/** @inheritDoc */
	public function getDisableWarningMessage(): ?Message {
		return null;
	}

	public function getAddKeyMessage(): ?Message {
		return null;
	}

	public function getLoginSwitchButtonMessage(): Message {
		return wfMessage( 'oathauth-auth-use-recovery-code' );
	}

	/** @inheritDoc */
	public function isSpecial(): bool {
		return true;
	}
}
