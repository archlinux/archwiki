<?php
/**
 * VisualEditor extension hooks
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

use MediaWiki\Extension\VisualEditor\VisualEditorHookRunner;
use MediaWiki\MediaWikiServices;

class VisualEditorHooks {

	// Known parameters that VE does not handle
	// TODO: Other params too?
	// Known-good parameters: edit, veaction, section, oldid, lintid, preload, preloadparams, editintro
	// Partially-good: preloadtitle (source-mode only)
	private const UNSUPPORTED_EDIT_PARAMS = [
		'undo',
		'undoafter',
		// Only for WTE. This parameter is not supported right now, and NWE has a very different design
		// for previews, so we might not want to support this at all.
		'preview',
		'veswitched'
	];

	private const TAGS = [
		'visualeditor',
		'visualeditor-wikitext',
		// No longer in active use:
		'visualeditor-needcheck',
		'visualeditor-switched'
	];

	/**
	 * Initialise the 'VisualEditorAvailableNamespaces' setting, and add content
	 * namespaces to it. This will run after LocalSettings.php is processed.
	 * Also ensure Parsoid extension is loaded when necessary.
	 */
	public static function onRegistration() {
		global $wgVisualEditorAvailableNamespaces, $wgContentNamespaces,
			$wgVisualEditorParsoidAutoConfig, $wgVirtualRestConfig, $wgRestAPIAdditionalRouteFiles;
		// phpcs:ignore MediaWiki.NamingConventions.ValidGlobalName.allowedPrefix
		global $IP;

		foreach ( $wgContentNamespaces as $contentNamespace ) {
			if ( !isset( $wgVisualEditorAvailableNamespaces[$contentNamespace] ) ) {
				$wgVisualEditorAvailableNamespaces[$contentNamespace] = true;
			}
		}

		// For the 1.35 LTS, Parsoid is loaded from includes/VEParsoid to provide a
		// "zero configuration" VisualEditor experience. In Wikimedia production, we
		// have $wgVisualEditorParsoidAutoConfig off (and $wgVirtualRestConfig set to
		// point to a separate cluster which has the Parsoid extension installed), so
		// this code won't be executed.
		if (
			$wgVisualEditorParsoidAutoConfig
			&& !ExtensionRegistry::getInstance()->isLoaded( 'Parsoid' )
			// Generally manually configuring VRS means that you're running
			// Parsoid on a different host.  If you need to manually configure
			// VRS, load the Parsoid extension explicitly.
			&& !isset( $wgVirtualRestConfig['modules']['parsoid'] )
			&& !isset( $wgVirtualRestConfig['modules']['restbase'] )
		) {
			// Only install these route files if we're auto-configuring and
			// the parsoid extension isn't loaded, otherwise we'll conflict.
			$wgRestAPIAdditionalRouteFiles[] = wfRelativePath(
				__DIR__ . '/VEParsoid/parsoidRoutes.json', $IP
			);
		}
	}

	/**
	 * Adds VisualEditor JS to the output.
	 *
	 * This is attached to the MediaWiki 'BeforePageDisplay' hook.
	 *
	 * @param OutputPage $output The page view.
	 * @param Skin $skin The skin that's going to build the UI.
	 */
	public static function onBeforePageDisplay( OutputPage $output, Skin $skin ) {
		$services = MediaWikiServices::getInstance();
		$hookRunner = new VisualEditorHookRunner( $services->getHookContainer() );
		if ( !$hookRunner->onVisualEditorBeforeEditor( $output, $skin ) ) {
			return;
		}
		if ( !(
			ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			$services->getService( 'MobileFrontend.Context' )
				->shouldDisplayMobileView()
		) ) {
			$output->addModules( [
				'ext.visualEditor.desktopArticleTarget.init',
				'ext.visualEditor.targetLoader'
			] );
			$output->addModuleStyles( [ 'ext.visualEditor.desktopArticleTarget.noscript' ] );
		}
		// add scroll offset js variable to output
		$veConfig = $services->getConfigFactory()->makeConfig( 'visualeditor' );
		$skinsToolbarScrollOffset = $veConfig->get( 'VisualEditorSkinToolbarScrollOffset' );
		$toolbarScrollOffset = 0;
		$skinName = $skin->getSkinName();
		if ( isset( $skinsToolbarScrollOffset[$skinName] ) ) {
			$toolbarScrollOffset = $skinsToolbarScrollOffset[$skinName];
		}
		// T220158: Don't add this unless it's non-default
		// TODO: Move this to packageFiles as it's not relevant to the HTML request.
		if ( $toolbarScrollOffset !== 0 ) {
			$output->addJsConfigVars( 'wgVisualEditorToolbarScrollOffset', $toolbarScrollOffset );
		}

		$output->addJsConfigVars(
			'wgEditSubmitButtonLabelPublish',
			$veConfig->get( 'EditSubmitButtonLabelPublish' )
		);
	}

	/**
	 * @internal For internal use in extension.json only.
	 * @return array
	 */
	public static function getDataForDesktopArticleTargetInitModule() {
		return [
			'unsupportedEditParams' => self::UNSUPPORTED_EDIT_PARAMS,
		];
	}

	/**
	 * Handler for the DifferenceEngineViewHeader hook, to add visual diffs code as configured
	 *
	 * @param DifferenceEngine $diff The difference engine
	 * @return void
	 */
	public static function onDifferenceEngineViewHeader( DifferenceEngine $diff ) {
		$services = MediaWikiServices::getInstance();
		$veConfig = $services->getConfigFactory()
			->makeConfig( 'visualeditor' );
		$userOptionsLookup = $services->getUserOptionsLookup();
		$output = RequestContext::getMain()->getOutput();
		$user = RequestContext::getMain()->getUser();

		if ( !(
			// Enabled globally on wiki
			$veConfig->get( 'VisualEditorEnableDiffPage' ) ||
			// Enabled as user beta feature
			$userOptionsLookup->getOption( $user, 'visualeditor-visualdiffpage' ) ||
			// Enabled by query param (deprecated)
			$output->getRequest()->getVal( 'visualdiff' ) !== null ||
			// Enabled by query param
			$output->getRequest()->getVal( 'diffmode' ) === 'visual'
		) ) {
			return;
		}

		if ( !ApiVisualEditor::isAllowedContentType( $veConfig, $diff->getTitle()->getContentModel() ) ) {
			return;
		}

		$output->addModuleStyles( [
			'ext.visualEditor.diffPage.init.styles',
			'oojs-ui.styles.icons-accessibility',
			'oojs-ui.styles.icons-editing-advanced'
		] );
		$output->addModules( 'ext.visualEditor.diffPage.init' );
		$output->enableOOUI();
		$output->addHTML(
			'<div class="ve-init-mw-diffPage-diffMode">' .
			// Will be replaced by a ButtonSelectWidget in JS
			new OOUI\ButtonGroupWidget( [
				'items' => [
					new \OOUI\ButtonWidget( [
						'data' => 'visual',
						'icon' => 'eye',
						'disabled' => true,
						'label' => $output->msg( 'visualeditor-savedialog-review-visual' )->plain()
					] ),
					new \OOUI\ButtonWidget( [
						'data' => 'source',
						'icon' => 'wikiText',
						'active' => true,
						'label' => $output->msg( 'visualeditor-savedialog-review-wikitext' )->plain()
					] )
				]
			] ) .
			'</div>'
		);
	}

	/**
	 * Detect incompatibile browsers which we can't expect to load VE
	 *
	 * @param WebRequest $req The web request to check the details of
	 * @param Config $config VE config object
	 * @return bool The User Agent is unsupported
	 */
	private static function isUAUnsupported( WebRequest $req, $config ) {
		if ( $req->getVal( 'vesupported' ) ) {
			return false;
		}
		$unsupportedList = $config->get( 'VisualEditorBrowserUnsupportedList' );
		$ua = strtolower( $req->getHeader( 'User-Agent' ) );
		foreach ( $unsupportedList as $uaSubstr => $rules ) {
			if ( !strpos( $ua, $uaSubstr . '/' ) ) {
				continue;
			}
			if ( !is_array( $rules ) ) {
				return true;
			}

			$matches = [];
			$ret = preg_match( '/' . $uaSubstr . '\/([0-9\.]*) ?/i', $ua, $matches );
			if ( $ret !== 1 ) {
				continue;
			}
			$version = $matches[1];
			foreach ( $rules as $rule ) {
				list( $op, $matchVersion ) = $rule;
				if (
					( $op === '<' && $version < $matchVersion ) ||
					( $op === '>' && $version > $matchVersion ) ||
					( $op === '<=' && $version <= $matchVersion ) ||
					( $op === '>=' && $version >= $matchVersion )
				) {
					return true;
				}
			}

		}
		return false;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param WebRequest $req
	 * @return bool
	 */
	private static function isSupportedEditPage( Title $title, User $user, WebRequest $req ) {
		if (
			$req->getVal( 'action' ) !== 'edit' ||
			!MediaWikiServices::getInstance()->getPermissionManager()->quickUserCan( 'edit', $user, $title )
		) {
			return false;
		}

		foreach ( self::UNSUPPORTED_EDIT_PARAMS as $param ) {
			if ( $req->getVal( $param ) !== null ) {
				return false;
			}
		}

		if ( $req->getVal( 'wteswitched' ) ) {
			return self::isVisualAvailable( $title, $req, $user );
		}

		switch ( self::getEditPageEditor( $user, $req ) ) {
			case 'visualeditor':
				return self::isVisualAvailable( $title, $req, $user ) ||
					self::isWikitextAvailable( $title, $user );
			case 'wikitext':
			default:
				return self::isWikitextAvailable( $title, $user );
		}
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	private static function enabledForUser( $user ) {
		$services = MediaWikiServices::getInstance();
		$veConfig = $services->getConfigFactory()
			->makeConfig( 'visualeditor' );
		$userOptionsLookup = $services->getUserOptionsLookup();
		$isBeta = $veConfig->get( 'VisualEditorEnableBetaFeature' );

		return ( $isBeta ?
			$userOptionsLookup->getOption( $user, 'visualeditor-enable' ) :
			!$userOptionsLookup->getOption( $user, 'visualeditor-betatempdisable' ) ) &&
			!$userOptionsLookup->getOption( $user, 'visualeditor-autodisable' );
	}

	/**
	 * @param Title $title
	 * @param WebRequest $req
	 * @param User $user
	 * @return bool
	 */
	private static function isVisualAvailable( $title, $req, $user ) {
		$veConfig = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'visualeditor' );

		return (
			// If forced by the URL parameter, skip the namespace check (T221892) and preference check
			$req->getVal( 'veaction' ) === 'edit' || (
				// Only in enabled namespaces
				ApiVisualEditor::isAllowedNamespace( $veConfig, $title->getNamespace() ) &&

				// Enabled per user preferences
				self::enabledForUser( $user )
			) &&
			// Only for pages with a supported content model
			ApiVisualEditor::isAllowedContentType( $veConfig, $title->getContentModel() )
		);
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @return bool
	 */
	private static function isWikitextAvailable( $title, $user ) {
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		return $userOptionsLookup->getOption( $user, 'visualeditor-newwikitext' ) &&
			$title->getContentModel() === 'wikitext';
	}

	/**
	 * Decide whether to bother showing the wikitext editor at all.
	 * If not, we expect the VE initialisation JS to activate.
	 *
	 * @param Article $article The article being viewed.
	 * @param User $user The user-specific settings.
	 * @return bool Whether to show the wikitext editor or not.
	 */
	public static function onCustomEditor( Article $article, User $user ) {
		$req = $article->getContext()->getRequest();
		$services = MediaWikiServices::getInstance();
		$veConfig = $services->getConfigFactory()->makeConfig( 'visualeditor' );

		if (
			!self::enabledForUser( $user ) ||
			self::isUAUnsupported( $req, $veConfig )
		) {
			return true;
		}

		$title = $article->getTitle();

		if ( $req->getVal( 'venoscript' ) ) {
			$req->response()->setCookie( 'VEE', 'wikitext', 0, [ 'prefix' => '' ] );
			$services->getUserOptionsManager()->setOption( $user, 'visualeditor-editor', 'wikitext' );
			if ( !$services->getReadOnlyMode()->isReadOnly() && $user->isRegistered() ) {
				DeferredUpdates::addCallableUpdate( static function () use ( $user ) {
					$user->saveSettings();
				} );
			}
			return true;
		}

		if ( self::isSupportedEditPage( $title, $user, $req ) ) {
			$params = $req->getValues();
			$params['venoscript'] = '1';
			$url = wfScript() . '?' . wfArrayToCgi( $params );
			$escapedUrl = htmlspecialchars( $url );

			$out = $article->getContext()->getOutput();
			$titleMsg = $title->exists() ? 'editing' : 'creating';
			$out->setPageTitle( wfMessage( $titleMsg, $title->getPrefixedText() ) );
			$out->addWikiMsg( 'visualeditor-toload', wfExpandUrl( $url ) );

			// Redirect if the user has no JS (<noscript>)
			$out->addHeadItem(
				've-noscript-fallback',
				"<noscript><meta http-equiv=\"refresh\" content=\"0; url=$escapedUrl\"></noscript>"
			);
			// Redirect if the user has no ResourceLoader
			$out->addScript( Html::inlineScript(
				"(window.NORLQ=window.NORLQ||[]).push(" .
					"function(){" .
						"location.href=\"$url\";" .
					"}" .
				");"
			) );
			$out->setRevisionId( $req->getInt( 'oldid', $article->getRevIdFetched() ) );
			return false;
		}
		return true;
	}

	/**
	 * @param User $user
	 * @param WebRequest $req
	 * @return string 'wikitext' or 'visual'
	 */
	private static function getEditPageEditor( User $user, WebRequest $req ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'visualeditor' );
		if ( $config->get( 'VisualEditorDisableForAnons' ) && !$user->isRegistered() ) {
			return 'wikitext';
		}
		$isRedLink = $req->getBool( 'redlink' );
		// On dual-edit-tab wikis, the edit page must mean the user wants wikitext,
		// unless following a redlink
		if ( !$config->get( 'VisualEditorUseSingleEditTab' ) && !$isRedLink ) {
			return 'wikitext';
		}
		return self::getPreferredEditor( $user, $req, !$isRedLink );
	}

	/**
	 * @param User $user
	 * @param WebRequest $req
	 * @param bool $useWikitextInMultiTab
	 * @return string 'wikitext' or 'visual'
	 */
	public static function getPreferredEditor(
		User $user, WebRequest $req, $useWikitextInMultiTab = false
	) {
		// VisualEditor shouldn't even call this method when it's disabled, but it is a public API for
		// other extensions (e.g. DiscussionTools), and the editor preferences might have surprising
		// values if the user has tried VisualEditor in the past and then disabled it. (T257234)
		if ( !self::enabledForUser( $user ) ) {
			return 'wikitext';
		}

		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();

		switch ( $userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) ) {
			case 'prefer-ve':
				return 'visualeditor';
			case 'prefer-wt':
				return 'wikitext';
			case 'multi-tab':
				// May have got here by switching from VE
				// TODO: Make such an action explicitly request wikitext
				// so we can use getLastEditor here instead.
				return $useWikitextInMultiTab ?
					'wikitext' :
					self::getLastEditor( $user, $req );
			case 'remember-last':
			default:
				return self::getLastEditor( $user, $req );
		}
	}

	/**
	 * @param User $user
	 * @param WebRequest $req
	 * @return string
	 */
	private static function getLastEditor( User $user, WebRequest $req ) {
		// This logic matches getLastEditor in:
		// modules/ve-mw/init/targets/ve.init.mw.DesktopArticleTarget.init.js
		$editor = $req->getCookie( 'VEE', '' );
		// Set editor to user's preference or site's default if …
		if (
			// … user is logged in,
			$user->isRegistered() ||
			// … no cookie is set, or
			!$editor ||
			// value is invalid.
			!( $editor === 'visualeditor' || $editor === 'wikitext' )
		) {
			$services = MediaWikiServices::getInstance();
			$userOptionsLookup = $services->getUserOptionsLookup();
			$editor = $userOptionsLookup->getOption( $user, 'visualeditor-editor' );
		}
		return $editor;
	}

	/**
	 * Changes the Edit tab and adds the VisualEditor tab.
	 *
	 * This is attached to the MediaWiki 'SkinTemplateNavigation' hook.
	 *
	 * @param SkinTemplate $skin The skin template on which the UI is built.
	 * @param array &$links Navigation links.
	 */
	public static function onSkinTemplateNavigation( SkinTemplate $skin, array &$links ) {
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$config = $services->getConfigFactory()
			->makeConfig( 'visualeditor' );

		// Exit if there's no edit link for whatever reason (e.g. protected page)
		if ( !isset( $links['views']['edit'] ) ) {
			return;
		}

		$hookRunner = new VisualEditorHookRunner( $services->getHookContainer() );
		if ( !$hookRunner->onVisualEditorBeforeEditor( $skin->getOutput(), $skin ) ) {
			return;
		}

		$user = $skin->getUser();
		if (
			$config->get( 'VisualEditorUseSingleEditTab' ) &&
			$userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) === 'prefer-wt'
		) {
			return;
		}

		if (
			$config->get( 'VisualEditorUseSingleEditTab' ) &&
			wfTimestampNow() < $config->get( 'VisualEditorSingleEditTabSwitchTimeEnd' ) &&
			$user->isRegistered() &&
			self::enabledForUser( $user ) &&
			!$userOptionsLookup->getOption( $user, 'visualeditor-hidetabdialog' ) &&
			$userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) === 'remember-last'
		) {
			$dbr = wfGetDB( DB_REPLICA );
			$revWhere = ActorMigration::newMigration()->getWhere( $dbr, 'rev_user', $user );
			foreach ( $revWhere['orconds'] as $key => $cond ) {
				$tsField = $key === 'actor' ? 'revactor_timestamp' : 'rev_timestamp';
				if (
					$dbr->select(
						[ 'revision' ] + $revWhere['tables'],
						'1',
						[
							$cond,
							$tsField . ' < ' . $dbr->addQuotes( $dbr->timestamp(
								$config->get( 'VisualEditorSingleEditTabSwitchTime' )
							) )
						],
						__METHOD__,
						[ 'LIMIT' => 1 ],
						$revWhere['joins']
					)->numRows() === 1
				) {
					$links['views']['edit']['class'] .= ' visualeditor-showtabdialog';
					break;
				}
			}
		}

		// Exit if the user doesn't have VE enabled
		if (
			!self::enabledForUser( $user ) ||
			// T253941: This option does not actually disable the editor, only leaves the tabs/links unchanged
			( $config->get( 'VisualEditorDisableForAnons' ) && !$user->isRegistered() )
		) {
			return;
		}

		$title = $skin->getRelevantTitle();
		// Don't exit if this page isn't VE-enabled, since we should still
		// change "Edit" to "Edit source".
		$isAvailable = self::isVisualAvailable( $title, $skin->getRequest(), $user );

		$tabMessages = $config->get( 'VisualEditorTabMessages' );
		// Rebuild the $links['views'] array and inject the VisualEditor tab before or after
		// the edit tab as appropriate. We have to rebuild the array because PHP doesn't allow
		// us to splice into the middle of an associative array.
		$newViews = [];
		foreach ( $links['views'] as $action => $data ) {
			if ( $action === 'edit' ) {
				// Build the VisualEditor tab
				$existing = $title->exists() || (
					$title->inNamespace( NS_MEDIAWIKI ) &&
					$title->getDefaultMessageText() !== false
				);
				$action = $existing ? 'edit' : 'create';
				$veParams = $skin->editUrlOptions();
				// Remove action=edit
				unset( $veParams['action'] );
				// Set veaction=edit
				$veParams['veaction'] = 'edit';
				$veTabMessage = $tabMessages[$action];
				// @phan-suppress-next-line PhanTypeInvalidDimOffset
				$veTabText = $veTabMessage === null ? $data['text'] :
					$skin->msg( $veTabMessage )->text();
				$veTab = [
					'href' => $title->getLocalURL( $veParams ),
					'text' => $veTabText,
					'primary' => true,
					'class' => '',
				];

				// Alter the edit tab
				$editTab = $data;
				if (
					$title->inNamespace( NS_FILE ) &&
					WikiPage::factory( $title ) instanceof WikiFilePage &&
					!WikiPage::factory( $title )->isLocal()
				) {
					$editTabMessage = $tabMessages[$action . 'localdescriptionsource'];
				} else {
					$editTabMessage = $tabMessages[$action . 'source'];
				}

				if ( $editTabMessage !== null ) {
					$editTab['text'] = $skin->msg( $editTabMessage )->text();
				}

				$editor = self::getLastEditor( $user, $skin->getRequest() );
				if (
					$isAvailable &&
					$config->get( 'VisualEditorUseSingleEditTab' ) &&
					(
						$userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) === 'prefer-ve' ||
						(
							$userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) === 'remember-last' &&
							$editor === 'visualeditor'
						)
					)
				) {
					$editTab['text'] = $veTabText;
					$newViews['edit'] = $editTab;
				} elseif (
					$isAvailable &&
					(
						!$config->get( 'VisualEditorUseSingleEditTab' ) ||
						$userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) === 'multi-tab'
					)
				) {
					// Inject the VE tab before or after the edit tab
					if ( $config->get( 'VisualEditorTabPosition' ) === 'before' ) {
						$editTab['class'] .= ' collapsible';
						$newViews['ve-edit'] = $veTab;
						$newViews['edit'] = $editTab;
					} else {
						$veTab['class'] .= ' collapsible';
						$newViews['edit'] = $editTab;
						$newViews['ve-edit'] = $veTab;
					}
				} elseif (
					!$config->get( 'VisualEditorUseSingleEditTab' ) ||
					!$isAvailable ||
					$userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) === 'multi-tab' ||
					(
						$userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) === 'remember-last' &&
						$editor === 'wikitext'
					)
				) {
					// Don't add ve-edit, but do update the edit tab (e.g. "Edit source").
					$newViews['edit'] = $editTab;
				} else {
					// This should not happen.
				}
			} else {
				// Just pass through
				$newViews[$action] = $data;
			}
		}
		$links['views'] = $newViews;
	}

	/**
	 * Called when the normal wikitext editor is shown.
	 * Inserts a 'veswitched' hidden field if requested by the client
	 *
	 * @param EditPage $editPage The edit page view.
	 * @param OutputPage $output The page view.
	 */
	public static function onEditPageShowEditFormFields( EditPage $editPage, OutputPage $output ) {
		$request = $output->getRequest();
		if ( $request->getBool( 'veswitched' ) ) {
			$output->addHTML( Html::hidden( 'veswitched', '1' ) );
		}
	}

	/**
	 * Called when an edit is saved
	 * Adds 'visualeditor-switched' tag to the edit if requested
	 * Adds whatever tags from static::TAGS are present in the vetags parameter
	 *
	 * @param RecentChange $rc The new RC entry.
	 */
	public static function onRecentChangeSave( RecentChange $rc ) {
		$request = RequestContext::getMain()->getRequest();
		if ( $request->getBool( 'veswitched' ) && $rc->getAttribute( 'rc_this_oldid' ) ) {
			$rc->addTags( 'visualeditor-switched' );
		}

		$tags = explode( ',', $request->getVal( 'vetags' ) ?? '' );
		$tags = array_values( array_intersect( $tags, static::TAGS ) );
		if ( $tags ) {
			$rc->addTags( $tags );
		}
	}

	/**
	 * Changes the section edit links to add a VE edit link.
	 *
	 * This is attached to the MediaWiki 'SkinEditSectionLinks' hook.
	 *
	 * @param Skin $skin Skin being used to render the UI
	 * @param Title $title Title being used for request
	 * @param string $section The name of the section being pointed to.
	 * @param string $tooltip The default tooltip.
	 * @param array &$result All link detail arrays.
	 * @phan-param array{editsection:array{text:string,targetTitle:Title,attribs:array,query:array}} $result
	 * @param Language $lang The user interface language.
	 */
	public static function onSkinEditSectionLinks( Skin $skin, Title $title, $section,
		$tooltip, &$result, $lang
	) {
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$config = $services->getConfigFactory()
			->makeConfig( 'visualeditor' );

		// Exit if we're in parserTests
		if ( isset( $GLOBALS[ 'wgVisualEditorInParserTests' ] ) ) {
			return;
		}

		$user = $skin->getUser();
		// Exit if the user doesn't have VE enabled
		if (
			!self::enabledForUser( $user ) ||
			// T253941: This option does not actually disable the editor, only leaves the tabs/links unchanged
			( $config->get( 'VisualEditorDisableForAnons' ) && !$user->isRegistered() )
		) {
			return;
		}

		// Exit if we're on a foreign file description page
		if (
			$title->inNamespace( NS_FILE ) &&
			WikiPage::factory( $title ) instanceof WikiFilePage &&
			!WikiPage::factory( $title )->isLocal()
		) {
			return;
		}

		$editor = self::getLastEditor( $user, $skin->getRequest() );
		if (
			!$config->get( 'VisualEditorUseSingleEditTab' ) ||
			$userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) === 'multi-tab' ||
			(
				$userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) === 'remember-last' &&
				$editor === 'wikitext'
			)
		) {
			// Don't add ve-edit, but do update the edit tab (e.g. "Edit source").
			$tabMessages = $config->get( 'VisualEditorTabMessages' );
			$sourceEditSection = $tabMessages['editsectionsource'];
			$result['editsection']['text'] = $skin->msg( $sourceEditSection )->inLanguage( $lang )->text();
		}

		// Exit if we're using the single edit tab.
		if (
			$config->get( 'VisualEditorUseSingleEditTab' ) &&
			$userOptionsLookup->getOption( $user, 'visualeditor-tabs' ) !== 'multi-tab'
		) {
			return;
		}

		// add VE edit section in VE available namespaces
		if ( self::isVisualAvailable( $title, $skin->getRequest(), $user ) ) {
			$veEditSection = $tabMessages['editsection'];

			$attribs = $result['editsection']['attribs'];
			$attribs['class'] = ( $attribs['class'] ?? '' ) . ' mw-editsection-visualeditor';

			$veLink = [
				'text' => $skin->msg( $veEditSection )->inLanguage( $lang )->text(),
				'targetTitle' => $title,
				'attribs' => $attribs,
				'query' => [ 'veaction' => 'edit', 'section' => $section ],
				'options' => [ 'noclasses', 'known' ]
			];

			$result['veeditsection'] = $veLink;
			if ( $config->get( 'VisualEditorTabPosition' ) === 'before' ) {
				krsort( $result );
				// TODO: This will probably cause weird ordering if any other extensions added something
				// already.
				// ... wfArrayInsertBefore?
			}
		}
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @param string[] &$bodyAttrs
	 */
	public static function onOutputPageBodyAttributes( OutputPage $out, Skin $sk, &$bodyAttrs ) {
		$specialTitle = $sk->getTitle();

		// HACK: Replace classes generated by Skin::getPageClasses as if an article title
		// was passed in, instead of a special page.
		if ( $specialTitle && $specialTitle->isSpecial( 'CollabPad' ) ) {
			$articleTitle = Title::newFromText( 'DummyPage' );

			$specialClasses = $sk->getPageClasses( $specialTitle );
			$articleClasses = $sk->getPageClasses( $articleTitle );

			$bodyAttrs['class'] = str_replace( $specialClasses, $articleClasses, $bodyAttrs['class'] );
		}
	}

	/**
	 * Convert a namespace index to the local text for display to the user.
	 *
	 * @param int $nsIndex
	 * @return string
	 */
	private static function convertNs( $nsIndex ) {
		global $wgLang;
		if ( $nsIndex ) {
			return MediaWikiServices::getInstance()->getLanguageConverterFactory()
				->getLanguageConverter( $wgLang )
				->convertNamespace( $nsIndex );
		} else {
			return wfMessage( 'blanknamespace' )->text();
		}
	}

	/**
	 * Handler for the GetPreferences hook, to add and hide user preferences as configured
	 *
	 * @param User $user
	 * @param array &$preferences Their preferences object
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		global $wgLang;
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$veConfig = $services->getConfigFactory()
			->makeConfig( 'visualeditor' );

		if (
			$veConfig->get( 'VisualEditorEnableBetaFeature' ) &&
			!ExtensionRegistry::getInstance()->isLoaded( 'BetaFeatures' )
		) {
			// Config option for visual editing "alpha" state (no Beta Feature)
			$namespaces = ApiVisualEditor::getAvailableNamespaceIds( $veConfig );

			$visualEnablePreference = [
				'type' => 'toggle',
				'label-message' => [
					'visualeditor-preference-enable',
					$wgLang->commaList( array_map(
						[ 'self', 'convertNs' ],
						$namespaces
					) ),
					count( $namespaces )
				],
				'section' => 'editing/editor'
			];
			if ( $userOptionsLookup->getOption( $user, 'visualeditor-autodisable' ) ) {
				$visualEnablePreference['default'] = false;
			}
			$preferences['visualeditor-enable'] = $visualEnablePreference;
		}

		if ( !$veConfig->get( 'VisualEditorEnableBetaFeature' ) ) {
			// Config option for visual editing "deployed" state (opt-out)
			$preferences['visualeditor-betatempdisable'] = [
				'type' => 'toggle',
				'label-message' => 'visualeditor-preference-betatempdisable',
				'section' => 'editing/editor',
				'default' => $userOptionsLookup->getOption( $user, 'visualeditor-betatempdisable' ) ||
					$userOptionsLookup->getOption( $user, 'visualeditor-autodisable' )
			];
		}

		// Config option for wikitext editing "deployed" state (opt-out)
		if (
			$veConfig->get( 'VisualEditorEnableWikitext' )
		) {
			$preferences['visualeditor-newwikitext'] = [
				'type' => 'toggle',
				'label-message' => 'visualeditor-preference-newwikitexteditor-enable',
				'section' => 'editing/editor'
			];
		}

		// Config option for Single Edit Tab
		if (
			$veConfig->get( 'VisualEditorUseSingleEditTab' ) &&
			self::enabledForUser( $user )
		) {
			$preferences['visualeditor-tabs'] = [
				'type' => 'select',
				'label-message' => 'visualeditor-preference-tabs',
				'section' => 'editing/editor',
				'options' => [
					wfMessage( 'visualeditor-preference-tabs-remember-last' )->escaped() => 'remember-last',
					wfMessage( 'visualeditor-preference-tabs-prefer-ve' )->escaped() => 'prefer-ve',
					wfMessage( 'visualeditor-preference-tabs-prefer-wt' )->escaped() => 'prefer-wt',
					wfMessage( 'visualeditor-preference-tabs-multi-tab' )->escaped() => 'multi-tab'
				]
			];
		}

		$api = [ 'type' => 'api' ];
		$preferences['visualeditor-autodisable'] = $api;
		// The diff mode is persisted for each editor mode separately,
		// e.g. use visual diffs for visual mode only.
		$preferences['visualeditor-diffmode-source'] = $api;
		$preferences['visualeditor-diffmode-visual'] = $api;
		$preferences['visualeditor-diffmode-historical'] = $api;
		$preferences['visualeditor-editor'] = $api;
		$preferences['visualeditor-hidebetawelcome'] = $api;
		$preferences['visualeditor-hidetabdialog'] = $api;
		$preferences['visualeditor-hidesourceswitchpopup'] = $api;
		$preferences['visualeditor-hidevisualswitchpopup'] = $api;
		$preferences['visualeditor-hideusered'] = $api;
		$preferences['visualeditor-findAndReplace-diacritic'] = $api;
		$preferences['visualeditor-findAndReplace-findText'] = $api;
		$preferences['visualeditor-findAndReplace-replaceText'] = $api;
		$preferences['visualeditor-findAndReplace-regex'] = $api;
		$preferences['visualeditor-findAndReplace-matchCase'] = $api;
		$preferences['visualeditor-findAndReplace-word'] = $api;
	}

	/**
	 * Handler for the GetBetaPreferences hook, to add and hide user beta preferences as configured
	 *
	 * @param User $user
	 * @param array &$preferences Their preferences object
	 */
	public static function onGetBetaPreferences( User $user, array &$preferences ) {
		$coreConfig = RequestContext::getMain()->getConfig();
		$iconpath = $coreConfig->get( 'ExtensionAssetsPath' ) . "/VisualEditor/images";

		$veConfig = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'visualeditor' );

		if ( $veConfig->get( 'VisualEditorEnableBetaFeature' ) ) {
			$preferences['visualeditor-enable'] = [
				'version' => '1.0',
				'label-message' => 'visualeditor-preference-core-label',
				'desc-message' => 'visualeditor-preference-core-description',
				'screenshot' => [
					'ltr' => "$iconpath/betafeatures-icon-VisualEditor-ltr.svg",
					'rtl' => "$iconpath/betafeatures-icon-VisualEditor-rtl.svg",
				],
				'info-message' => 'visualeditor-preference-core-info-link',
				'discussion-message' => 'visualeditor-preference-core-discussion-link',
				'requirements' => [
					'javascript' => true,
					'unsupportedList' => $veConfig->get( 'VisualEditorBrowserUnsupportedList' ),
				]
			];
		}

		if (
			$veConfig->get( 'VisualEditorEnableWikitextBetaFeature' ) &&
			// Don't try to register as a beta feature if enabled by default
			!$veConfig->get( 'VisualEditorEnableWikitext' )
		) {
			$preferences['visualeditor-newwikitext'] = [
				'version' => '1.0',
				'label-message' => 'visualeditor-preference-newwikitexteditor-label',
				'desc-message' => 'visualeditor-preference-newwikitexteditor-description',
				'screenshot' => [
					'ltr' => "$iconpath/betafeatures-icon-WikitextEditor-ltr.svg",
					'rtl' => "$iconpath/betafeatures-icon-WikitextEditor-rtl.svg",
				],
				'info-message' => 'visualeditor-preference-newwikitexteditor-info-link',
				'discussion-message' => 'visualeditor-preference-newwikitexteditor-discussion-link',
				'requirements' => [
					'javascript' => true,
					'unsupportedList' => $veConfig->get( 'VisualEditorBrowserUnsupportedList' ),
				]
			];
		}

		if (
			$veConfig->get( 'VisualEditorEnableDiffPageBetaFeature' ) &&
			// Don't try to register as a beta feature if enabled by default
			!$veConfig->get( 'VisualEditorEnableDiffPage' )
		) {
			$preferences['visualeditor-visualdiffpage'] = [
				'version' => '1.0',
				'label-message' => 'visualeditor-preference-visualdiffpage-label',
				'desc-message' => 'visualeditor-preference-visualdiffpage-description',
				'screenshot' => [
					'ltr' => "$iconpath/betafeatures-icon-VisualDiffPage-ltr.svg",
					'rtl' => "$iconpath/betafeatures-icon-VisualDiffPage-rtl.svg",
				],
				'info-message' => 'visualeditor-preference-visualdiffpage-info-link',
				'discussion-message' => 'visualeditor-preference-visualdiffpage-discussion-link',
				'requirements' => [
					'javascript' => true,
					'unsupportedList' => $veConfig->get( 'VisualEditorBrowserUnsupportedList' ),
				]
			];
		}
	}

	/**
	 * Implements the PreferencesFormPreSave hook, to remove the 'autodisable' flag
	 * when the user it was set on explicitly enables VE.
	 *
	 * @param array $data User-submitted data
	 * @param PreferencesFormOOUI $form A ContextSource
	 * @param User $user User with new preferences already set
	 * @param bool &$result Success or failure
	 */
	public static function onPreferencesFormPreSave( $data, $form, $user, &$result ) {
		$services = MediaWikiServices::getInstance();
		$veConfig = $services->getConfigFactory()->makeConfig( 'visualeditor' );
		$userOptionsManager = $services->getUserOptionsManager();
		// On a wiki where enable is hidden and set to 1, if user sets betatempdisable=0
		// then set autodisable=0
		// On a wiki where betatempdisable is hidden and set to 0, if user sets enable=1
		// then set autodisable=0
		if (
			$userOptionsManager->getOption( $user, 'visualeditor-autodisable' ) &&
			$userOptionsManager->getOption( $user, 'visualeditor-enable' ) &&
			!$userOptionsManager->getOption( $user, 'visualeditor-betatempdisable' )
		) {
			$userOptionsManager->setOption( $user, 'visualeditor-autodisable', false );
		} elseif (
			// On a wiki where betatempdisable is hidden and set to 0, if user sets enable=0,
			// then set autodisable=1
			$veConfig->get( 'VisualEditorTransitionDefault' ) &&
			!$userOptionsManager->getOption( $user, 'visualeditor-betatempdisable' ) &&
			!$userOptionsManager->getOption( $user, 'visualeditor-enable' ) &&
			!$userOptionsManager->getOption( $user, 'visualeditor-autodisable' )
		) {
			$userOptionsManager->setOption( $user, 'visualeditor-autodisable', true );
		}
	}

	/**
	 * Implements the ListDefinedTags and ChangeTagsListActive hooks, to
	 * populate core Special:Tags with the change tags in use by VisualEditor.
	 *
	 * @param array &$tags Available change tags.
	 */
	public static function onListDefinedTags( &$tags ) {
		$tags = array_merge( $tags, static::TAGS );
	}

	/**
	 * Adds extra variables to the page config.
	 *
	 * @param array &$vars Global variables object
	 * @param OutputPage $out The page view.
	 */
	public static function onMakeGlobalVariablesScript( array &$vars, OutputPage $out ) {
		$pageLanguage = ApiVisualEditor::getPageLanguage( $out->getTitle() );
		$converter = MediaWikiServices::getInstance()->getLanguageConverterFactory()
			->getLanguageConverter( $pageLanguage );

		$fallbacks = $converter->getVariantFallbacks( $converter->getPreferredVariant() );

		$vars['wgVisualEditor'] = [
			'pageLanguageCode' => $pageLanguage->getHtmlCode(),
			'pageLanguageDir' => $pageLanguage->getDir(),
			'pageVariantFallbacks' => $fallbacks,
		];
	}

	/**
	 * Adds extra variables to the global config
	 *
	 * @param array &$vars Global variables object
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		$coreConfig = RequestContext::getMain()->getConfig();
		$veConfig = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'visualeditor' );
		$extensionRegistry = ExtensionRegistry::getInstance();
		$availableNamespaces = ApiVisualEditor::getAvailableNamespaceIds( $veConfig );
		$availableContentModels = array_filter(
			array_merge(
				$extensionRegistry->getAttribute( 'VisualEditorAvailableContentModels' ),
				$veConfig->get( 'VisualEditorAvailableContentModels' )
			)
		);

		$namespacesWithSubpages = $coreConfig->get( 'NamespacesWithSubpages' );
		// Export as a list of namespaces where subpages are enabled instead of an object
		// mapping namespaces to if subpages are enabled or not, so filter out disabled
		// namespaces and then just use the keys. See T291729.
		$namespacesWithSubpages = array_filter( $namespacesWithSubpages );
		$namespacesWithSubpagesEnabled = array_keys( $namespacesWithSubpages );
		// $wgNamespacesWithSubpages can include namespaces that don't exist, no need
		// to include those in the JavaScript data. See T291727.
		// Run this filtering after the filter for subpages being enabled, to reduce
		// the number of calls needed to namespace info.
		$nsInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
		$namespacesWithSubpagesEnabled = array_values( array_filter(
			$namespacesWithSubpagesEnabled,
			[ $nsInfo, 'exists' ]
		) );
		$vars['wgVisualEditorConfig'] = [
			'usePageImages' => $extensionRegistry->isLoaded( 'PageImages' ),
			'usePageDescriptions' => $extensionRegistry->isLoaded( 'WikibaseClient' ),
			'isBeta' => $veConfig->get( 'VisualEditorEnableBetaFeature' ),
			'disableForAnons' => $veConfig->get( 'VisualEditorDisableForAnons' ),
			'preloadModules' => $veConfig->get( 'VisualEditorPreloadModules' ),
			'preferenceModules' => $veConfig->get( 'VisualEditorPreferenceModules' ),
			'namespaces' => $availableNamespaces,
			'contentModels' => $availableContentModels,
			'pluginModules' => array_merge(
				$extensionRegistry->getAttribute( 'VisualEditorPluginModules' ),
				// @todo deprecate the global setting
				$veConfig->get( 'VisualEditorPluginModules' )
			),
			'thumbLimits' => $coreConfig->get( 'ThumbLimits' ),
			'galleryOptions' => $coreConfig->get( 'GalleryOptions' ),
			'unsupportedList' => $veConfig->get( 'VisualEditorBrowserUnsupportedList' ),
			'tabPosition' => $veConfig->get( 'VisualEditorTabPosition' ),
			'tabMessages' => $veConfig->get( 'VisualEditorTabMessages' ),
			'singleEditTab' => $veConfig->get( 'VisualEditorUseSingleEditTab' ),
			'enableVisualSectionEditing' => $veConfig->get( 'VisualEditorEnableVisualSectionEditing' ),
			'showBetaWelcome' => $veConfig->get( 'VisualEditorShowBetaWelcome' ),
			'allowExternalLinkPaste' => $veConfig->get( 'VisualEditorAllowExternalLinkPaste' ),
			'enableTocWidget' => $veConfig->get( 'VisualEditorEnableTocWidget' ),
			'enableWikitext' => (
				$veConfig->get( 'VisualEditorEnableWikitext' ) ||
				$veConfig->get( 'VisualEditorEnableWikitextBetaFeature' )
			),
			'useChangeTagging' => $veConfig->get( 'VisualEditorUseChangeTagging' ),
			'namespacesWithSubpages' => $namespacesWithSubpagesEnabled,
			'specialBooksources' => urldecode( SpecialPage::getTitleFor( 'Booksources' )->getPrefixedURL() ),
			'rebaserUrl' => $coreConfig->get( 'VisualEditorRebaserURL' ),
			'restbaseUrl' => $coreConfig->get( 'VisualEditorRestbaseURL' ),
			'fullRestbaseUrl' => $coreConfig->get( 'VisualEditorFullRestbaseURL' ),
			'allowLossySwitching' => $coreConfig->get( 'VisualEditorAllowLossySwitching' ),
			'feedbackApiUrl' => $veConfig->get( 'VisualEditorFeedbackAPIURL' ),
			'feedbackTitle' => $veConfig->get( 'VisualEditorFeedbackTitle' ),
			'sourceFeedbackTitle' => $veConfig->get( 'VisualEditorSourceFeedbackTitle' ),
			'transclusionDialogSuggestedValues' => $veConfig->get( 'VisualEditorTransclusionDialogSuggestedValues' ),
			'transclusionDialogInlineDescriptions' =>
				$veConfig->get( 'VisualEditorTransclusionDialogInlineDescriptions' ),
			'transclusionDialogBackButton' => $veConfig->get( 'VisualEditorTransclusionDialogBackButton' ),
			'transclusionDialogNewSidebar' => $veConfig->get( 'VisualEditorTransclusionDialogNewSidebar' ),
			'cirrusSearchLookup' => $extensionRegistry->isLoaded( 'CirrusSearch' )
				&& $veConfig->get( 'VisualEditorTemplateSearchImprovements' ),
			'templateSearchImprovements' => $veConfig->get( 'VisualEditorTemplateSearchImprovements' ),
		];
	}

	/**
	 * Conditionally register the jquery.uls.data and jquery.i18n modules, in case they've already
	 * been registered by the UniversalLanguageSelector extension or the TemplateData extension.
	 *
	 * @param ResourceLoader $resourceLoader Client-side code and assets to be loaded.
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ) {
		$veResourceTemplate = [
			'localBasePath' => dirname( __DIR__ ),
			'remoteExtPath' => 'VisualEditor',
		];

		// Only register VisualEditor core's local version of jquery.uls.data if it hasn't been
		// installed locally already (presumably, by the UniversalLanguageSelector extension).
		if ( !$resourceLoader->isModuleRegistered( 'jquery.uls.data' ) ) {
			$resourceLoader->register( [
				'jquery.uls.data' => $veResourceTemplate + [
					'scripts' => [
						'lib/ve/lib/jquery.uls/src/jquery.uls.data.js',
						'lib/ve/lib/jquery.uls/src/jquery.uls.data.utils.js',
					],
					'targets' => [ 'desktop', 'mobile' ],
			] ] );
		}
	}

	/**
	 * Ensures that we know whether we're running inside a parser test.
	 *
	 * @param array &$settings The settings with which MediaWiki is being run.
	 */
	public static function onParserTestGlobals( array &$settings ) {
		$settings['wgVisualEditorInParserTests'] = true;
	}

	/**
	 * @param array &$redirectParams Parameters preserved on special page redirects
	 *   to wiki pages
	 */
	public static function onRedirectSpecialArticleRedirectParams( &$redirectParams ) {
		$redirectParams[] = 'veaction';
	}

	/**
	 * If the user has specified that they want to edit the page with VE, suppress any redirect.
	 *
	 * @param Title $title Title being used for request
	 * @param Article|null $article The page being viewed.
	 * @param OutputPage $output The page view.
	 * @param User $user The user-specific settings.
	 * @param WebRequest $request
	 * @param MediaWiki $mediaWiki Helper class.
	 */
	public static function onBeforeInitialize(
		Title $title, $article, OutputPage $output,
		User $user, WebRequest $request, MediaWiki $mediaWiki
	) {
		if ( $request->getVal( 'veaction' ) ) {
			$request->setVal( 'redirect', 'no' );
		}
	}

	/**
	 * On login, if user has a VEE cookie, set their preference equal to it.
	 *
	 * @param User $user The user-specific settings.
	 */
	public static function onUserLoggedIn( $user ) {
		$cookie = RequestContext::getMain()->getRequest()->getCookie( 'VEE', '' );
		if ( $cookie === 'visualeditor' || $cookie === 'wikitext' ) {
			$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
			DeferredUpdates::addUpdate( new AtomicSectionUpdate(
				$lb->getConnectionRef( DB_PRIMARY ),
				__METHOD__,
				static function () use ( $user, $cookie ) {
					$services = MediaWikiServices::getInstance();
					if ( $services->getReadOnlyMode()->isReadOnly() ) {
						return;
					}

					$uLatest = $user->getInstanceForUpdate();
					$services->getUserOptionsManager()
						->setOption( $uLatest, 'visualeditor-editor', $cookie );
					$uLatest->saveSettings();
				}
			) );
		}
	}
}
