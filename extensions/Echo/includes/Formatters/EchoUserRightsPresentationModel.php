<?php

namespace MediaWiki\Extension\Notifications\Formatters;

use MediaWiki\Extension\Notifications\DiscussionParser;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;

/**
 * Formatter for 'user-rights' notifications
 */
class EchoUserRightsPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'user-rights';
	}

	private function isAutomaticExpiryEvent(): bool {
		// Automatic expirations are system-generated and don't have an agent.
		return (bool)$this->event->getExtraParam( 'automatic' );
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		// For automatic rights expirations, use a header without an agent.
		if ( $this->isAutomaticExpiryEvent() ) {
			return $this->getHeaderMessageForExpiration();
		}

		[ $formattedName, $genderName ] = $this->getAgentForOutput();
		$viewingUser = $this->getViewingUserForGender();
		$add = array_map(
			[ $this->language, 'embedBidi' ],
			$this->getLocalizedGroupNames( $this->event->getExtraParam( 'add', [] ), $viewingUser )
		);
		$remove = array_map(
			[ $this->language, 'embedBidi' ],
			$this->getLocalizedGroupNames( $this->event->getExtraParam( 'remove', [] ), $viewingUser )
		);
		$expiryChanged = array_map(
			[ $this->language, 'embedBidi' ],
			$this->getLocalizedGroupNames( $this->event->getExtraParam( 'expiry-changed', [] ), $viewingUser )
		);
		if ( $expiryChanged ) {
			$msg = $this->msg( 'notification-header-user-rights-expiry-change' );
			$msg->params( $genderName );
			$msg->params( $this->language->commaList( $expiryChanged ) );
			$msg->params( count( $expiryChanged ) );
			$msg->params( $viewingUser );
			return $msg;
		} elseif ( $add && !$remove ) {
			$msg = $this->msg( 'notification-header-user-rights-add-only' );
			$msg->params( $genderName );
			$msg->params( $this->language->commaList( $add ) );
			$msg->params( count( $add ) );
			$msg->params( $viewingUser );
			return $msg;
		} elseif ( !$add && $remove ) {
			$msg = $this->msg( 'notification-header-user-rights-remove-only' );
			$msg->params( $genderName );
			$msg->params( $this->language->commaList( $remove ) );
			$msg->params( count( $remove ) );
			$msg->params( $viewingUser );
			return $msg;
		} else {
			$msg = $this->msg( 'notification-header-user-rights-add-and-remove' );
			$msg->params( $genderName );
			$msg->params( $this->language->commaList( $add ) );
			$msg->params( count( $add ) );
			$msg->params( $this->language->commaList( $remove ) );
			$msg->params( count( $remove ) );
			$msg->params( $viewingUser );
			return $msg;
		}
	}

	/**
	 * header message for automatic user rights expiry
	 *
	 * @return Message
	 */
	private function getHeaderMessageForExpiration(): Message {
		$viewingUser = $this->getViewingUserForGender();

		$groupNames = $this->getLocalizedGroupNames(
			$this->event->getExtraParam( 'remove', [] ),
			$viewingUser
		);

		$remove = [];
		foreach ( $groupNames as $name ) {
			$remove[] = $this->language->embedBidi( $name );
		}

		$msg = $this->msg( 'notification-header-user-rights-expiry' );
		$msg->params( $this->language->commaList( $remove ) );
		$msg->params( count( $remove ) );
		$msg->params( $viewingUser );
		return $msg;
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		$reason = $this->event->getExtraParam( 'reason' );
		if ( $reason ) {
			$text = DiscussionParser::getTextSnippet( $reason, $this->language );
			return new RawMessage( "$1", [ $text ] );
		}
		return false;
	}

	/**
	 * @param string[] $names
	 * @param string $genderName
	 * @return string[]
	 */
	private function getLocalizedGroupNames( array $names, string $genderName ) {
		return array_map(
			fn ( $name ) => $this->language->getGroupMemberName( $name, $genderName ),
			array_values( $names )
		);
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		$addedGroups = array_values( $this->event->getExtraParam( 'add', [] ) );
		$removedGroups = array_values( $this->event->getExtraParam( 'remove', [] ) );
		if ( $addedGroups !== [] && $removedGroups === [] ) {
			$fragment = $addedGroups[0];
		} elseif ( $addedGroups === [] && $removedGroups !== [] ) {
			$fragment = $removedGroups[0];
		} else {
			$fragment = '';
		}
		return [
			'url' => SpecialPage::getTitleFor( 'Listgrouprights', false, $fragment )->getFullURL(),
			'label' => $this->msg( 'echo-learn-more' )->text(),
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		if ( $this->isAutomaticExpiryEvent() ) {
			return [ $this->getLogLink() ];
		}

		return [ $this->getAgentLink(), $this->getLogLink() ];
	}

	private function getLogLink(): array {
		$affectedUserPage = User::newFromId( $this->event->getExtraParam( 'user' ) )->getUserPage();
		$query = [
			'type' => 'rights',
			'page' => $affectedUserPage->getPrefixedText(),
		];
		if ( !$this->isAutomaticExpiryEvent() ) {
			$query['user'] = $this->event->getAgent()->getName();
		}
		return [
			'label' => $this->msg( 'echo-log' )->text(),
			'url' => SpecialPage::getTitleFor( 'Log' )->getFullURL( $query ),
			'description' => '',
			'icon' => false,
			'prioritized' => true,
		];
	}

	/** @inheritDoc */
	protected function getSubjectMessageKey() {
		return $this->isAutomaticExpiryEvent()
			? 'notification-user-rights-expiry-email-subject'
			: 'notification-user-rights-email-subject';
	}
}
