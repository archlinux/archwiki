<?php

namespace MediaWiki\Skins\Vector;

use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\Skin\Hook\SkinPageReadyConfigHook;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\Skins\Vector\Hooks\HookRunner;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;

/**
 * Presentation hook handlers for Vector skin.
 *
 * Hook handler method names should be in the form of:
 *	on<HookName>()
 * @package Vector
 * @internal
 */
class Hooks implements
	GetPreferencesHook,
	LocalUserCreatedHook,
	SkinPageReadyConfigHook
{
	public function __construct(
		private readonly Config $config,
		private readonly UserOptionsManager $userOptionsManager,
	) {
	}

	/**
	 * Checks if the current skin is a variant of Vector
	 *
	 * @param string $skinName
	 * @return bool
	 */
	private static function isVectorSkin( string $skinName ): bool {
		return (
			$skinName === Constants::SKIN_NAME_LEGACY ||
			$skinName === Constants::SKIN_NAME_MODERN
		);
	}

	/**
	 * Generates config variables for skins.vector.search Resource Loader module (defined in
	 * skin.json).
	 *
	 * @param RL\Context $context
	 * @param Config $config
	 * @return array<string,mixed>
	 */
	public static function getVectorSearchResourceLoaderConfig(
		RL\Context $context,
		Config $config
	): array {
		$additionalSearchOptions = [
			'highlightQuery' =>
				VectorServices::getLanguageService()->canWordsBeSplitSafely( $context->getLanguage() )
		];

		$hookRunner = new HookRunner( MediaWikiServices::getInstance()->getHookContainer() );
		$hookRunner->onVectorSearchResourceLoaderConfig( $additionalSearchOptions );

		$vectorTypeahead = $config->get( 'VectorTypeahead' );
		$vectorTypeahead['options'] = array_merge( $vectorTypeahead['options'], $additionalSearchOptions );
		return $vectorTypeahead;
	}

	/**
	 * SkinPageReadyConfig hook handler
	 *
	 * Replace searchModule provided by skin.
	 *
	 * @since 1.35
	 * @param RL\Context $context
	 * @param mixed[] &$config Associative array of configurable options
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onSkinPageReadyConfig(
		RL\Context $context,
		array &$config
	) {
		// It's better to exit before any additional check
		if ( !self::isVectorSkin( $context->getSkin() ) ) {
			return;
		}
		// Tell the `mediawiki.page.ready` module not to wire up search.
		// This allows us to use the new Vue implementation.
		// Context has no knowledge of legacy / modern Vector
		// and from its point of view they are the same thing.
		// Please see the modules `skins.vector.js` and `skins.vector.legacy.js`
		// for the wire up of search.
		$config['searchModule'] = 'skins.vector.search';
	}

	/**
	 * Moves watch item from actions to views menu.
	 *
	 * @internal used inside Hooks::onSkinTemplateNavigation
	 * @param array &$content_navigation
	 */
	private static function updateActionsMenu( &$content_navigation ) {
		$key = null;
		if ( isset( $content_navigation['actions']['watch'] ) ) {
			$key = 'watch';
		}
		if ( isset( $content_navigation['actions']['unwatch'] ) ) {
			$key = 'unwatch';
		}

		// Promote watch link from actions to views and add an icon
		// The second check to isset is pointless but shuts up phan.
		if ( $key !== null && isset( $content_navigation['actions'][ $key ] ) ) {
			$content_navigation['views'][$key] = $content_navigation['actions'][$key];
			unset( $content_navigation['actions'][$key] );
		}
	}

	/**
	 * Adds icons to items in the "views" menu.
	 *
	 * @internal used inside Hooks::onSkinTemplateNavigation
	 * @param array &$content_navigation
	 * @param bool $isLegacy is this the legacy Vector skin?
	 */
	private static function updateViewsMenuIcons( &$content_navigation, $isLegacy ) {
		foreach ( $content_navigation['views'] as &$item ) {
			$icon = $item['icon'] ?? null;
			if ( $icon ) {
				if ( $isLegacy ) {
					self::appendClassToItem(
						$item['class'],
						[ 'icon' ]
					);
				} else {
					// Force the item as a button with hidden text.
					$item['button'] = true;
					$item['text-hidden'] = true;
					$item = self::updateMenuItemData( $item, false );
				}
			} elseif ( !$isLegacy ) {
				// The vector-tab-noicon class is only used in Vector-22.
				self::appendClassToItem(
					$item['class'],
					[ 'vector-tab-noicon' ]
				);
			}
		}
	}

	/**
	 * All associated pages menu items do not have icons so are given the vector-tab-noicon class.
	 *
	 * @internal used inside Hooks::onSkinTemplateNavigation
	 * @param array &$content_navigation
	 */
	private static function updateAssociatedPagesMenuIcons( &$content_navigation ) {
		foreach ( $content_navigation['associated-pages'] as &$item ) {
			self::appendClassToItem(
				$item['class'],
				[ 'vector-tab-noicon' ]
			);
		}
	}

	/**
	 * Adds class to a property
	 *
	 * @param array|string &$item to update
	 * @param array|string $classes to add to the item
	 */
	private static function appendClassToItem( &$item, $classes ) {
		$existingClasses = $item;

		if ( is_array( $existingClasses ) ) {
			// Treat as array
			$newArrayClasses = is_array( $classes ) ? $classes : [ trim( $classes ) ];
			$item = array_merge( $existingClasses, $newArrayClasses );
		} elseif ( is_string( $existingClasses ) ) {
			// Treat as string
			$newStrClasses = is_string( $classes ) ? trim( $classes ) : implode( ' ', $classes );
			$item .= ' ' . $newStrClasses;
		} else {
			// Treat as whatever $classes is
			$item = $classes;
		}

		if ( is_string( $item ) ) {
			$item = trim( $item );
		}
	}

	/**
	 * @internal used inside ::updateUserLinksDropdownItems
	 * @param array $content_navigation
	 * @return bool
	 */
	private static function isReadingListEnabled( $content_navigation ) {
		return isset( $content_navigation['user-menu']['readinglists'] );
	}

	/**
	 * Make an icon
	 *
	 * @internal for use inside Vector skin.
	 * @param string $name
	 * @return string of HTML
	 */
	private static function makeIcon( $name ) {
		// Html::makeLink will pass this through rawElement
		return '<span class="vector-icon mw-ui-icon-' . $name . ' mw-ui-icon-wikimedia-' . $name . '"></span>';
	}

	/**
	 * Update template data to include classes and html that handle buttons and icons.
	 *
	 * @internal used in ::updateMenuItemData
	 * @param array $item data to update
	 * @param string $buttonClassProp property to append button classes
	 * @param string $iconHtmlProp property to set icon HTML
	 * @param bool $unsetIcon should the icon field be unset?
	 * @return array $item Updated data
	 */
	private static function updateItemData(
		$item, $buttonClassProp, $iconHtmlProp, $unsetIcon = true
	) {
		$hasButton = $item['button'] ?? false;
		$hideText = $item['text-hidden'] ?? false;
		$icon = $item['icon'] ?? '';
		if ( $unsetIcon ) {
			unset( $item['icon'] );
		}
		unset( $item['button'] );
		unset( $item['text-hidden'] );

		if ( $hasButton ) {
			// Hardcoded button classes, this should be fixed by replacing Hooks.php with VectorComponentButton.php
			self::appendClassToItem( $item[ $buttonClassProp ], [
				'cdx-button',
				'cdx-button--fake-button',
				'cdx-button--fake-button--enabled',
				'cdx-button--weight-quiet'
			] );
		}
		if ( $icon ) {
			if ( $hideText && $hasButton ) {
				self::appendClassToItem( $item[ $buttonClassProp ], [ 'cdx-button--icon-only' ] );
			}

			$item[ $iconHtmlProp ] = self::makeIcon( $icon );
		}
		return $item;
	}

	/**
	 * Updates template data for Vector menu items.
	 *
	 * @internal used inside Hooks::updateMenuItems ::updateViewsMenuIcons and ::updateUserLinksDropdownItems
	 * @param array $item menu item data to update
	 * @param bool $unsetIcon should the icon field be unset?
	 * @return array $item Updated menu item data
	 */
	private static function updateMenuItemData( $item, $unsetIcon = true ) {
		$buttonClassProp = 'link-class';
		$iconHtmlProp = 'link-html';
		return self::updateItemData( $item, $buttonClassProp, $iconHtmlProp, $unsetIcon );
	}

	/**
	 * Vector 2022 only:
	 * Creates an additional menu that will be injected inside the more (cactions)
	 * dropdown menu. This menu is a clone of `views` and this menu will only be
	 * shown at low resolutions (when the `views` menu is hidden).
	 *
	 * An additional menu is used instead of adding to the existing cactions menu
	 * so that the emptyPortlet logic for that menu is preserved and the cactions menu
	 * is not shown at large resolutions when empty (e.g. all items including collapsed
	 * items are hidden).
	 *
	 * @param array &$content_navigation
	 */
	private static function createMoreOverflowMenu( &$content_navigation ) {
		$clonedViews = [];
		foreach ( $content_navigation['views'] ?? [] as $key => $item ) {
			$newItem = $item;
			self::appendClassToItem( $newItem[ 'class' ], 'vector-more-collapsible-item' );
			$clonedViews['more-' . $key] = $newItem;
		}
		// Inject collapsible menu items ahead of existing actions.
		$content_navigation['views-overflow'] = $clonedViews;
	}

	/**
	 * Upgrades Vector's watch action to a watchstar.
	 * This is invoked inside SkinVector, not via skin registration, as skin hooks
	 * are not guaranteed to run last.
	 * This can possibly be revised based on the outcome of T287622.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation
	 * @param SkinTemplate $sk
	 * @param array &$content_navigation
	 */
	public static function onSkinTemplateNavigation( $sk, &$content_navigation ) {
		$skinName = $sk->getSkinName();
		// These changes should only happen in Vector.
		if ( !$skinName || !self::isVectorSkin( $skinName ) ) {
			return;
		}

		$title = $sk->getRelevantTitle();
		if (
			$sk->getConfig()->get( 'VectorUseIconWatch' ) &&
			$title && $title->canExist() &&
			// Only move the watchstar if bookmark not detected
			// T402352
			!self::isReadingListEnabled( $content_navigation )
		) {
			self::updateActionsMenu( $content_navigation );
		}

		if ( $skinName === Constants::SKIN_NAME_MODERN ) {
			self::createMoreOverflowMenu( $content_navigation );
		}

		// The updating of the views menu happens /after/ the overflow menu has been created
		// this avoids icons showing in the more overflow menu.
		self::updateViewsMenuIcons( $content_navigation, self::isSkinVersionLegacy( $skinName ) );
		self::updateAssociatedPagesMenuIcons( $content_navigation );
	}

	/**
	 * Adds Vector specific user preferences that can only be accessed via API.
	 *
	 * @param User $user User whose preferences are being modified.
	 * @param array[] &$prefs Preferences description array, to be fed to a HTMLForm object.
	 */
	public function onGetPreferences( $user, &$prefs ): void {
		$vectorPrefs = [
			Constants::PREF_KEY_LIMITED_WIDTH => [
				'type' => 'toggle',
				'label-message' => 'vector-prefs-limited-width',
				'section' => 'rendering/skin/skin-prefs',
				'help-message' => 'vector-prefs-limited-width-help',
				'hide-if' => [ '!==', 'skin', Constants::SKIN_NAME_MODERN ],
			],
			Constants::PREF_KEY_FONT_SIZE => [
				'type' => 'select',
				'label-message' => 'vector-feature-custom-font-size-name',
				'section' => 'rendering/skin/skin-prefs',
				'options-messages' => [
					'vector-feature-custom-font-size-0-label' => '0',
					'vector-feature-custom-font-size-1-label' => '1',
					'vector-feature-custom-font-size-2-label' => '2',
				],
				'hide-if' => [ '!==', 'skin', Constants::SKIN_NAME_MODERN ],
			],
			Constants::PREF_KEY_PAGE_TOOLS_PINNED => [
				'type' => 'api'
			],
			Constants::PREF_KEY_MAIN_MENU_PINNED => [
				'type' => 'api'
			],
			Constants::PREF_KEY_TOC_PINNED => [
				'type' => 'api'
			],
			Constants::PREF_KEY_APPEARANCE_PINNED => [
				'type' => 'api'
			],
			Constants::PREF_KEY_NIGHT_MODE => [
				'type' => 'select',
				'label-message' => 'skin-theme-name',
				'help-message' => 'skin-theme-description',
				'section' => 'rendering/skin/skin-prefs',
				'options-messages' => [
					'skin-theme-day-label' => 'day',
					'skin-theme-night-label' => 'night',
					'skin-theme-os-label' => 'os',
				],
				'hide-if' => [ '!==', 'skin', Constants::SKIN_NAME_MODERN ],
			],
		];
		$prefs += $vectorPrefs;
	}

	/**
	 * Called one time when initializing a users preferences for a newly created account.
	 *
	 * @param User $user Newly created user object.
	 * @param bool $isAutoCreated
	 */
	public function onLocalUserCreated( $user, $isAutoCreated ) {
		$default = $this->config->get( Constants::CONFIG_KEY_DEFAULT_SKIN_VERSION_FOR_NEW_ACCOUNTS );
		if ( $default ) {
			$this->userOptionsManager->setOption(
				$user,
				Constants::PREF_KEY_SKIN,
				$default === Constants::SKIN_VERSION_LEGACY ?
					Constants::SKIN_NAME_LEGACY : Constants::SKIN_NAME_MODERN
			);
		}
	}

	/**
	 * Gets whether the current skin version is the legacy version.
	 *
	 * @param string $skinName hint that can be used to detect modern vector.
	 * @return bool
	 */
	private static function isSkinVersionLegacy( $skinName ): bool {
		return $skinName === Constants::SKIN_NAME_LEGACY;
	}
}
