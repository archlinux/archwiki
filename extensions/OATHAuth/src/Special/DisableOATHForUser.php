<?php

namespace MediaWiki\Extension\OATHAuth\Special;

use MediaWiki\CheckUser\Hooks as CheckUserHooks;
use MediaWiki\Config\ConfigException;
use MediaWiki\Exception\MWException;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Message\Message;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;

class DisableOATHForUser extends FormSpecialPage {

	public function __construct(
		private readonly OATHUserRepository $userRepo,
		private readonly UserFactory $userFactory,
		private readonly CentralIdLookup $centralIdLookup,
	) {
		// messages used: disableoathforuser (display "name" on Special:SpecialPages),
		// right-oathauth-disable-for-user, action-oathauth-disable-for-user
		parent::__construct( 'DisableOATHForUser', 'oathauth-disable-for-user' );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/**
	 * @return string
	 */
	protected function getLoginSecurityLevel() {
		return $this->getName();
	}

	/**
	 * Set the page title and add JavaScript RL modules
	 *
	 * @param HTMLForm $form
	 */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );
		$form->setWrapperLegendMsg( 'oathauth-disable-for-user' );
		$form->setPreHtml( $this->msg( 'oathauth-disable-intro' )->parse() );
		$form->getOutput()->setPageTitleMsg( $this->msg( 'oathauth-disable-for-user' ) );
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/** @inheritDoc */
	public function requiresUnblock() {
		return false;
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
				'label-message' => 'oathauth-enterdisablereason',
				'name' => 'reason',
				'required' => true,
			],
		];
	}

	/**
	 * @param array $formData
	 * @return array|bool
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function onSubmit( array $formData ) {
		$user = $this->userFactory->newFromName( $formData['user'] );
		// T393253 - Check the username is valid, but don't check if it exists on the local wiki.
		// Instead, check there is a valid central ID.
		if ( !$user || $this->centralIdLookup->centralIdFromName( $formData['user'] ) === 0 ) {
			return [ 'oathauth-user-not-found' ];
		}

		$oathUser = $this->userRepo->findByUser( $user );

		if ( !$oathUser->isTwoFactorAuthEnabled() ) {
			return [ 'oathauth-user-not-does-not-have-oath-enabled' ];
		}

		if ( $this->getUser()->pingLimiter( 'disableoath', 0 ) ) {
			// Arbitrary duration given here
			return [ 'oathauth-throttled', Message::durationParam( 60 ) ];
		}

		$this->userRepo->removeAll( $oathUser, $this->getRequest()->getIP(), false );

		// messages used: logentry-oath-disable-other, log-action-oath-disable-other
		$logEntry = new ManualLogEntry( 'oath', 'disable-other' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $user->getUserPage() );
		$logEntry->setComment( $formData['reason'] );
		$logEntry->insert();

		if ( ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			CheckUserHooks::updateCheckUserData( $logEntry->getRecentChange() );
		}

		LoggerFactory::getInstance( 'authentication' )->info(
			'OATHAuth disabled for {usertarget} by {user} from {clientip}', [
				'user' => $this->getUser()->getName(),
				'usertarget' => $formData['user'],
				'clientip' => $this->getRequest()->getIP(),
			]
		);

		return true;
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'oathauth-disabledoath' );
		$this->getOutput()->returnToMain();
	}

}
