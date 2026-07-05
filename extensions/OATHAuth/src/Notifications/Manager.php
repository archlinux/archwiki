<?php
/**
 * Copyright (C) 2022 Kunal Mehta <legoktm@debian.org>
 *
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Notifications;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Notification\RecipientSet;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;

/**
 * Manages logic for configuring and sending out notifications with Echo
 */
class Manager {

	/**
	 * Whether Echo is installed and can be used
	 */
	private static function isEnabled(): bool {
		return ExtensionRegistry::getInstance()->isLoaded( 'Echo' );
	}

	/**
	 * Send a notification that 2FA has been disabled
	 *
	 * @param OATHUser $oUser
	 * @param bool $self Whether they disabled it themselves
	 */
	public static function notifyDisabled( OATHUser $oUser, bool $self ): void {
		if ( !self::isEnabled() ) {
			return;
		}
		Event::create( [
			// message used: notification-header-oathauth-disable
			'type' => 'oathauth-disable',
			'title' => SpecialPage::getTitleFor( 'Preferences' ),
			'agent' => $oUser->getUser(),
			'extra' => [
				'self' => $self,
				'activeDevices' => count( $oUser->getNonSpecialKeys() ),
			]
		] );
	}

	/**
	 * Send a notification that 2FA has been enabled
	 */
	public static function notifyEnabled( OATHUser $oUser ): void {
		if ( !self::isEnabled() ) {
			return;
		}
		Event::create( [
			// message used: notification-header-oathauth-enable
			'type' => 'oathauth-enable',
			'title' => SpecialPage::getTitleFor( 'Preferences' ),
			'agent' => $oUser->getUser(),
			'extra' => [
				'activeDevices' => count( $oUser->getNonSpecialKeys() ),
			],
		] );
	}

	/**
	 * Send a notification that the user has $tokenCount recovery tokens left
	 */
	public static function notifyRecoveryTokensRemaining(
		OATHUser $oUser, int $tokenCount, int $generatedCount
	): void {
		if ( !self::isEnabled() ) {
			return;
		}
		Event::create( [
			// message used: notification-header-oathauth-recoverycodes-count
			'type' => 'oathauth-recoverycodes-count',
			'agent' => $oUser->getUser(),
			'extra' => [
				'codeCount' => $tokenCount,
				'generatedCount' => $generatedCount,
			],
		] );
	}

	/**
	 * Sends a notification that the user had additional recovery tokens generated for them
	 */
	public static function notifyRecoveryTokensGeneratedForUser(
		UserIdentity $targetUser,
		int $tokenCount
	): void {
		if ( !self::isEnabled() ) {
			return;
		}
		Event::create( [
			// message used: notification-header-oathauth-recoverycodes-generated-for-user
			'type' => 'oathauth-recoverycodes-generated-for-user',
			'extra' => [
				'codeCount' => $tokenCount
			],
		], new RecipientSet( $targetUser ) );
	}
}
