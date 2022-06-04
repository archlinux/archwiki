<?php
/**
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
 */

namespace MediaWiki\Minerva\Menu;

use IContextSource;
use MediaWiki\Minerva\Menu\Entries\AuthMenuEntry;
use MediaWiki\Minerva\Menu\Entries\SingleMenuEntry;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use Message;
use MWException;
use SpecialPage;
use Title;

/**
 * Set of all know menu items for easier building
 */
final class Definitions {

	/**
	 * @var UserIdentity
	 */
	private $user;

	/**
	 * @var IContextSource
	 */
	private $context;

	/**
	 * @var SpecialPageFactory
	 */
	private $specialPageFactory;

	/**
	 * @var UserOptionsLookup
	 */
	private $userOptionsLookup;

	/**
	 * Initialize definitions helper class
	 *
	 * @param IContextSource $context
	 * @param SpecialPageFactory $factory
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		IContextSource $context,
		SpecialPageFactory $factory,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->user = $context->getUser();
		$this->context = $context;
		$this->specialPageFactory = $factory;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * Creates a login or logout button with a profile button.
	 *
	 * @param Group $group
	 * @throws MWException
	 */
	public function insertAuthMenuItem( Group $group ) {
		$group->insertEntry( new AuthMenuEntry(
				$this->user,
				$this->context,
				$this->newLogInOutQuery( $this->newReturnToQuery() )
		) );
	}

	/**
	 * Perform message localization
	 * @param string $key to localize
	 * @return Message
	 */
	public function msg( string $key ) {
		return $this->context->msg( $key );
	}

	/**
	 * If Nearby is supported, build and inject the Nearby link
	 * @param Group $group
	 * @throws MWException
	 */
	public function insertNearbyIfSupported( Group $group ) {
		// Nearby link (if supported)
		if ( $this->specialPageFactory->exists( 'Nearby' ) ) {
			$group->insert( 'nearby', /* $isJSOnly = */ true )
				->addComponent(
					$this->context->msg( 'mobile-frontend-main-menu-nearby' )->text(),
					SpecialPage::getTitleFor( 'Nearby' )->getLocalURL(),
					'',
					[ 'data-event-name' => 'menu.nearby' ],
					'minerva-mapPin'
				);
		}
	}

	/**
	 * Build and insert the Settings link
	 * @param Group $group
	 * @throws MWException
	 */
	public function insertMobileOptionsItem( Group $group ) {
		$title = $this->context->getTitle();
		$config = $this->context->getConfig();
		$returnToTitle = $title->getPrefixedText();
		$user = $this->user;
		$betaEnabled = $config->get( 'MFEnableBeta' );
		/*
		 * to avoid linking to an empty settings page we make this jsonly when:
		 * - AMC and beta is disabled (if logged in there is nothing to show)
		 * - user is logged out and beta is disabled (beta is the only thing a non-js user can do)
		 * In future we might want to make this a static function on Special:MobileOptions.
		 */
		$jsonly = ( !$user->isRegistered() && !$betaEnabled ) ||
			( $user->isRegistered() && !$config->get( 'MFAdvancedMobileContributions' ) &&
				!$betaEnabled
			);

		$item = SingleMenuEntry::create(
			'settings',
			$this->context->msg( 'mobile-frontend-main-menu-settings' )->text(),
			SpecialPage::getTitleFor( 'MobileOptions' )
				->getLocalURL( [ 'returnto' => $returnToTitle ] )
		);
		if ( $jsonly ) {
			$item->setJSOnly();
		}
		$group->insertEntry( $item );
	}

	/**
	 * Build and insert the Preferences link
	 * @param Group $group
	 * @throws MWException
	 */
	public function insertPreferencesItem( Group $group ) {
		$entry = SingleMenuEntry::create(
			'preferences',
			$this->context->msg( 'preferences' )->text(),
			SpecialPage::getTitleFor( 'Preferences' )->getLocalURL()
		);
		$entry->setIcon( 'settings' );
		$group->insertEntry( $entry );
	}

	/**
	 * Build and insert About page link
	 * @param Group $group
	 */
	public function insertAboutItem( Group $group ) {
		$msg = $this->context->msg( 'aboutsite' );
		if ( $msg->isDisabled() ) {
			return;
		}
		$title = Title::newFromText( $this->context->msg( 'aboutpage' )->inContentLanguage()->text() );
		if ( !$title ) {
			return;
		}
		$group->insert( 'about' )
			->addComponent( $msg->text(), $title->getLocalURL() );
	}

	/**
	 * Build and insert Disclaimers link
	 * @param Group $group
	 */
	public function insertDisclaimersItem( Group $group ) {
		$msg = $this->context->msg( 'disclaimers' );
		if ( $msg->isDisabled() ) {
			return;
		}
		$title = Title::newFromText( $this->context->msg( 'disclaimerpage' )
			->inContentLanguage()->text() );
		if ( !$title ) {
			return;
		}
		$group->insert( 'disclaimers' )
			->addComponent( $msg->text(), $title->getLocalURL() );
	}

	/**
	 * Build and insert the RecentChanges link
	 * @param Group $group
	 * @throws MWException
	 */
	public function insertRecentChanges( Group $group ) {
		$title = SpecialPage::getTitleFor( 'Recentchanges' );

		$group->insert( 'recentchanges' )
			->addComponent(
				$this->context->msg( 'recentchanges' )->escaped(),
				$title->getLocalURL(),
				'',
				[ 'data-event-name' => 'menu.recentchanges' ],
				'minerva-recentChanges'
			);
	}

	/**
	 * Build and insert the SpecialPages link
	 * @param Group $group
	 * @throws MWException
	 */
	public function insertSpecialPages( Group $group ) {
		$group->insertEntry(
			SingleMenuEntry::create(
				'specialPages',
				$this->context->msg( 'specialpages' )->text(),
				SpecialPage::getTitleFor( 'Specialpages' )->getLocalURL()
			)
		);
	}

	/**
	 * Build and insert the CommunityPortal link
	 * @param Group $group
	 * @throws MWException
	 */
	public function insertCommunityPortal( Group $group ) {
		$msg = $this->context->msg( 'portal' );
		if ( $msg->isDisabled() ) {
			return;
		}
		$title = Title::newFromText( $this->context->msg( 'portal-url' )
			->inContentLanguage()->text() );
		if ( !$title ) {
			return;
		}
		$group->insertEntry( SingleMenuEntry::create(
			'speechBubbles',
			$msg->text(),
			$title->getLocalURL()
		) );
	}

	/**
	 * @param array $returnToQuery
	 * @return array
	 */
	private function newLogInOutQuery( array $returnToQuery ): array {
		$ret = [];
		$title = $this->context->getTitle();
		if ( $title && !$title->isSpecial( 'Userlogin' ) ) {
			$ret[ 'returnto' ] = $title->getPrefixedText();
		}
		if ( $this->user && !$this->user->isRegistered() ) {
			// unset campaign on login link so as not to interfere with A/B tests
			unset( $returnToQuery['campaign'] );
		}
		if ( !empty( $returnToQuery ) ) {
			$ret['returntoquery'] = wfArrayToCgi( $returnToQuery );
		}
		return $ret;
	}

	/**
	 * Retrieve current query parameters from Request object so system can pass those
	 * to the Login/logout links
	 * Some parameters are disabled (like title), as the returnto will be replaced with
	 * the current page.
	 * @return array
	 */
	private function newReturnToQuery(): array {
		$returnToQuery = [];
		$request = $this->context->getRequest();
		if ( !$request->wasPosted() ) {
			$returnToQuery = $request->getValues();
			unset( $returnToQuery['title'] );
			unset( $returnToQuery['returnto'] );
			unset( $returnToQuery['returntoquery'] );
		}
		return $returnToQuery;
	}

	/**
	 * Insert the Donate Link in the Mobile Menu.
	 *
	 * @param Group $group
	 * @throws MWException
	 */
	public function insertDonateItem( Group $group ) {
		$labelMsg = $this->context->msg( 'sitesupport' );
		$urlMsg = $this->context->msg( 'sitesupport-url' );
		if ( !$urlMsg->exists() || $labelMsg->isDisabled() ) {
			return;
		}
		// Add term field to allow distinguishing from other sidebars.
		// https://www.mediawiki.org/wiki/Wikimedia_Product/Analytics_Infrastructure/Schema_fragments#Campaign_Attribution
		$url = wfAppendQuery(
			$urlMsg->text(),
			[ 'utm_key' => 'minerva' ]
		);

		 $group->insert( 'donate' )->addComponent(
			$labelMsg->text(),
			$url,
			'',
			[
				// for consistency with desktop
				'id' => 'n-sitesupport',
				'data-event-name' => 'menu.donate',
			],
			'minerva-heart'
		);
	}
}
