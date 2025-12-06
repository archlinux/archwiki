<?php

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\CheckUser\Hooks as CheckUserHooks;
use MediaWiki\Config\ConfigException;
use MediaWiki\Exception\MWException;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;

class VerifyOATHForUser extends FormSpecialPage {
	private bool $enabledStatus;
	private string $targetUser;

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly UserFactory $userFactory,
	) {
		// messages used: verifyoathforuser (display "name" on Special:SpecialPages),
		// right-oathauth-verify-user, action-oathauth-verify-user
		parent::__construct( 'VerifyOATHForUser', 'oathauth-verify-user' );
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

	/**
	 * @throws UserBlockedError
	 * @throws UserNotLoggedIn
	 */
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

	/**
	 * @param array $formData
	 * @return array|true
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function onSubmit( array $formData ) {
		$this->targetUser = $formData['user'];
		$user = $this->userFactory->newFromName( $this->targetUser );
		if ( !$user || $user->getId() === 0 ) {
			return [ 'oathauth-user-not-found' ];
		}
		$oathUser = $this->userRepo->findByUser( $user );

		$this->enabledStatus = $oathUser->isTwoFactorAuthEnabled();

		// messages used: logentry-oath-verify, log-action-oath-verify
		$logEntry = new ManualLogEntry( 'oath', 'verify' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $user->getUserPage() );
		$logEntry->setComment( $formData['reason'] );
		$logEntry->insert();

		if ( ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			CheckUserHooks::updateCheckUserData( $logEntry->getRecentChange() );
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
