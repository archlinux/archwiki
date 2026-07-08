<?php
namespace MediaWiki\Skins\Vector\Components;

use MediaWiki\Html\Html;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Linker\Linker;
use MediaWiki\Message\Message;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;

/**
 * VectorComponentUserLinks component
 */
class VectorComponentUserLinks implements VectorComponent {
	private const BUTTON_CLASSES = 'cdx-button cdx-button--fake-button '
		. 'cdx-button--fake-button--enabled cdx-button--weight-quiet';
	private const ICON_ONLY_BUTTON_CLASS = 'cdx-button--icon-only';
	private const COLLAPSIBLE_CLASS = 'user-links-collapsible-item';

	private const ACCOUNT_MENU_ITEM_KEYS = [
		'createaccount',
		'login',
		'login-private',
	];
	private const OVERFLOW_MENU_ITEM_KEYS = [
		'readinglists',
		'watchlist',
		'sitesupport',
	];

	/**
	 * @param MessageLocalizer $localizer
	 * @param UserIdentity $user
	 * @param UserNameUtils $userNameUtils
	 * @param array $portletData
	 * @param string $userIcon that represents the current type of user
	 */
	public function __construct(
		private readonly MessageLocalizer $localizer,
		private readonly UserIdentity $user,
		private readonly UserNameUtils $userNameUtils,
		private readonly array $portletData,
		private readonly string $userIcon = 'userAvatar',
	) {
	}

	/**
	 * @param string $key
	 * @return Message
	 */
	private function msg( $key ): Message {
		return $this->localizer->msg( $key );
	}

	/**
	 * @param bool $isDefaultAnonUserLinks
	 * @param int $userLinksCount
	 * @return VectorComponentDropdown
	 */
	private function getDropdown( $isDefaultAnonUserLinks, $userLinksCount ) {
		$user = $this->user;
		$isAnon = !$user->isRegistered();

		$class = 'vector-user-menu';
		$class .= ' vector-button-flush-right';
		$class .= !$isAnon ?
			' vector-user-menu-logged-in' :
			' vector-user-menu-logged-out';

		// Hide entire user links dropdown on larger viewports if it only contains
		// create account & login link, which are only shown on smaller viewports
		if ( $isAnon && $isDefaultAnonUserLinks ) {
			$linkclass = ' ' . self::COLLAPSIBLE_CLASS;

			if ( $userLinksCount === 0 ) {
				// The user links can be completely empty when even login is not possible
				// (e.g using remote authentication). In this case, we need to hide the
				// dropdown completely not only on larger viewports.
				$linkclass .= '--none';
			}

			$class .= $linkclass;
		}

		$tooltip = Html::expandAttributes( [
			'title' => $this->msg( 'vector-personal-tools-tooltip' )->text(),
		] );
		$icon = $this->userIcon;
		if ( $icon === '' && $userLinksCount ) {
			$icon = 'ellipsis';
			// T287494 We use tooltip messages to provide title attributes on hover over certain menu icons.
			// For modern Vector, the "tooltip-p-personal" key is set to "User menu" which is appropriate for
			// the user icon (dropdown indicator for user links menu) for logged-in users.
			// This overrides the tooltip for the user links menu icon which is an ellipsis for anonymous users.
			$tooltip = Linker::tooltip( 'vector-anon-user-menu-title' ) ?? '';
		}

		return new VectorComponentDropdown(
			'vector-user-links-dropdown', $this->msg( 'personaltools' )->text(), $class, $icon, $tooltip
		);
	}

	/**
	 * @param UserIdentity $user
	 * @return array
	 */
	private function getOverflowKeys( $user ) {
		// Only certain items get promoted to the overflow menu:
		// * readinglist
		// * watchlist
		// * (account keys)
		if ( $this->userNameUtils->isTemp( $user->getName() ) ) {
			// Temporary accounts don't show the account items in overflow
			return array_diff(
				self::OVERFLOW_MENU_ITEM_KEYS,
				self::ACCOUNT_MENU_ITEM_KEYS
			);
		} else {
			return array_merge( self::OVERFLOW_MENU_ITEM_KEYS, self::ACCOUNT_MENU_ITEM_KEYS );
		}
	}

	/**
	 * @param bool $isDefaultAnonUserLinks
	 * @return array
	 */
	private function getMenus( $isDefaultAnonUserLinks ) {
		$user = $this->user;
		$isAnon = !$user->isRegistered();
		$portletData = $this->portletData;

		// Hide default user menu on larger viewports if it only contains
		// create account & login link, which are only shown on smaller viewports
		// FIXME: Replace array_merge with an add class helper function
		$userMenuClass = $portletData[ 'data-user-menu' ][ 'class' ] ?? '';
		$userMenuClass = $isAnon && $isDefaultAnonUserLinks ?
			$userMenuClass . ' ' . self::COLLAPSIBLE_CLASS : $userMenuClass;

		$overflowKeys = self::getOverflowKeys( $user );
		$userMenuData = $portletData[ 'data-user-menu' ][ 'array-items' ];
		$userMenuOverrides = [];
		// Construct overrides for any menu item that is duplicated in the overflow menu
		foreach ( $overflowKeys as $key ) {
			$menuItem = array_filter( $userMenuData, static function ( $item ) use ( $key ) {
				return $item['name'] === $key;
			} );

			if ( $menuItem ) {
				$menuItem = array_values( $menuItem )[0];
				$userMenuOverrides[ $menuItem[ 'id' ] ] = [ 'collapsible' => true ];
			}
		}
		$userMenu = $this->updateMenuItemStyles( $userMenuData, [], $userMenuOverrides );

		return [
			new VectorComponentMenu( [
				'id' => 'p-personal',
				'label' => null,
				'class' => $userMenuClass,
				'html-tooltip' => $portletData[ 'data-user-menu' ][ 'html-tooltip' ] ?? '',
				'array-list-items' => $userMenu
			] )
		];
	}

	/**
	 * Update menu item styling based of default menu styles and overrides
	 * Style options include: 'button', 'collapsible', 'icon'
	 * 'button' can be boolean or an array with button options, e.g. ['iconOnly' => true]
	 *
	 * @param array $menuItems
	 * @param array $menuStyles all menu items will use these default styles unless there's an item specific override
	 * @param array $overrides styles for individual menu items keyed by item id, which will override $menuStyles
	 * @return array
	 */
	private static function updateMenuItemStyles( $menuItems, $menuStyles, $overrides = [] ) {
		return array_map( static function ( $item ) use ( $menuStyles, $overrides ) {
			$id = $item['id'];
			$hasOverrides = $id && isset( $overrides[ $id ] );
			$styles = $hasOverrides ? $overrides[ $id ] : $menuStyles;

			$isCollapsible = $styles['collapsible'] ?? false;
			// collapsible class is added to the item (LI element) class
			if ( $isCollapsible ) {
				$class = $item['class'] ?? '';
				$item['class'] = $class . ' ' . self::COLLAPSIBLE_CLASS;
			}
			// Update link classes
			$item['array-links'] = array_map( static function ( $link ) use ( $styles ) {
				if ( array_key_exists( 'icon', $styles ) ) {
					$link['icon'] = $styles['icon'];
				}
				$link['array-attributes'] = array_map( static function ( $attribute ) use ( $styles ) {
					if ( $attribute['key'] === 'class' ) {
						$newClass = $attribute['value'];
						$isButton = $styles['button'] ?? false;
						$isIconOnlyButton = $styles['button' ]['iconOnly'] ?? false;
						if ( $isButton ) {
							$newClass .= ' ' . self::BUTTON_CLASSES;
						}
						if ( $isIconOnlyButton ) {
							$newClass .= ' ' . self::ICON_ONLY_BUTTON_CLASS;
						}
						$attribute['value'] = $newClass;
					}
					return $attribute;
				}, $link['array-attributes'] ?? [] );
				return $link;
			}, $item['array-links'] );
			return $item;
		}, $menuItems );
	}

	/**
	 * What class should the overflow menu have?
	 *
	 * @param array $arrayListItems
	 * @return string
	 */
	private static function getOverflowMenuClass( $arrayListItems ) {
		$overflowMenuClass = 'mw-portlet';
		if ( count( $arrayListItems ) === 0 ) {
			$overflowMenuClass .= ' emptyPortlet';
		}
		return $overflowMenuClass;
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$portletData = $this->portletData;
		$user = $this->user;

		$userLinksCount = count( $portletData['data-user-menu']['array-items'] );
		$isDefaultAnonUserLinks = $userLinksCount <= 3;
		$userInterfacePreferences = $this->updateMenuItemStyles(
			$portletData[ 'data-user-interface-preferences' ]['array-items'] ?? [],
			// applies to all menu items
			[
				'button' => true,
				'collapsible' => true,
			]
		);

		$userPage = $this->updateMenuItemStyles(
			$portletData[ 'data-user-page' ]['array-items'] ?? [],
			// applies to all menu items
			[
				'collapsible' => true,
				'icon' => null,
			]
		);

		$notifications = $this->updateMenuItemStyles(
			$portletData[ 'data-notifications' ]['array-items'] ?? [],
			// applies to all menu items EXCEPT the "You have a talk page message" (pt-talk-alert)
			[
				'button' => [
					'iconOnly' => true,
				],
			],
			[
				'pt-talk-alert' => [
					'button' => false,
					'icon' => null,
				],
			]
		);

		$overflowKeys = self::getOverflowKeys( $user );
		$overflow = array_map(
			static function ( $item ) {
				// Since we're creating duplicate icons
				$item['id'] .= '-2';
				return $item;
			},
			// array_filter preserves keys so use array_values to restore array.
			array_values(
				array_filter(
					$portletData['data-user-menu']['array-items'] ?? [],
					static function ( $item ) use ( $overflowKeys ) {
						$name = $item['name'];
						return in_array( $name, $overflowKeys );
					}
				)
			)
		);

		// Logged in overflow menu items are icon only buttons
		// Styles for anon overflow menu is generally collapsible with no icon
		// the overflow donate link (pt-sitesupport-2) is an exception
		$overflowDefaultStyle = $this->user->isRegistered() ? [
			'button' => [
				'iconOnly' => true,
			],
			'collapsible' => true,
		] : [
			'button' => true,
			'collapsible' => true,
		];

		// Disable buttons and hide icons for the account actions:
		$overrides = [];
		foreach ( self::ACCOUNT_MENU_ITEM_KEYS as $key ) {
			$overrides['pt-' . $key . '-2'] = [
				'button' => false,
				'icon' => null,
				'collapsible' => true,
			];
		}

		$overflow = $this->updateMenuItemStyles(
			$overflow,
			// applies to all menu items
			$overflowDefaultStyle,
			// item specific overrides
			$overrides,
		);

		$preferencesMenu = new VectorComponentMenu( [
			'id' => 'p-vector-user-menu-preferences',
			'class' => self::getOverflowMenuClass( $userInterfacePreferences ),
			'label' => null,
			'html-items' => null,
			'array-list-items' => $userInterfacePreferences,
		] );
		$userPageMenu = new VectorComponentMenu( [
			'id' => 'p-vector-user-menu-userpage',
			'class' => self::getOverflowMenuClass( $userPage ),
			'label' => null,
			'html-items' => null,
			'array-list-items' => $userPage,
			'html-after-portal' => $portletData[ 'data-user-page' ]['html-after-portal'] ?? '',
		] );
		$notificationsMenu = new VectorComponentMenu( [
			'id' => 'p-vector-user-menu-notifications',
			'class' => self::getOverflowMenuClass( $notifications ),
			'label' => null,
			'html-items' => null,
			'array-list-items' => $notifications,
		] );
		$overflowMenu = new VectorComponentMenu( [
			'id' => 'p-vector-user-menu-overflow',
			'class' => self::getOverflowMenuClass( $overflow ),
			'label' => null,
			'html-items' => null,
			'array-list-items' => $overflow,
		] );

		return [
			'is-wide' => array_filter(
				[ $overflow, $notifications, $userPage, $userInterfacePreferences ]
			) !== [],
			'data-user-links-notifications' => $notificationsMenu->getTemplateData(),
			'data-user-links-overflow' => $overflowMenu->getTemplateData(),
			'data-user-links-preferences' => $preferencesMenu->getTemplateData(),
			'data-user-links-user-page' => $userPageMenu->getTemplateData(),
			'data-user-links-dropdown' => $this->getDropdown(
				$isDefaultAnonUserLinks, $userLinksCount )->getTemplateData(),
			'data-user-links-menus' => array_map( static function ( $menu ) {
				return $menu->getTemplateData();
			}, $this->getMenus( $isDefaultAnonUserLinks ) ),
		];
	}
}
