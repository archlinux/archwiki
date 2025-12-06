<?php

namespace MediaWiki\Extension\Notifications\Formatters;

use MediaWiki\SpecialPage\SpecialPage;

/**
 * Presentation model for a notification that asks a user with an unconfirmed
 * email to confirm the email address.
 */
class EchoVerifyEmailReminderPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function canRender() {
		// If the user has confirmed their email address, there is no need to display
		// the notification.
		return !$this->getUser()->isEmailConfirmed();
	}

	/** @inheritDoc */
	public function getIconType() {
		return 'alert';
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		return $this->msg( 'notification-header-verify-email-reminder' )
				->params( $this->getViewingUserForGender() );
	}

	/** @inheritDoc */
	public function getSubjectMessage() {
		return $this->msg( 'notification-subject-email-verify-email-reminder' )
				->params( $this->getViewingUserForGender() );
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		$links = [];
		if ( $this->getDistributionType() !== 'email' ) {
			// On web, we duplicate the primary link, which from a UX perspective is more
			// obvious about the next action to take.
			// For email, this isn't necessary since the email renderer creates a prominent
			// button with the primary link
			$links[] = [
				'icon' => 'lock',
				'prioritized' => true,
				'url' => SpecialPage::getTitleFor( 'Confirmemail' )->getFullURL(),
				'label' => $this->msg( 'notification-verify-email-reminder-link-label' )
					->params( $this->getViewingUserForGender() )
					->text(),
			];
		}
		$links[] = [
			'icon' => 'settings',
			'url' => SpecialPage::getTitleFor(
				'Preferences',
				false,
				'mw-prefsection-personal-email'
			)->getFullURL(),
			'label' => $this->msg( 'notification-link-text-verify-email-reminder' )
				->params( $this->getViewingUserForGender() )
				->text(),
		];
		return $links;
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		$url = SpecialPage::getTitleFor( 'Confirmemail' )->getFullURL();
		if ( $this->getDistributionType() === 'email' ) {
			$user = $this->getUser();
			$expiration = '';
			$token = $user->getConfirmationToken( $expiration );
			$url = $user->getConfirmationTokenUrl( $token );
			$user->saveSettings();
		}
		return [
			'url' => $url,
			'label' => $this->msg( 'notification-verify-email-reminder-link-label' )->text(),
		];
	}
}
