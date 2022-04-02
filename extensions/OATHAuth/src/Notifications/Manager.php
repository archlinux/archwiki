<?php
/**
 * Copyright (C) 2022 Kunal Mehta <legoktm@debian.org>
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
 */

namespace MediaWiki\Extension\OATHAuth\Notifications;

use EchoEvent;
use ExtensionRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use SpecialPage;

/**
 * Manages logic for configuring and sending out notifications with Echo
 */
class Manager {
	/**
	 * Whether Echo is installed and can be used
	 *
	 * @return bool
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
	public static function notifyDisabled( OATHUser $oUser, bool $self ) {
		if ( !self::isEnabled() ) {
			return;
		}
		EchoEvent::create( [
			'type' => 'oathauth-disable',
			'title' => SpecialPage::getTitleFor( 'Preferences' ),
			'agent' => $oUser->getUser(),
			'extra' => [
				'self' => $self,
			]
		] );
	}

	/**
	 * Hook: BeforeCreateEchoEvent
	 *
	 * Configure our notification types. We don't register a category since
	 * these are all "system" messages that cannot be disabled.
	 *
	 * @param array &$notifications
	 */
	public static function onBeforeCreateEchoEvent( &$notifications ) {
		$notifications['oathauth-disable'] = [
			'category' => 'system',
			'group' => 'negative',
			'section' => 'alert',
			'presentation-model' => DisablePresentationModel::class,
			'canNotifyAgent' => true,
			'user-locators' => [ 'EchoUserLocator::locateEventAgent' ],
		];
	}
}
