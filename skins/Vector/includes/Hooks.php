<?php

namespace Vector;

use Config;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use OutputPage;
use ResourceLoaderContext;
use Skin;
use SkinTemplate;
use SkinVector;
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
		if ( $context->getSkin() !== 'vector' ) {
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
		$menu_icon_classes = [ 'mw-ui-icon', 'mw-ui-icon-before', 'mw-ui-icon-wikimedia-' . $icon_name ];
		self::addListItemClass( $item, $menu_icon_classes, true );
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
		$COLLAPSE_MENU_ITEM_CLASS = 'user-links-collapsible-item';

		// For logged-in users in modern Vector, rearrange some links in the personal toolbar.
		if ( $sk->loggedin ) {
			// Remove user page from personal menu dropdown for logged in users at higher resolutions.
			self::appendClassToListItem(
				$content_navigation['user-menu']['userpage'],
				$COLLAPSE_MENU_ITEM_CLASS
			);
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

		// ULS and user page links are hidden at lower resolutions.
		if ( $content_navigation['user-interface-preferences'] ) {
			self::appendClassToListItem(
				$content_navigation['user-interface-preferences']['uls'],
				$COLLAPSE_MENU_ITEM_CLASS
			);
		}
		if ( $content_navigation['user-page'] ) {
			self::appendClassToListItem(
				$content_navigation['user-page']['userpage'],
				$COLLAPSE_MENU_ITEM_CLASS
			);

			// Style the user page link as mw-ui-button.
			self::addListItemClass(
				$content_navigation['user-page']['userpage'],
				[ 'mw-ui-button',  'mw-ui-quiet' ],
				true
			);
		}
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
	 * @param SkinTemplate $sk
	 * @param array &$content_navigation
	 * @param string $menu identifier
	 */
	private static function updateMenuItems( $sk, &$content_navigation, $menu ) {
		foreach ( $content_navigation[$menu] as $key => $item ) {
			$hasButton = $item['button'] ?? false;
			$hideText = $item['text-hidden'] ?? false;
			$icon = $item['icon'] ?? '';
			unset( $item['button'] );
			unset( $item['icon'] );
			unset( $item['text-hidden'] );

			if ( $hasButton ) {
				$item['link-class'][] = 'mw-ui-button mw-ui-quiet';
			}

			if ( $icon ) {
				if ( $hideText ) {
					$item['link-class'][] = 'mw-ui-icon mw-ui-icon-element mw-ui-icon-' . $icon;
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

		if ( $sk->getSkinName() === 'vector' ) {
			if (
				$sk->getConfig()->get( 'VectorUseIconWatch' ) &&
				$title && $title->canExist()
			) {
				self::updateActionsMenu( $content_navigation );
			}

			if ( isset( $content_navigation['user-menu'] ) ) {
				if ( self::isSkinVersionLegacy() ) {
					// Remove user page from personal toolbar since it will be inside the personal menu for logged-in
					// users in legacy Vector.
					unset( $content_navigation['user-page'] );
				} else {
					// For modern Vector, rearrange some links in the personal toolbar.
					self::updateUserLinksItems( $sk, $content_navigation );
				}
			}

			if ( !self::isSkinVersionLegacy() ) {
				// Upgrade preferences and notifications to icon buttons
				// for extensions that have opted in.
				if ( isset( $content_navigation['user-interface-preferences'] ) ) {
					self::updateMenuItems(
						$sk, $content_navigation, 'user-interface-preferences'
					);
				}
				if ( isset( $content_navigation['notifications'] ) ) {
					self::updateMenuItems(
						$sk, $content_navigation, 'notifications'
					);
				}
			}
		}
	}

	/**
	 * Add Vector preferences to the user's Special:Preferences page directly underneath skins.
	 *
	 * @param User $user User whose preferences are being modified.
	 * @param array[] &$prefs Preferences description array, to be fed to a HTMLForm object.
	 */
	public static function onGetPreferences( User $user, array &$prefs ) {
		if ( !self::getConfig( Constants::CONFIG_KEY_SHOW_SKIN_PREFERENCES ) ) {
			// Do not add Vector skin specific preferences.
			return;
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
				'hide-if' => [ '!==', 'wpskin', Constants::SKIN_NAME ],
			],
			Constants::PREF_KEY_SIDEBAR_VISIBLE => [
				'type' => 'api',
				'default' => self::getConfig( Constants::CONFIG_KEY_DEFAULT_SIDEBAR_VISIBLE_FOR_AUTHORISED_USER )
			],
		];

		// Seek the skin preference section to add Vector preferences just below it.
		$skinSectionIndex = array_search( 'skin', array_keys( $prefs ) );
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
	 * Hook executed on user's Special:Preferences form save. This is used to convert the boolean
	 * presentation of skin version to a version string. That is, a single preference change by the
	 * user may trigger two writes: a boolean followed by a string.
	 *
	 * @param array $formData Form data submitted by user
	 * @param HTMLForm $form A preferences form
	 * @param User $user Logged-in user
	 * @param bool &$result Variable defining is form save successful
	 * @param array $oldPreferences
	 */
	public static function onPreferencesFormPreSave(
		array $formData,
		HTMLForm $form,
		User $user,
		&$result,
		$oldPreferences
	) {
		$isVectorEnabled = ( $formData[ 'skin' ] ?? '' ) === Constants::SKIN_NAME;

		if ( !$isVectorEnabled && array_key_exists( Constants::PREF_KEY_SKIN_VERSION, $oldPreferences ) ) {
			// The setting was cleared. However, this is likely because a different skin was chosen and
			// the skin version preference was hidden.
			MediaWikiServices::getInstance()->getUserOptionsManager()->setOption(
				$user,
				Constants::PREF_KEY_SKIN_VERSION,
				$oldPreferences[ Constants::PREF_KEY_SKIN_VERSION ]
			);
		}
	}

	/**
	 * Called one time when initializing a users preferences for a newly created account.
	 *
	 * @param User $user Newly created user object.
	 * @param bool $isAutoCreated
	 */
	public static function onLocalUserCreated( User $user, $isAutoCreated ) {
		$default = self::getConfig( Constants::CONFIG_KEY_DEFAULT_SKIN_VERSION_FOR_NEW_ACCOUNTS );
		// Permanently set the default preference. The user can later change this preference, however,
		// self::onLocalUserCreated() will not be executed for that account again.
		MediaWikiServices::getInstance()->getUserOptionsManager()->setOption(
			$user,
			Constants::PREF_KEY_SKIN_VERSION,
			$default
		);
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
		if ( !$sk instanceof SkinVector ) {
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
		if ( self::isSkinVersionLegacy() ) {
			$bodyAttrs['class'] .= ' skin-vector-legacy';
		}

		// Determine the search widget treatment to send to the user
		if ( VectorServices::getFeatureManager()->isFeatureEnabled( Constants::FEATURE_USE_WVUI_SEARCH ) ) {
			$bodyAttrs['class'] .= ' skin-vector-search-vue';
		}

		$config = $sk->getConfig();
		// Should we disable the max-width styling?
		if ( !self::isSkinVersionLegacy() && $sk->getTitle() && self::shouldDisableMaxWidth(
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
		if ( !$out->getSkin() instanceof SkinVector ) {
			return;
		}

		$user = $out->getUser();

		if ( $user->isRegistered() && self::isSkinVersionLegacy() ) {
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
	 *
	 * @see VectorServices::getFeatureManager
	 *
	 * @return bool
	 */
	private static function isSkinVersionLegacy(): bool {
		return !VectorServices::getFeatureManager()->isFeatureEnabled( Constants::FEATURE_LATEST_SKIN );
	}
}
