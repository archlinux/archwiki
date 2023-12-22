<?php
/*
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

namespace MediaWiki\Specials;

use ErrorPageError;
use HTMLForm;
use MediaWiki\MainConfigNames;
use MediaWiki\Preferences\MultiUsernameFilter;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserOptionsManager;

/**
 * A special page that allows users to modify their notification
 * preferences
 *
 * @ingroup SpecialPage
 */
class SpecialMute extends FormSpecialPage {

	private const PAGE_NAME = 'Mute';

	/** @var UserIdentity|null */
	private $target;

	/** @var int */
	private $targetCentralId;

	private CentralIdLookup $centralIdLookup;
	private UserOptionsManager $userOptionsManager;
	private UserIdentityLookup $userIdentityLookup;
	private UserIdentityUtils $userIdentityUtils;

	/**
	 * @param CentralIdLookup $centralIdLookup
	 * @param UserOptionsManager $userOptionsManager
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserIdentityUtils $userIdentityUtils
	 */
	public function __construct(
		CentralIdLookup $centralIdLookup,
		UserOptionsManager $userOptionsManager,
		UserIdentityLookup $userIdentityLookup,
		UserIdentityUtils $userIdentityUtils
	) {
		parent::__construct( self::PAGE_NAME, '', false );
		$this->centralIdLookup = $centralIdLookup;
		$this->userOptionsManager = $userOptionsManager;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userIdentityUtils = $userIdentityUtils;
	}

	/**
	 * Entry point for special pages
	 *
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$this->addHelpLink(
			'https://meta.wikimedia.org/wiki/Community_health_initiative/User_Mute_features',
			true
		);
		$this->requireNamedUser( 'specialmute-login-required' );
		$this->loadTarget( $par );

		parent::execute( $par );

		$out = $this->getOutput();
		$out->addModules( 'mediawiki.misc-authed-ooui' );
	}

	/**
	 * @inheritDoc
	 */
	public function requiresUnblock() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		$out = $this->getOutput();
		$out->addWikiMsg( 'specialmute-success' );
	}

	/**
	 * @param array $data
	 * @param HTMLForm|null $form
	 * @return bool
	 */
	public function onSubmit( array $data, HTMLForm $form = null ) {
		foreach ( $data as $userOption => $value ) {
			if ( $value ) {
				$this->muteTarget( $userOption );
			} else {
				$this->unmuteTarget( $userOption );
			}
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'specialmute' );
	}

	/**
	 * @return UserIdentity|null
	 */
	private function getTarget(): ?UserIdentity {
		return $this->target;
	}

	/**
	 * Un-mute target
	 *
	 * @param string $userOption up_property key that holds the list of muted users
	 */
	private function unmuteTarget( $userOption ) {
		$muteList = $this->getMuteList( $userOption );

		$key = array_search( $this->targetCentralId, $muteList );
		if ( $key !== false ) {
			unset( $muteList[$key] );
			$muteList = implode( "\n", $muteList );

			$user = $this->getUser();
			$this->userOptionsManager->setOption( $user, $userOption, $muteList );
			$user->saveSettings();
		}
	}

	/**
	 * Mute target
	 * @param string $userOption up_property key that holds the blacklist
	 */
	private function muteTarget( $userOption ) {
		// avoid duplicates just in case
		if ( !$this->isTargetMuted( $userOption ) ) {
			$muteList = $this->getMuteList( $userOption );

			$muteList[] = $this->targetCentralId;
			$muteList = implode( "\n", $muteList );

			$user = $this->getUser();
			$this->userOptionsManager->setOption( $user, $userOption, $muteList );
			$user->saveSettings();
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getForm() {
		$target = $this->getTarget();
		$form = parent::getForm();
		$form->setId( 'mw-specialmute-form' );
		$form->setHeaderHtml( $this->msg( 'specialmute-header', $target ? $target->getName() : '' )->parse() );
		$form->setSubmitTextMsg( 'specialmute-submit' );
		$form->setSubmitID( 'save' );

		return $form;
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		$config = $this->getConfig();
		$fields = [];

		if ( !$config->get( MainConfigNames::EnableUserEmail ) ) {
			throw new ErrorPageError( 'specialmute', 'specialmute-error-email-disabled' );
		}

		if ( !$config->get( MainConfigNames::EnableUserEmailMuteList ) ) {
			throw new ErrorPageError( 'specialmute', 'specialmute-error-mutelist-disabled' );
		}

		if ( !$this->getUser()->getEmailAuthenticationTimestamp() ) {
			throw new ErrorPageError( 'specialmute', 'specialmute-error-no-email-set' );
		}

		$target = $this->getTarget();

		$fields['email-blacklist'] = [
			'type' => 'check',
			'label-message' => [
				'specialmute-label-mute-email',
				$target ? $target->getName() : ''
			],
			'default' => $this->isTargetMuted( 'email-blacklist' ),
		];

		$legacyUser = $target ? User::newFromIdentity( $target ) : null;
		$this->getHookRunner()->onSpecialMuteModifyFormFields( $legacyUser, $this->getUser(), $fields );

		if ( count( $fields ) == 0 ) {
			throw new ErrorPageError( 'specialmute', 'specialmute-error-no-options' );
		}

		return $fields;
	}

	/**
	 * @param string|null $username
	 */
	private function loadTarget( $username ) {
		$target = null;
		if ( $username !== null ) {
			$target = $this->userIdentityLookup->getUserIdentityByName( $username );
		}
		if ( !$target || !$this->userIdentityUtils->isNamed( $target ) ) {
			throw new ErrorPageError( 'specialmute', 'specialmute-error-invalid-user' );
		} else {
			$this->target = $target;
			$this->targetCentralId = $this->centralIdLookup->centralIdFromLocalUser( $target );
		}
	}

	/**
	 * @param string $userOption
	 * @return bool
	 */
	public function isTargetMuted( $userOption ) {
		$muteList = $this->getMuteList( $userOption );
		return in_array( $this->targetCentralId, $muteList, true );
	}

	/**
	 * @param string $userOption
	 * @return array
	 */
	private function getMuteList( $userOption ) {
		$muteList = $this->userOptionsManager->getOption( $this->getUser(), $userOption );
		if ( !$muteList ) {
			return [];
		}

		return MultiUsernameFilter::splitIds( $muteList );
	}
}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( SpecialMute::class, 'SpecialMute' );
