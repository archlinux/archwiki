<?php

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;

class VerifyOATHForUser extends FormSpecialPage {
	private bool $enabledStatus;
	private string $targetUser;

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly UserFactory $userFactory,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly ExtensionRegistry $extensionRegistry,
	) {
		// messages used: verifyoathforuser (display "name" on Special:SpecialPages)
		parent::__construct( 'VerifyOATHForUser' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		// messages used: right-oathauth-verify-user, action-oathauth-verify-user
		return 'oathauth-verify-user';
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

	/** @inheritDoc */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );
		$form->getOutput()->setPageTitleMsg( $this->msg( 'oathauth-verify-for-user' ) );
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/** @inheritDoc */
	public function requiresUnblock() {
		return true;
	}

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
				'label-message' => 'oathauth-enterverifyreason',
				'name' => 'reason',
				'required' => true,
			],
		];
	}

	/** @inheritDoc */
	public function onSubmit( array $formData ) {
		$this->targetUser = $formData['user'];
		$user = $this->userFactory->newFromName( $this->targetUser );
		// T424117, same as T393253 - Check the username is valid, but don't check if
		// it exists on the local wiki. Instead, check there is a valid central ID.
		if ( !$user || $this->centralIdLookup->centralIdFromName( $formData['user'] ) === 0 ) {
			return [ 'oathauth-user-not-found' ];
		}

		if ( $this->getUser()->pingLimiter( 'verify-2fa' ) ) {
			return Status::newFatal( 'oathauth-throttled' );
		}

		$oathUser = $this->userRepo->findByUser( $user );

		$this->enabledStatus = $oathUser->isTwoFactorAuthEnabled();

		// messages used: logentry-oath-verify, log-action-oath-verify
		$logEntry = new ManualLogEntry( 'oath', 'verify' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $user->getUserPage() );
		$logEntry->setComment( $formData['reason'] );
		$logId = $logEntry->insert();

		if ( $this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			/** @var CheckUserInsert $checkUserInsert */
			$checkUserInsert = MediaWikiServices::getInstance()->get( 'CheckUserInsert' );
			$checkUserInsert->updateCheckUserData( $logEntry->getRecentChange( $logId ) );
		}

		LoggerFactory::getInstance( 'authentication' )->info(
			'OATHAuth status checked for {usertarget} by {user} from {clientip}', [
				'user' => $this->getUser()->getName(),
				'usertarget' => $this->targetUser,
				'clientip' => $this->getRequest()->getIP(),
			]
		);

		return true;
	}

	public function onSuccess() {
		$msg = $this->enabledStatus ? 'oathauth-verify-enabled' : 'oathauth-verify-disabled';

		$out = $this->getOutput();
		$out->addBacklinkSubtitle( $this->getPageTitle() );
		$out->addWikiMsg( $msg, $this->targetUser );
	}

}
