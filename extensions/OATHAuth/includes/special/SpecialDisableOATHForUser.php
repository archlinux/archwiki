<?php

class SpecialDisableOATHForUser extends FormSpecialPage {
	/** @var OATHUserRepository */
	private $OATHRepository;

	public function __construct() {
		parent::__construct( 'DisableOATHForUser', 'oathauth-disable-for-user' );

		$this->OATHRepository = OATHAuthHooks::getOATHUserRepository();
	}

	public function doesWrites() {
		return true;
	}

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
		$form->setWrapperLegend( $this->msg( 'oathauth-disable-header' ) );
		$form->setPreText( $this->msg( 'oathauth-disable-intro' ) );
		$form->getOutput()->setPageTitle( $this->msg( 'oathauth-disable-for-user' ) );
	}

	/**
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @return bool
	 */
	public function requiresUnblock() {
		return false;
	}

	/**
	 * Require users to be logged in
	 *
	 * @param User $user
	 */
	protected function checkExecutePermissions( User $user ) {
		parent::checkExecutePermissions( $user );

		$this->requireLogin();
	}

	/**
	 * @return array[]
	 */
	protected function getFormFields() {
		return [
			'user' => [
				'type' => 'user',
				'default' => '',
				'label-message' => 'oathauth-enteruser',
				'name' => 'user'
			]
		];
	}

	/**
	 * @param array $formData
	 *
	 * @return array|bool
	 */
	public function onSubmit( array $formData ) {
		$user = User::newFromName( $formData['user'] );
		if ( $user && $user->getId() === 0 ) {
			return [ 'oathauth-user-not-found' ];
		}
		$oathUser = $this->OATHRepository->findByUser( $user );

		if ( $oathUser->getKey() === null ) {
			return [ 'oathauth-user-not-does-not-have-oath-enabled' ];
		}

		if ( $this->getUser()->pingLimiter( 'disableoath', 0 ) ) {
			// Arbitrary duration given here
			return [ 'oathauth-throttled', Message::durationParam( 60 ) ];
		}

		$oathUser->setKey( null );
		$this->OATHRepository->remove( $oathUser, $this->getRequest()->getIP() );

		\MediaWiki\Logger\LoggerFactory::getInstance( 'authentication' )->info(
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
