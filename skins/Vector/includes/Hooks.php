<?php

namespace Vector;

use Config;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use OutputPage;
use ResourceLoaderContext;
use RuntimeException;
use Skin;
use SkinTemplate;
use Title;
use User;
use Vector\HTMLForm\Fields\HTMLLegacySkinVersionField;

/**
 * Presentation hook handlers for Vector skin.
 *
 * Hook handler method names should be in the form of:
 *	on<HookName>()
 * @package Vector
 * @internal
 */
class Hooks {
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
	 * @param Config $config
	 * @return array
	 */
	private static function getActiveABTest( $config ) {
		$ab = $config->get(
			Constants::CONFIG_STICKY_HEADER_TREATMENT_AB_TEST_ENROLLMENT
		);
		if ( count( $ab ) === 0 ) {
			// If array is empty then no experiment and need to validate.
			return $ab;
		}
		if ( !array_key_exists( 'buckets', $ab ) ) {
			throw new RuntimeException( 'Invalid VectorWebABTestEnrollment value: Must contain buckets key.' );
		}
		if ( !array_key_exists( 'unsampled', $ab['buckets'] ) ) {
			throw new RuntimeException( 'Invalid VectorWebABTestEnrollment value: Must define an `unsampled` bucket.' );
		} else {
			// check bucket values.
			foreach ( $ab['buckets'] as $bucketName => $bucketDefinition ) {
				if ( !is_array( $bucketDefinition ) ) {
					throw new RuntimeException( 'Invalid VectorWebABTestEnrollment value: Buckets should be arrays' );
				}
				$samplingRate = $bucketDefinition['samplingRate'];
				if ( is_string( $samplingRate ) ) {
					throw new RuntimeException(
						'Invalid VectorWebABTestEnrollment value: Sampling rate should be number between 0 and 1.'
					);
				}
			}
		}

		return $ab;
	}

	/**
	 * Passes config variables to Vector (modern) ResourceLoader module.
	 * @param ResourceLoaderContext $context
	 * @param Config $config
	 * @return array
	 */
	public static function getVectorResourceLoaderConfig(
		ResourceLoaderContext $context,
		Config $config
	) {
		return [
			'wgVectorSearchHost' => $config->get( 'VectorSearchHost' ),
			'wgVectorWebABTestEnrollment' => self::getActiveABTest( $config ),
		];
	}

	/**
	 * Generates config variables for skins.vector.search Resource Loader module (defined in
	 * skin.json).
	 *
	 * @param ResourceLoaderContext $context
	 * @param Config $config
	 * @return array<string,mixed>
	 */
	public static function getVectorWvuiSearchResourceLoaderConfig(
		ResourceLoaderContext $context,
		Config $config
	): array {
		$result = $config->get( 'VectorWvuiSearchOptions' );
		$result['highlightQuery'] =
			VectorServices::getLanguageService()->canWordsBeSplitSafely( $context->getLanguage() );

		return $result;
	}

	/**
	 * SkinPageReadyConfig hook handler
	 *
	 * Replace searchModule provided by skin.
	 *
	 * @since 1.35
	 * @param ResourceLoaderContext $context
	 * @param mixed[] &$config Associative array of configurable options
	 * @return void This hook must not abort, it must return no value
	 */
	public static function onSkinPageReadyConfig(
		ResourceLoaderContext $context,
		array &$config
	) {
		// It's better to exit before any additional check
		if ( !self::isVectorSkin( $context->getSkin() ) ) {
			return;
		}

		// Tell the `mediawiki.page.ready` module not to wire up search.
		// This allows us to use $wgVectorUseWvuiSearch to decide to load
		// the historic jquery autocomplete search or the new Vue implementation.
		// ResourceLoaderContext has no knowledge of legacy / modern Vector
		// and from its point of view they are the same thing.
		// Please see the modules `skins.vector.js` and `skins.vector.legacy.js`
		// for the wire up of search.
		// The related method self::getVectorResourceLoaderConfig handles which
		// search to load.
		$config['search'] = false;
	}

	/**
	 * Transforms watch item inside the action navigation menu
	 *
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
		if ( $key !== null ) {
			self::appendClassToListItem(
				$content_navigation['actions'][$key],
				'icon'
			);
			$content_navigation['views'][$key] = $content_navigation['actions'][$key];
			unset( $content_navigation['actions'][$key] );
		}
	}

	/**
	 * Updates class list on list item
	 *
	 * @param array &$item to update for use in makeListItem
	 * @param array $classes to add to the item
	 * @param bool $applyToLink (optional) and defaults to false.
	 *   If set will modify `link-class` instead of `class`
	 */
	private static function addListItemClass( &$item, $classes, $applyToLink = false ) {
		$property = $applyToLink ? 'link-class' : 'class';
		$existingClass = $item[$property] ?? [];

		if ( is_array( $existingClass ) ) {
			$item[$property] = array_merge( $existingClass, $classes );
		} elseif ( is_string( $existingClass ) ) {
			// treat as string
			$item[$property] = array_merge( [ $existingClass ], $classes );
		} else {
			$item[$property] = $classes;
		}
	}

	/**
	 * Updates the class on an existing item taking into account whether
	 * a class exists there already.
	 *
	 * @param array &$item
	 * @param string $newClass
	 */
	private static function appendClassToListItem( &$item, $newClass ) {
		self::addListItemClass( $item, [ $newClass ] );
	}

	/**
	 * Adds an icon to the list item of a menu.
	 *
	 * @param array &$item
	 * @param string $icon_name
	 */
	private static function addIconToListItem( &$item, $icon_name ) {
		// Set the default menu icon classes.
		$menu_icon_classes = [ 'mw-ui-icon', 'mw-ui-icon-before',
			// Some extensions declare icons without the wikimedia- prefix. e.g. Echo
			'mw-ui-icon-' . $icon_name,
			// FIXME: Some icon names are prefixed with `wikimedia-`.
			// We should seek to remove all these instances.
			'mw-ui-icon-wikimedia-' . $icon_name
		];
		self::addListItemClass( $item, $menu_icon_classes, true );
	}

	/**
	 * Updates personal navigation menu (user links) dropdown for modern Vector:
	 *  - Adds icons
	 *  - Makes user page and watchlist collapsible
	 *
	 * @param SkinTemplate $sk
	 * @param array &$content_navigation
	 * @suppress PhanTypeArraySuspiciousNullable False positives
	 * @suppress PhanTypePossiblyInvalidDimOffset False positives
	 */
	private static function updateUserLinksDropdownItems( $sk, &$content_navigation ) {
		// For logged-in users in modern Vector, rearrange some links in the personal toolbar.
		if ( $sk->getUser()->isRegistered() ) {
			// Remove user page from personal menu dropdown for logged in use
			self::makeMenuItemCollapsible(
				$content_navigation['user-menu']['userpage']
			);
			// watchlist may be disabled if $wgGroupPermissions['*']['viewmywatchlist'] = false;
			// See [[phab:T299671]]
			if ( isset( $content_navigation['user-menu']['watchlist'] ) ) {
				self::makeMenuItemCollapsible(
					$content_navigation['user-menu']['watchlist']
				);
			}
			// Remove logout link from user-menu and recreate it in SkinVector,
			unset( $content_navigation['user-menu']['logout'] );
			// Don't show icons for anon menu items (besides login and create account).
			// Prefix user link items with associated icon.
			$user_menu = $content_navigation['user-menu'];
			// Loop through each menu to check/append its link classes.
			foreach ( $user_menu as $menu_key => $menu_value ) {
				$icon_name = $menu_value['icon'] ?? '';
				self::addIconToListItem( $content_navigation['user-menu'][$menu_key], $icon_name );
			}
		} else {
			// Remove "Not logged in" from personal menu dropdown for anon users.
			unset( $content_navigation['user-menu']['anonuserpage'] );
			// "Create account" link is handled manually by Vector
			unset( $content_navigation['user-menu']['createaccount'] );
			// "Login" link is handled manually by Vector
			unset( $content_navigation['user-menu']['login'] );
			// Remove duplicate "Login" link added by SkinTemplate::buildPersonalUrls if group read permissions
			// are set to false.
			unset( $content_navigation['user-menu']['login-private'] );
		}
	}

	/**
	 * Updates personal navigation menu (user links) overflow items for modern Vector
	 * including 'notification', 'user-interface-preferences', 'user-page', 'vector-user-menu-overflow'
	 *
	 * @param array &$content_navigation
	 */
	private static function updateUserLinksOverflowItems( &$content_navigation ) {
		// Upgrade preferences, notifications, and watchlist to icon buttons
		// for extensions that have opted in.
		if ( isset( $content_navigation['notifications'] ) ) {
			self::updateMenuItems( $content_navigation, 'notifications' );
		}
		if ( isset( $content_navigation['user-interface-preferences']['uls'] ) ) {
			$content_navigation['user-interface-preferences']['uls'] += [
				'collapsible' => true,
			];
			self::updateMenuItems( $content_navigation, 'user-interface-preferences' );
		}
		if ( isset( $content_navigation['user-page']['userpage'] ) ) {
			$content_navigation['user-page']['userpage'] = array_merge( $content_navigation['user-page']['userpage'], [
				'button' => true,
				'collapsible' => true,
				'icon' => null,
			] );
			self::updateMenuItems( $content_navigation, 'user-page' );
		}
		if ( isset( $content_navigation['vector-user-menu-overflow']['watchlist'] ) ) {
			$content_navigation['vector-user-menu-overflow']['watchlist'] += [
				'button' => true,
				'collapsible' => true,
				'text-hidden' => true,
				'id' => 'pt-watchlist-2',
			];
			self::updateMenuItems( $content_navigation, 'vector-user-menu-overflow' );
		}
	}

	/**
	 * Updates personal navigation menu (user links) for modern Vector wherein user page, create account and login links
	 * are removed from the dropdown to be handled separately. In legacy Vector, the custom "user-page" bucket is
	 * removed to preserve existing behavior.
	 *
	 * @param SkinTemplate $sk
	 * @param array &$content_navigation
	 */
	private static function updateUserLinksItems( $sk, &$content_navigation ) {
		$skinName = $sk->getSkinName();
		if ( self::isSkinVersionLegacy( $skinName ) ) {
			// Remove user page from personal toolbar since it will be inside the personal menu for logged-in
			// users in legacy Vector.
			unset( $content_navigation['user-page'] );
		} else {
			if ( isset( $content_navigation['user-menu']['watchlist'] ) ) {
				// Copy watchlist data into 'vector-user-menu-overflow'
				$content_navigation['vector-user-menu-overflow'] = [
					'watchlist' => $content_navigation['user-menu']['watchlist']
				];

				self::updateUserLinksDropdownItems( $sk, $content_navigation );
			}

			self::updateUserLinksOverflowItems( $content_navigation );
		}
	}

	/**
	 * Modifies list item to make it collapsible.
	 *
	 * @param array &$item
	 */
	private static function makeMenuItemCollapsible( array &$item ) {
		$COLLAPSE_MENU_ITEM_CLASS = 'user-links-collapsible-item';
		self::appendClassToListItem(
			$item,
			$COLLAPSE_MENU_ITEM_CLASS
		);
	}

	/**
	 * Make an icon
	 *
	 * @internal for use inside Vector skin.
	 * @param string $name
	 * @return string of HTML
	 */
	public static function makeIcon( $name ) {
		// Html::makeLink will pass this through rawElement
		return '<span class="mw-ui-icon mw-ui-icon-' . $name . '"></span>';
	}

	/**
	 * Updates user interface preferences for modern Vector to upgrade icon/button menu items.
	 *
	 * @param array &$content_navigation
	 * @param string $menu identifier
	 */
	private static function updateMenuItems( &$content_navigation, $menu ) {
		foreach ( $content_navigation[$menu] as $key => $item ) {
			$hasButton = $item['button'] ?? false;
			$hideText = $item['text-hidden'] ?? false;
			$isCollapsible = $item['collapsible'] ?? false;
			$icon = $item['icon'] ?? '';
			unset( $item['button'] );
			unset( $item['icon'] );
			unset( $item['text-hidden'] );
			unset( $item['collapsible'] );
			if ( $isCollapsible ) {
				self::makeMenuItemCollapsible( $item );
			}

			if ( $hasButton ) {
				self::addListItemClass( $item, [ 'mw-ui-button', 'mw-ui-quiet' ], true );
			}

			if ( $icon ) {
				if ( $hideText ) {
					$iconElementClasses = [ 'mw-ui-icon', 'mw-ui-icon-element',
						// Some extensions declare icons without the wikimedia- prefix. e.g. Echo
						'mw-ui-icon-' . $icon,
						// FIXME: Some icon names are prefixed with `wikimedia-`.
						// We should seek to remove all these instances.
						'mw-ui-icon-wikimedia-' . $icon
					];
					self::addListItemClass( $item, $iconElementClasses, true );
				} else {
					$item['link-html'] = self::makeIcon( $icon );
				}
			}
			$content_navigation[$menu][$key] = $item;
		}
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
		$title = $sk->getRelevantTitle();

		$skinName = $sk->getSkinName();
		if ( self::isVectorSkin( $skinName ) ) {
			if (
				$sk->getConfig()->get( 'VectorUseIconWatch' ) &&
				$title && $title->canExist()
			) {
				self::updateActionsMenu( $content_navigation );
			}

			self::updateUserLinksItems( $sk, $content_navigation );
		}
	}

	/**
	 * Add Vector preferences to the user's Special:Preferences page directly underneath skins
	 * provided that $wgVectorSkinMigrationMode is not enabled.
	 *
	 * @param User $user User whose preferences are being modified.
	 * @param array[] &$prefs Preferences description array, to be fed to a HTMLForm object.
	 */
	public static function onGetPreferences( User $user, array &$prefs ) {
		if ( !self::getConfig( Constants::CONFIG_KEY_SHOW_SKIN_PREFERENCES ) ) {
			// Do not add Vector skin specific preferences.
			return;
		}

		// If migration mode was enabled, and the skin version is set to modern,
		// switch over the skin.
		if ( self::isMigrationMode() && !self::isSkinVersionLegacy() ) {
			MediaWikiServices::getInstance()->getUserOptionsManager()->setOption(
				$user,
				Constants::PREF_KEY_SKIN,
				Constants::SKIN_NAME_MODERN
			);
		}

		// Preferences to add.
		$vectorPrefs = [
			Constants::PREF_KEY_SKIN_VERSION => [
				'class' => HTMLLegacySkinVersionField::class,
				// The checkbox title.
				'label-message' => 'prefs-vector-enable-vector-1-label',
				// Show a little informational snippet underneath the checkbox.
				'help-message' => 'prefs-vector-enable-vector-1-help',
				// The tab location and title of the section to insert the checkbox. The bit after the slash
				// indicates that a prefs-skin-prefs string will be provided.
				'section' => 'rendering/skin/skin-prefs',
				'default' => self::isSkinVersionLegacy(),
				// Only show this section when the Vector skin is checked. The JavaScript client also uses
				// this state to determine whether to show or hide the whole section.
				// If migration mode is enabled, the section is always hidden.
				'hide-if' => self::isMigrationMode() ? [ '!==', 'skin', '0' ] :
					[ '!==', 'skin', Constants::SKIN_NAME_LEGACY ],
			],
			Constants::PREF_KEY_SIDEBAR_VISIBLE => [
				'type' => 'api',
				'default' => self::getConfig( Constants::CONFIG_KEY_DEFAULT_SIDEBAR_VISIBLE_FOR_AUTHORISED_USER )
			],
		];

		// Seek the skin preference section to add Vector preferences just below it.
		$skinSectionIndex = array_search(
			Constants::PREF_KEY_SKIN, array_keys( $prefs )
		);
		if ( $skinSectionIndex !== false ) {
			// Skin preference section found. Inject Vector skin-specific preferences just below it.
			// This pattern can be found in Popups too. See T246162.
			$vectorSectionIndex = $skinSectionIndex + 1;
			$prefs = array_slice( $prefs, 0, $vectorSectionIndex, true )
				+ $vectorPrefs
				+ array_slice( $prefs, $vectorSectionIndex, null, true );
		} else {
			// Skin preference section not found. Just append Vector skin-specific preferences.
			$prefs += $vectorPrefs;
		}
	}

	/**
	 * Adds MediaWiki:Vector.css as the skin style that controls classic Vector.
	 *
	 * @param string $skin
	 * @param array &$pages
	 */
	public static function onResourceLoaderSiteStylesModulePages( string $skin, array &$pages ) {
		if ( $skin === Constants::SKIN_NAME_MODERN ) {
			$pages['MediaWiki:Vector.css'] = [ 'type' => 'style' ];
		}
	}

	/**
	 * Adds MediaWiki:Vector.css as the skin style that controls classic Vector.
	 *
	 * @param string $skin
	 * @param array &$pages
	 */
	public static function onResourceLoaderSiteModulePages( string $skin, array &$pages ) {
		if ( $skin === Constants::SKIN_NAME_MODERN ) {
			$pages['MediaWiki:Vector.js'] = [ 'type' => 'script' ];
		}
	}

	/**
	 * Hook executed on user's Special:Preferences form save. This is used to convert the boolean
	 * presentation of skin version to a version string. That is, a single preference change by the
	 * user may trigger two writes: a boolean followed by a string.
	 *
	 * @param array &$formData Form data submitted by user
	 * @param HTMLForm $form A preferences form
	 * @param User $user Logged-in user
	 * @param bool &$result Variable defining is form save successful
	 * @param array $oldPreferences
	 */
	public static function onPreferencesFormPreSave(
		array &$formData,
		HTMLForm $form,
		User $user,
		&$result,
		$oldPreferences
	) {
		$userManager = MediaWikiServices::getInstance()->getUserOptionsManager();
		$skinVersion = $formData[ Constants::PREF_KEY_SKIN_VERSION ] ?? '';
		$skin = $formData[ Constants::PREF_KEY_SKIN ] ?? '';
		$isVectorEnabled = self::isVectorSkin( $skin );

		if (
			self::isMigrationMode() &&
			$skin === Constants::SKIN_NAME_LEGACY &&
			$skinVersion === Constants::SKIN_VERSION_LATEST
		) {
			// Mismatch between skin and version. Use skin.
			$userManager->setOption(
				$user,
				Constants::PREF_KEY_SKIN_VERSION,
				Constants::SKIN_VERSION_LEGACY
			);
		}

		if ( !$isVectorEnabled && array_key_exists( Constants::PREF_KEY_SKIN_VERSION, $oldPreferences ) ) {
			// The setting was cleared. However, this is likely because a different skin was chosen and
			// the skin version preference was hidden.
			$userManager->setOption(
				$user,
				Constants::PREF_KEY_SKIN_VERSION,
				$oldPreferences[ Constants::PREF_KEY_SKIN_VERSION ]
			);
		}
	}

	/**
	 * Check whether we can start migrating users to use skin preference.
	 *
	 * @return bool
	 */
	private static function isMigrationMode(): bool {
		return self::getConfig( 'VectorSkinMigrationMode' );
	}

	/**
	 * Called one time when initializing a users preferences for a newly created account.
	 *
	 * @param User $user Newly created user object.
	 * @param bool $isAutoCreated
	 */
	public static function onLocalUserCreated( User $user, $isAutoCreated ) {
		$default = self::getConfig( Constants::CONFIG_KEY_DEFAULT_SKIN_VERSION_FOR_NEW_ACCOUNTS );
		$optionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
		// Permanently set the default preference. The user can later change this preference, however,
		// self::onLocalUserCreated() will not be executed for that account again.
		$optionsManager->setOption(
			$user,
			Constants::PREF_KEY_SKIN_VERSION,
			$default
		);

		// Also set the skin key if migration mode is enabled.
		if ( self::isMigrationMode() ) {
			$optionsManager->setOption(
				$user,
				Constants::PREF_KEY_SKIN,
				$default === Constants::SKIN_VERSION_LEGACY ?
					Constants::SKIN_NAME_LEGACY : Constants::SKIN_NAME_MODERN
			);
		}
	}

	/**
	 * Called when OutputPage::headElement is creating the body tag to allow skins
	 * and extensions to add attributes they might need to the body of the page.
	 *
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @param string[] &$bodyAttrs
	 */
	public static function onOutputPageBodyAttributes( OutputPage $out, Skin $sk, &$bodyAttrs ) {
		$skinName = $out->getSkin()->getSkinName();
		if ( !self::isVectorSkin( $skinName ) ) {
			return;
		}

		// As of 2020/08/13, this CSS class is referred to by the following deployed extensions:
		//
		// - VisualEditor
		// - CodeMirror
		// - WikimediaEvents
		//
		// See https://codesearch.wmcloud.org/deployed/?q=skin-vector-legacy for an up-to-date
		// list.
		if ( self::isSkinVersionLegacy( $skinName ) ) {
			$bodyAttrs['class'] .= ' skin-vector-legacy';
		}

		$config = $sk->getConfig();
		// Should we disable the max-width styling?
		if ( !self::isSkinVersionLegacy( $skinName ) && $sk->getTitle() && self::shouldDisableMaxWidth(
			$config->get( 'VectorMaxWidthOptions' ),
			$sk->getTitle(),
			$out->getRequest()->getValues()
		) ) {
			$bodyAttrs['class'] .= ' skin-vector-disable-max-width';
		}
	}

	/**
	 * Per the $options configuration (for use with $wgVectorMaxWidthOptions)
	 * determine whether max-width should be disabled on the page.
	 * For the main page: Check the value of $options['exclude']['mainpage']
	 * For all other pages, the following will happen:
	 * - the array $options['include'] of canonical page names will be checked
	 *   against the current page. If a page has been listed there, function will return false
	 *   (max-width will not be  disabled)
	 * Max width is disabled if:
	 *  1) The current namespace is listed in array $options['exclude']['namespaces']
	 *  OR
	 *  2) The query string matches one of the name and value pairs $exclusions['querystring'].
	 *     Note the wildcard "*" for a value, will match all query string values for the given
	 *     query string parameter.
	 *
	 * @internal only for use inside tests.
	 * @param array $options
	 * @param Title $title
	 * @param array $requestValues
	 * @return bool
	 */
	public static function shouldDisableMaxWidth( array $options, Title $title, array $requestValues ) {
		$canonicalTitle = $title->getRootTitle();

		$inclusions = $options['include'] ?? [];
		$exclusions = $options['exclude'] ?? [];

		if ( $title->isMainPage() ) {
			// only one check to make
			return $exclusions['mainpage'] ?? false;
		} elseif ( $canonicalTitle->isSpecialPage() ) {
			$canonicalTitle->fixSpecialName();
		}

		//
		// Check the inclusions based on the canonical title
		// The inclusions are checked first as these trump any exclusions.
		//
		// Now we have the canonical title and the inclusions link we look for any matches.
		foreach ( $inclusions as $titleText ) {
			$includedTitle = Title::newFromText( $titleText );

			if ( $canonicalTitle->equals( $includedTitle ) ) {
				return false;
			}
		}

		//
		// Check the exclusions
		// If nothing matches the exclusions to determine what should happen
		//
		$excludeNamespaces = $exclusions['namespaces'] ?? [];
		// Max width is disabled on certain namespaces
		if ( $title->inNamespaces( $excludeNamespaces ) ) {
			return true;
		}
		$excludeQueryString = $exclusions['querystring'] ?? [];

		foreach ( $excludeQueryString as $param => $excludedParamValue ) {
			$paramValue = $requestValues[$param] ?? false;
			if ( $paramValue ) {
				if ( $excludedParamValue === '*' ) {
					// check wildcard
					return true;
				} elseif ( $paramValue === $excludedParamValue ) {
					// Check if the excluded param value matches
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * NOTE: Please use ResourceLoaderGetConfigVars hook instead if possible
	 * for adding config to the page.
	 * Adds config variables to JS that depend on current page/request.
	 *
	 * Adds a config flag that can disable saving the VectorSidebarVisible
	 * user preference when the sidebar menu icon is clicked.
	 *
	 * @param array &$vars Array of variables to be added into the output.
	 * @param OutputPage $out OutputPage instance calling the hook
	 */
	public static function onMakeGlobalVariablesScript( &$vars, OutputPage $out ) {
		$skin = $out->getSkin();
		$skinName = $skin->getSkinName();
		if ( !self::isVectorSkin( $skinName ) ) {
			return;
		}

		$user = $out->getUser();

		if ( $user->isRegistered() && self::isSkinVersionLegacy( $skinName ) ) {
			$vars[ 'wgVectorDisableSidebarPersistence' ] =
				self::getConfig(
					Constants::CONFIG_KEY_DISABLE_SIDEBAR_PERSISTENCE
				);
		}
	}

	/**
	 * Get a configuration variable such as `Constants::CONFIG_KEY_SHOW_SKIN_PREFERENCES`.
	 *
	 * @param string $name Name of configuration option.
	 * @return mixed Value configured.
	 * @throws \ConfigException
	 */
	private static function getConfig( $name ) {
		return self::getServiceConfig()->get( $name );
	}

	/**
	 * @return \Config
	 */
	private static function getServiceConfig() {
		return MediaWikiServices::getInstance()->getService( Constants::SERVICE_CONFIG );
	}

	/**
	 * Gets whether the current skin version is the legacy version.
	 * Should mirror SkinVector::isLegacy
	 *
	 * @see VectorServices::getFeatureManager
	 *
	 * @param string $skinName hint that can be used to detect modern vector.
	 * @return bool
	 */
	private static function isSkinVersionLegacy( $skinName = '' ): bool {
		if ( $skinName === Constants::SKIN_NAME_MODERN ) {
			return false;
		}

		$isLatestSkinFeatureEnabled = VectorServices::getFeatureManager()
			->isFeatureEnabled( Constants::FEATURE_LATEST_SKIN );

		return !$isLatestSkinFeatureEnabled;
	}
}
