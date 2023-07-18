<?php
/**
 * Implements Special:ChangeEmail
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

use MediaWiki\Auth\AuthManager;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Title\Title;

/**
 * Let users change their email address.
 *
 * @ingroup SpecialPage
 */
class SpecialChangeEmail extends FormSpecialPage {
	/**
	 * @var Status
	 */
	private $status;

	/**
	 * @param AuthManager $authManager
	 */
	public function __construct( AuthManager $authManager ) {
		parent::__construct( 'ChangeEmail', 'editmyprivateinfo' );

		$this->setAuthManager( $authManager );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function isListed() {
		return $this->getAuthManager()->allowsPropertyChange( 'emailaddress' );
	}

	/**
	 * Main execution point
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$out->disallowUserJs();
		$out->addModules( 'mediawiki.special.changeemail' );
		parent::execute( $par );
	}

	protected function getLoginSecurityLevel() {
		return $this->getName();
	}

	protected function checkExecutePermissions( User $user ) {
		if ( !$this->getAuthManager()->allowsPropertyChange( 'emailaddress' ) ) {
			throw new ErrorPageError( 'changeemail', 'cannotchangeemail' );
		}

		$this->requireNamedUser( 'changeemail-no-info' );

		// This could also let someone check the current email address, so
		// require both permissions.
		if ( !$this->getAuthority()->isAllowed( 'viewmyprivateinfo' ) ) {
			throw new PermissionsError( 'viewmyprivateinfo' );
		}

		parent::checkExecutePermissions( $user );
	}

	protected function getFormFields() {
		$user = $this->getUser();

		return [
			'Name' => [
				'type' => 'info',
				'label-message' => 'username',
				'default' => $user->getName(),
			],
			'OldEmail' => [
				'type' => 'info',
				'label-message' => 'changeemail-oldemail',
				'default' => $user->getEmail() ?: $this->msg( 'changeemail-none' )->text(),
			],
			'NewEmail' => [
				'type' => 'email',
				'label-message' => 'changeemail-newemail',
				'autofocus' => true,
				'maxlength' => 255,
				'help-message' => 'changeemail-newemail-help',
			],
		];
	}

	protected function getDisplayFormat() {
		return 'ooui';
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setId( 'mw-changeemail-form' );
		$form->setTableId( 'mw-changeemail-table' );
		$form->setSubmitTextMsg( 'changeemail-submit' );
		$form->addHiddenFields( $this->getRequest()->getValues( 'returnto', 'returntoquery' ) );

		$form->addHeaderHtml( $this->msg( 'changeemail-header' )->parseAsBlock() );
		$form->setSubmitID( 'change_email_submit' );
	}

	public function onSubmit( array $data ) {
		$this->status = $this->attemptChange( $this->getUser(), $data['NewEmail'] );

		return $this->status;
	}

	public function onSuccess() {
		$request = $this->getRequest();

		$returnTo = $request->getVal( 'returnto' );
		$titleObj = $returnTo !== null ? Title::newFromText( $returnTo ) : null;
		if ( !$titleObj instanceof Title ) {
			$titleObj = Title::newMainPage();
		}
		$query = $request->getVal( 'returntoquery', '' );

		if ( $this->status->value === true ) {
			$this->getOutput()->redirect( $titleObj->getFullUrlForRedirect( $query ) );
		} elseif ( $this->status->value === 'eauth' ) {
			# Notify user that a confirmation email has been sent...
			$out = $this->getOutput();
			$out->addHTML(
				Html::warningBox(
					$out->msg( 'eauthentsent', $this->getUser()->getName() )->parse()
				)
			);
			// just show the link to go back
			$this->getOutput()->addReturnTo( $titleObj, wfCgiToArray( $query ) );
		}
	}

	/**
	 * @param User $user
	 * @param string $newAddr
	 *
	 * @return Status
	 */
	private function attemptChange( User $user, $newAddr ) {
		if ( $newAddr !== '' && !Sanitizer::validateEmail( $newAddr ) ) {
			return Status::newFatal( 'invalidemailaddress' );
		}

		$oldAddr = $user->getEmail();
		if ( $newAddr === $oldAddr ) {
			return Status::newFatal( 'changeemail-nochange' );
		}

		if ( strlen( $newAddr ) > 255 ) {
			return Status::newFatal( 'changeemail-maxlength' );
		}

		// To prevent spam, rate limit adding a new address, but do
		// not rate limit removing an address.
		if ( $newAddr !== '' && $user->pingLimiter( 'changeemail' ) ) {
			return Status::newFatal( 'actionthrottledtext' );
		}

		$userLatest = $user->getInstanceForUpdate();
		$status = $userLatest->setEmailWithConfirmation( $newAddr );
		if ( !$status->isGood() ) {
			return $status;
		}

		LoggerFactory::getInstance( 'authentication' )->info(
			'Changing email address for {user} from {oldemail} to {newemail}', [
				'user' => $userLatest->getName(),
				'oldemail' => $oldAddr,
				'newemail' => $newAddr,
			]
		);

		$this->getHookRunner()->onPrefsEmailAudit( $userLatest, $oldAddr, $newAddr );

		$userLatest->saveSettings();

		return $status;
	}

	public function requiresUnblock() {
		return false;
	}

	protected function getGroupName() {
		return 'users';
	}
}
