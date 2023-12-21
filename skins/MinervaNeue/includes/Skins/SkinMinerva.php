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

namespace MediaWiki\Minerva\Skins;

use ExtensionRegistry;
use Html;
use Language;
use MediaWiki\Extension\Notifications\Controller\NotificationController;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\Menu\Main\MainMenuDirector;
use MediaWiki\Minerva\Menu\PageActions\PageActionsDirector;
use MediaWiki\Minerva\Menu\User\UserMenuDirector;
use MediaWiki\Minerva\Permissions\IMinervaPagePermissions;
use MediaWiki\Minerva\SkinOptions;
use MediaWiki\Title\Title;
use MWTimestamp;
use RuntimeException;
use SkinMustache;
use SkinTemplate;
use SpecialMobileHistory;
use SpecialPage;

/**
 * Minerva: Born from the godhead of Jupiter with weapons!
 * A skin that works on both desktop and mobile
 * @ingroup Skins
 */
class SkinMinerva extends SkinMustache {
	/** @const LEAD_SECTION_NUMBER integer which corresponds to the lead section
	 * in editing mode
	 */
	public const LEAD_SECTION_NUMBER = 0;

	/** @var string Name of this skin */
	public $skinname = 'minerva';
	/** @var string Name of this used template */
	public $template = 'MinervaTemplate';

	/** @var SkinOptions */
	private $skinOptions;

	/** @var array|null */
	private $sidebarCachedResult;

	/** @var array|null */
	private $contentNavigationUrls;

	/** @var TemplateParser|null */
	private $templateParser;

	/**
	 * This variable is lazy loaded, please use getPermissions() getter
	 * @see SkinMinerva::getPermissions()
	 * @var IMinervaPagePermissions
	 */
	private $permissions;

	/**
	 * @return SkinOptions
	 */
	private function getSkinOptions() {
		if ( !$this->skinOptions ) {
			$this->skinOptions = MediaWikiServices::getInstance()->getService( 'Minerva.SkinOptions' );
		}
		return $this->skinOptions;
	}

	/**
	 * @return bool
	 */
	private function hasPageActions() {
		$title = $this->getTitle();
		return !$title->isSpecialPage() && !$title->isMainPage() &&
			$this->getContext()->getActionName() === 'view';
	}

	/**
	 * @return bool
	 */
	private function hasSecondaryActions() {
		return !$this->getUserPageHelper()->isUserPage();
	}

	/**
	 * @return bool
	 */
	private function isFallbackEditor() {
		$action = $this->getContext()->getActionName();
		return $action === 'edit';
	}

	/**
	 * Returns available page actions if the page has any.
	 *
	 * @param array $nav result of SkinTemplate::buildContentNavigationUrls
	 * @return array|null
	 */
	private function getPageActions( array $nav ) {
		if ( $this->isFallbackEditor() || !$this->hasPageActions() ) {
			return null;
		}
		$services = MediaWikiServices::getInstance();
		/** @var PageActionsDirector $pageActionsDirector */
		$pageActionsDirector = $services->getService( 'Minerva.Menu.PageActionsDirector' );
		$sidebar = $this->buildSidebar();
		$actions = $nav['actions'] ?? [];
		$views = $nav['views'] ?? [];
		return $pageActionsDirector->buildMenu( $sidebar['TOOLBOX'], $actions, $views );
	}

	/**
	 * A notification icon that links to Special:Mytalk when Echo is not installed.
	 * Consider upstreaming this to core or removing at a future date.
	 *
	 * @return array
	 */
	private function getNotificationFallbackButton() {
		return [
				'icon' => 'bellOutline-base20',
				'href' => SpecialPage::getTitleFor( 'Mytalk' )->getLocalURL(
						[ 'returnto' => $this->getTitle()->getPrefixedText() ]
				),
		];
	}

	/**
	 * @param array $alert
	 * @param array $notice
	 * @return array
	 */
	private function getCombinedNotificationButton( array $alert, array $notice ) {
		// Sum the notifications from the two original buttons
		$notifCount = ( $alert['data']['counter-num'] ?? 0 ) + ( $notice['data']['counter-num'] ?? 0 );
		$alert['data']['counter-num'] = $notifCount;
		// @phan-suppress-next-line PhanUndeclaredClassReference
		if ( class_exists( NotificationController::class ) ) {
			// @phan-suppress-next-line PhanUndeclaredClassMethod
			$alert['data']['counter-text'] = NotificationController::formatNotificationCount( $notifCount );
		} else {
			$alert['data']['counter-text'] = $notifCount;
		}

		$linkClassAlert = $alert['link-class'] ?? [];
		$hasUnseenAlerts = is_array( $linkClassAlert ) && in_array( 'mw-echo-unseen-notifications', $linkClassAlert );
		// The circle should only appear if there are unseen notifications.
		// Once the notifications are seen (by opening the notification drawer)
		// then the icon reverts to a gray circle, but on page refresh
		// it should revert back to a bell icon.
		// If you try and change this behaviour, at time of writing
		// (December 2022) JavaScript will correct it.
		if ( $notifCount > 0 && $hasUnseenAlerts ) {
			$linkClass = $notice['link-class'] ?? [];
			$hasUnseenNotices = is_array( $linkClass ) && in_array( 'mw-echo-unseen-notifications', $linkClass );
			return $this->getNotificationCircleButton( $alert, $hasUnseenNotices );
		} else {
			return $this->getNotificationButton( $alert );
		}
	}

	/**
	 * Minerva differs from other skins in that for users with unread notifications
	 * instead of a bell with a small square indicating the number of notifications
	 * it shows a red circle with a number inside. Ideally Vector and Minerva would
	 * be treated the same but we'd need to talk to a designer about consolidating these
	 * before making such a decision.
	 *
	 * @param array $alert
	 * @param bool $hasUnseenNotices does the user have unseen notices?
	 * @return array
	 */
	private function getNotificationCircleButton( array $alert, bool $hasUnseenNotices ) {
		$alertCount = $alert['data']['counter-num'] ?? 0;
		$linkClass = $alert['link-class'] ?? [];
		$hasSeenAlerts = is_array( $linkClass ) && in_array( 'mw-echo-unseen-notifications', $linkClass );
		$alertText = $alert['data']['counter-text'] ?? $alertCount;
		$alert['icon'] = 'circle';
		$alert['class'] = 'notification-count';
		if ( $hasSeenAlerts || $hasUnseenNotices ) {
			$alert['class'] .= ' notification-unseen mw-echo-unseen-notifications';
		}
		return $alert;
	}

	/**
	 * Removes the OOUI icon class and adds Minerva notification classes.
	 *
	 * @param array $alert
	 * @return array
	 */
	private function getNotificationButton( array $alert ) {
		$linkClass = $alert['link-class'];
		$alert['link-class'] = array_filter(
			$linkClass,
			static function ( $class ) {
				return $class !== 'oo-ui-icon-bellOutline';
			}
		);
		$alert['icon'] = 'bellOutline-base20';
		return $alert;
	}

	/**
	 * Caches content navigation urls locally for use inside getTemplateData
	 *
	 * @inheritDoc
	 */
	protected function runOnSkinTemplateNavigationHooks( SkinTemplate $skin, &$contentNavigationUrls ) {
		parent::runOnSkinTemplateNavigationHooks( $skin, $contentNavigationUrls );
		// There are some SkinTemplate modifications that occur after the execution of this hook
		// to add rel attributes and ID attributes.
		// The only one Minerva needs is this one so we manually add it.
		foreach ( array_keys( $contentNavigationUrls['associated-pages'] ) as $id ) {
			if ( in_array( $id, [ 'user_talk', 'talk' ] ) ) {
				$contentNavigationUrls['associated-pages'][ $id ]['rel'] = 'discussion';
			}
		}
		$skinOptions = $this->getSkinOptions();
		$this->contentNavigationUrls = $contentNavigationUrls;

		//
		// Echo Technical debt!!
		// * Convert the Echo button into a single button
		// * Switch out the icon.
		//
		if ( $this->getUser()->isRegistered() ) {
			if ( count( $contentNavigationUrls['notifications'] ) === 0 ) {
				// Shown to logged in users when Echo is not installed:
				$contentNavigationUrls['notifications']['mytalks'] = $this->getNotificationFallbackButton();
			} elseif ( $skinOptions->get( SkinOptions::SINGLE_ECHO_BUTTON ) ) {
				// Combine notification icons. Minerva only shows one entry point to notifications.
				// This can be reconsidered with a solution to https://phabricator.wikimedia.org/T142981
				$alert = $contentNavigationUrls['notifications']['notifications-alert'] ?? null;
				$notice = $contentNavigationUrls['notifications']['notifications-notice'] ?? null;
				if ( $alert && $notice ) {
					unset( $contentNavigationUrls['notifications']['notifications-notice'] );
					$contentNavigationUrls['notifications']['notifications-alert'] =
						$this->getCombinedNotificationButton( $alert, $notice );
				}
			} else {
				// Show desktop alert icon.
				$alert = $contentNavigationUrls['notifications']['notifications-alert'] ?? null;
				if ( $alert ) {
					// Correct the icon to be the bell filled rather than the outline to match
					// Echo's badge.
					$linkClass = $alert['link-class'] ?? [];
					$alert['link-class'] = array_filter( $linkClass, static function ( $class ) {
						return $class !== 'oo-ui-icon-bellOutline';
					} );
					$contentNavigationUrls['notifications']['notifications-alert'] = $alert;
				}
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
			$data = parent::getTemplateData();
			$skinOptions = $this->getSkinOptions();
			// FIXME: Can we use $data instead of calling buildContentNavigationUrls ?
			$nav = $this->contentNavigationUrls;
			if ( $nav === null ) {
				throw new RuntimeException( 'contentNavigationUrls was not set as expected.' );
			}
			if ( !$this->hasCategoryLinks() ) {
				unset( $data['html-categories'] );
			}

			// Special handling for certain pages.
			// This is technical debt that should be upstreamed to core.
			$isUserPage = $this->getUserPageHelper()->isUserPage();
			$isUserPageAccessible = $this->getUserPageHelper()->isUserPageAccessibleToCurrentUser();
			if ( $isUserPage && $isUserPageAccessible ) {
				$data['html-title-heading'] = $this->getUserPageHeadingHtml( $data['html-title-heading' ] );
			}

			$usermessage = $data['html-user-message'] ?? '';
			if ( $usermessage ) {
				$data['html-user-message'] = Html::warningBox(
					'<span class="minerva-icon minerva-icon--userTalk-warning"></span>&nbsp;'
						. $usermessage,
					'minerva-anon-talk-message'
				);
			}
			$allLanguages = $data['data-portlets']['data-languages']['array-items'] ?? [];
			$allVariants = $data['data-portlets']['data-variants']['array-items'] ?? [];
			$notifications = $data['data-portlets']['data-notifications']['array-items'] ?? [];

			return $data + [
				'has-minerva-languages' => !empty( $allLanguages ) || !empty( $allVariants ),
				'array-minerva-banners' => $this->prepareBanners( $data['html-site-notice'] ),
				'data-minerva-search-box' => $data['data-search-box'] + [
					'data-btn' => [
						'data-icon' => [
							'icon' => 'search-base20',
						],
						'label' => $this->msg( 'searchbutton' )->escaped(),
						'classes' => 'skin-minerva-search-trigger',
						'array-attributes' => [
							[
								'key' => 'id',
								'value' => 'searchIcon',
							]
						]
					],
				],
				'data-minerva-main-menu-btn' => [
					'data-icon' => [
						'icon' => 'menu-base20',
					],
					'tag-name' => 'label',
					'classes' => 'toggle-list__toggle',
					'array-attributes' => [
						[
							'key' => 'for',
							'value' => 'main-menu-input',
						],
						[
							'key' => 'id',
							'value' => 'mw-mf-main-menu-button',
						],
						[
							'key' => 'aria-hidden',
							'value' => 'true',
						],
						[
							'key' => 'data-event-name',
							'value' => 'ui.mainmenu',
						],
					],
					'text' => $this->msg( 'mobile-frontend-main-menu-button-tooltip' )->escaped(),
				],
				'data-minerva-main-menu' => $this->getMainMenu()->getMenuData(
					$nav,
					$this->buildSidebar()
				)['items'],
				'html-minerva-tagline' => $this->getTaglineHtml(),
				'html-minerva-user-menu' => $this->getPersonalToolsMenu( $nav['user-menu'] ),
				'is-minerva-beta' => $this->getSkinOptions()->get( SkinOptions::BETA_MODE ),
				'data-minerva-notifications' => $notifications ? [
					'array-buttons' => $this->getNotificationButtons( $notifications ),
				] : null,
				'data-minerva-tabs' => $this->getTabsData( $nav ),
				'data-minerva-page-actions' => $this->getPageActions( $nav ),
				'data-minerva-secondary-actions' => $this->getSecondaryActions( $nav ),
				'html-minerva-subject-link' => $this->getSubjectPage(),
				'data-minerva-history-link' => $this->getHistoryLink( $this->getTitle() ),
			];
	}

	/**
	 * Prepares the notification badges for the Button template.
	 *
	 * @internal
	 * @param array $notifications
	 * @return array
	 */
	public static function getNotificationButtons( array $notifications ) {
		$btns = [];

		foreach ( $notifications as $notification ) {
			$linkData = $notification['array-links'][ 0 ] ?? [];
			$icon = $linkData['icon'] ?? null;
			if ( !$icon ) {
				continue;
			}
			$id = $notification['id'] ?? null;
			$classes = '';
			$attributes = [];

			// We don't want to output multiple attributes.
			// Iterate through the attributes and pull out ID and class which
			// will be defined separately.
			foreach ( $linkData[ 'array-attributes' ] as $keyValuePair ) {
				if ( $keyValuePair['key'] === 'class' ) {
					$classes = $keyValuePair['value'];
				} elseif ( $keyValuePair['key'] === 'id' ) {
					// ignore. We want to use the LI `id` instead.
				} else {
					$attributes[] = $keyValuePair;
				}
			}
			// add LI ID to end for use on the button.
			if ( $id ) {
				$attributes[] = [
					'key' => 'id',
					'value' => $id,
				];
			}
			$btns[] = [
				'tag-name' => 'a',
				// FIXME: Move preg_replace when Echo no longer provides this class.
				'classes' => preg_replace( '/oo-ui-icon-(bellOutline|tray)/', '', $classes ),
				'array-attributes' => $attributes,
				'data-icon' => [
					'icon' => $icon,
				],
				'label' => $linkData['text'] ?? '',
			];
		}
		return $btns;
	}

	/**
	 * Tabs are available if a page has page actions but is not the talk page of
	 * the main page.
	 *
	 * Special pages have tabs if SkinOptions::TABS_ON_SPECIALS is enabled.
	 * This is used by Extension:GrowthExperiments
	 *
	 * @return bool
	 */
	private function hasPageTabs() {
		$title = $this->getTitle();
		$skinOptions = $this->getSkinOptions();
		$isSpecialPage = $title->isSpecialPage();
		$subjectPage = MediaWikiServices::getInstance()->getNamespaceInfo()
			->getSubjectPage( $title );
		$isMainPageTalk = Title::newFromLinkTarget( $subjectPage )->isMainPage();
		return (
				$this->hasPageActions() && !$isMainPageTalk &&
				$skinOptions->get( SkinOptions::TALK_AT_TOP )
			) || (
				$isSpecialPage &&
				$skinOptions->get( SkinOptions::TABS_ON_SPECIALS )
			);
	}

	/**
	 * @param array $contentNavigationUrls
	 * @return array
	 */
	private function getTabsData( array $contentNavigationUrls ) {
		$hasPageTabs = $this->hasPageTabs();
		if ( !$hasPageTabs ) {
			return [];
		}
		return $contentNavigationUrls ? [
			'items' => array_values( $contentNavigationUrls['associated-pages'] ),
		] : [];
	}

	/**
	 * Lazy load the permissions object. We don't want to initialize it as it requires many
	 * dependencies, sometimes some of those dependencies cannot be fulfilled (like missing Title
	 * object)
	 * @return IMinervaPagePermissions
	 */
	private function getPermissions(): IMinervaPagePermissions {
		if ( $this->permissions === null ) {
			$this->permissions = MediaWikiServices::getInstance()
				->getService( 'Minerva.Permissions' )
				->setContext( $this->getContext() );
		}
		return $this->permissions;
	}

	/**
	 * Initalized main menu. Please use getter.
	 * @var MainMenuDirector
	 */
	private $mainMenu;

	/**
	 * Build the Main Menu Director by passing the skin options
	 *
	 * @return MainMenuDirector
	 */
	protected function getMainMenu(): MainMenuDirector {
		if ( !$this->mainMenu ) {
			$this->mainMenu = MediaWikiServices::getInstance()->getService( 'Minerva.Menu.MainDirector' );
		}
		return $this->mainMenu;
	}

	/**
	 * Prepare all Minerva menus
	 *
	 * @param array $personalUrls result of SkinTemplate::buildPersonalUrls
	 * @return string|null
	 */
	private function getPersonalToolsMenu( array $personalUrls ) {
		$services = MediaWikiServices::getInstance();
		/** @var UserMenuDirector $userMenuDirector */
		$userMenuDirector = $services->getService( 'Minerva.Menu.UserMenuDirector' );
		return $userMenuDirector->renderMenuData( $personalUrls );
	}

	/**
	 * @return string
	 */
	protected function getSubjectPage() {
		$services = MediaWikiServices::getInstance();
		$title = $this->getTitle();
		$skinOptions = $this->getSkinOptions();

		// If it's a talk page, add a link to the main namespace page
		// In AMC we do not need to do this as there is an easy way back to the article page
		// via the talk/article tabs.
		if ( $title->isTalkPage() && !$skinOptions->get( SkinOptions::TALK_AT_TOP ) ) {
			// if it's a talk page for which we have a special message, use it
			switch ( $title->getNamespace() ) {
				case NS_USER_TALK:
					$msg = 'mobile-frontend-talk-back-to-userpage';
					break;
				case NS_PROJECT_TALK:
					$msg = 'mobile-frontend-talk-back-to-projectpage';
					break;
				case NS_FILE_TALK:
					$msg = 'mobile-frontend-talk-back-to-filepage';
					break;
				default: // generic (all other NS)
					$msg = 'mobile-frontend-talk-back-to-page';
			}
			$subjectPage = $services->getNamespaceInfo()->getSubjectPage( $title );

			return MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
				$subjectPage,
				$this->msg( $msg, $title->getText() )->text(),
				[
					'data-event-name' => 'talk.returnto',
					'class' => 'return-link'
				]
			);
		} else {
			return '';
		}
	}

	/**
	 * Overrides Skin::doEditSectionLink
	 * @param Title $nt The title being linked to (may not be the same as
	 *   the current page, if the section is included from a template)
	 * @param string $section
	 * @param string|null $tooltip
	 * @param Language $lang
	 * @return string
	 */
	public function doEditSectionLink( Title $nt, $section, $tooltip, Language $lang ) {
		if ( $this->getPermissions()->isAllowed( IMinervaPagePermissions::EDIT_OR_CREATE ) &&
			 !$nt->isMainPage() ) {
			$message = $this->msg( 'mobile-frontend-editor-edit' )->inLanguage( $lang )->text();
			$html = Html::openElement( 'span', [ 'class' => 'mw-editsection' ] );
			if ( !$this->templateParser ) {
				$this->templateParser = new TemplateParser( __DIR__ );
			}
			$templateParser = $this->templateParser;
			$html .= $templateParser->processTemplate( 'Button', [
				'tag-name' => 'a',
				'data-icon' => [
					'icon' => 'edit-base20'
				],
				'array-attributes' => [
					[
						'key' => 'href',
						'value' => $nt->getLocalURL( [ 'action' => 'edit', 'section' => $section ] ),
					],
					[
						'key' => 'title',
						'value' => $this->msg( 'editsectionhint', $tooltip )->inLanguage( $lang )->text(),
					],
					[
						'key' => 'data-section',
						'value' => $section,
					],
				],
				'label' => $message,
				// Note visibility of the edit section link button is controlled by .edit-page in ui.less so
				// we default to enabled even though this may not be true.
				'classes' => 'edit-page',
			] );
			$html .= Html::closeElement( 'span' );
			return $html;
		}
		return '';
	}

	/**
	 * Takes a title and returns classes to apply to the body tag
	 * @param Title $title
	 * @return string
	 */
	public function getPageClasses( $title ) {
		$skinOptions = $this->getSkinOptions();
		$className = parent::getPageClasses( $title );
		$className .= ' ' . ( $skinOptions->get( SkinOptions::BETA_MODE )
				? 'beta' : 'stable' );

		if ( $title->isMainPage() ) {
			$className .= ' page-Main_Page ';
		}

		if ( $this->getUser()->isRegistered() ) {
			$className .= ' is-authenticated';
		}
		// The new treatment should only apply to the main namespace
		if (
			$title->getNamespace() === NS_MAIN &&
			$skinOptions->get( SkinOptions::PAGE_ISSUES )
		) {
			$className .= ' issues-group-B';
		}
		return $className;
	}

	/**
	 * Whether the output page contains category links and the category feature is enabled.
	 * @return bool
	 */
	private function hasCategoryLinks() {
		$skinOptions = $this->getSkinOptions();
		if ( !$skinOptions->get( SkinOptions::CATEGORIES ) ) {
			return false;
		}
		$categoryLinks = $this->getOutput()->getCategoryLinks();

		if ( !count( $categoryLinks ) ) {
			return false;
		}
		return !empty( $categoryLinks['normal'] ) || !empty( $categoryLinks['hidden'] );
	}

	/**
	 * @return SkinUserPageHelper
	 */
	public function getUserPageHelper() {
		return MediaWikiServices::getInstance()->getService( 'Minerva.SkinUserPageHelper' );
	}

	/**
	 * Get a history link which describes author and relative time of last edit
	 * @param Title $title The Title object of the page being viewed
	 * @param string $timestamp
	 * @return array
	 */
	protected function getRelativeHistoryLink( Title $title, $timestamp ) {
		$user = $this->getUser();
		$userDate = $this->getLanguage()->userDate( $timestamp, $user );
		$text = $this->msg(
			'minerva-last-modified-date', $userDate,
			$this->getLanguage()->userTime( $timestamp, $user )
		)->parse();
		return [
			// Use $edit['timestamp'] (Unix format) instead of $timestamp (MW format)
			'data-timestamp' => wfTimestamp( TS_UNIX, $timestamp ),
			'href' => $this->getHistoryUrl( $title ),
			'text' => $text,
		] + $this->getRevisionEditorData( $title );
	}

	/**
	 * Get a history link which makes no reference to user or last edited time
	 * @param Title $title The Title object of the page being viewed
	 * @return array
	 */
	protected function getGenericHistoryLink( Title $title ) {
		$text = $this->msg( 'mobile-frontend-history' )->plain();
		return [
			'href' => $this->getHistoryUrl( $title ),
			'text' => $text,
		];
	}

	/**
	 * Get the URL for the history page for the given title using Special:History
	 * when available.
	 * @param Title $title The Title object of the page being viewed
	 * @return string
	 */
	protected function getHistoryUrl( Title $title ) {
		return ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			SpecialMobileHistory::shouldUseSpecialHistory( $title, $this->getUser() ) ?
			SpecialPage::getTitleFor( 'History', $title )->getLocalURL() :
			$title->getLocalURL( [ 'action' => 'history' ] );
	}

	/**
	 * Prepare the content for the 'last edited' message, e.g. 'Last edited on 30 August
	 * 2013, at 23:31'. This message is different for the main page since main page
	 * content is typically transcluded rather than edited directly.
	 *
	 * The relative time is only rendered on the latest revision.
	 * For older revisions the last modified information will not render with a relative time
	 * nor will it show the name of the editor.
	 * @param Title $title The Title object of the page being viewed
	 * @return array|null
	 */
	protected function getHistoryLink( Title $title ) {
		if ( !$title->exists() ||
			$this->getContext()->getActionName() !== 'view'
		) {
			return null;
		}

		$out = $this->getOutput();

		if ( !$out->getRevisionId() || !$out->isRevisionCurrent() || $title->isMainPage() ) {
			$historyLink = $this->getGenericHistoryLink( $title );
		} else {
			// Get rev_timestamp of current revision (preloaded by MediaWiki core)
			$timestamp = $out->getRevisionTimestamp();
			if ( !$timestamp ) {
				# No cached timestamp, load it from the database
				$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
				$timestamp = $revisionLookup->getTimestampFromId( $out->getRevisionId() );
			}
			$historyLink = $this->getRelativeHistoryLink( $title, $timestamp );
		}

		return $historyLink + [
			'historyIcon' => [
				'icon' => 'modified-history',
				'size' => 'medium'
			],
			'arrowIcon' => [
				'icon' => 'expand',
				'size' => 'small'
			]
		];
	}

	/**
	 * Returns data attributes representing the editor for the current revision.
	 * @param LinkTarget $title The Title object of the page being viewed
	 * @return array representing user with name and gender fields. Empty if the editor no longer
	 *   exists in the database or is hidden from public view.
	 */
	private function getRevisionEditorData( LinkTarget $title ) {
		$services = MediaWikiServices::getInstance();
		$rev = $services->getRevisionLookup()
			->getRevisionByTitle( $title );
		$result = [];
		if ( $rev ) {
			$revUser = $rev->getUser();
			// Note the user will only be returned if that information is public
			if ( $revUser ) {
				$editorName = $revUser->getName();
				$editorGender = $services->getGenderCache()->getGenderOf( $revUser, __METHOD__ );
				$result += [
					'data-user-name' => $editorName,
					'data-user-gender' => $editorGender,
				];
			}
		}
		return $result;
	}

	/**
	 * Returns the HTML representing the tagline
	 * @return string HTML for tagline
	 */
	protected function getTaglineHtml() {
		$tagline = '';

		if ( $this->getUserPageHelper()->isUserPage() ) {
			$pageUser = $this->getUserPageHelper()->getPageUser();
			$fromDate = $pageUser->getRegistration();

			if ( $this->getUserPageHelper()->isUserPageAccessibleToCurrentUser() && is_string( $fromDate ) ) {
				$fromDateTs = wfTimestamp( TS_UNIX, $fromDate );
				$genderCache = MediaWikiServices::getInstance()->getGenderCache();

				// This is shown when js is disabled. js enhancement made due to caching
				$tagline = $this->msg( 'mobile-frontend-user-page-member-since',
						$this->getLanguage()->userDate( new MWTimestamp( $fromDateTs ), $this->getUser() ),
						$pageUser )->text();

				// Define html attributes for usage with js enhancement (unix timestamp, gender)
				$attrs = [ 'id' => 'tagline-userpage',
					'data-userpage-registration-date' => $fromDateTs,
					'data-userpage-gender' => $genderCache->getGenderOf( $pageUser, __METHOD__ ) ];
			}
		} else {
			$title = $this->getTitle();
			if ( $title ) {
				$out = $this->getOutput();
				$tagline = $out->getProperty( 'wgMFDescription' );
			}
		}

		$attrs[ 'class' ] = 'tagline';
		return Html::element( 'div', $attrs, $tagline );
	}

	/**
	 * Returns the HTML representing the heading.
	 *
	 * @param string $heading The heading suggested by core.
	 * @return string HTML for header
	 */
	private function getUserPageHeadingHtml( $heading ) {
		// The heading is just the username without namespace
		// This is escaped as a precaution (user name should be safe).
		return Html::rawElement( 'h1',
			// These IDs and classes should match Skin::getTemplateData
			[
				'id' => 'firstHeading',
				'class' => 'firstHeading mw-first-heading mw-minerva-user-heading',
			],
			htmlspecialchars(
				$this->getUserPageHelper()->getPageUser()->getName()
			)
		);
	}

	/**
	 * Load internal banner content to show in pre content in template
	 * Beware of HTML caching when using this function.
	 * Content set as "internalbanner"
	 * @param string $siteNotice HTML fragment
	 * @return array
	 */
	protected function prepareBanners( $siteNotice ) {
		$banners = [];
		if ( $siteNotice && $this->getConfig()->get( 'MinervaEnableSiteNotice' ) ) {
			$banners[] = '<div id="siteNotice">' . $siteNotice . '</div>';
		} else {
			$banners[] = '<div id="siteNotice"></div>';
		}
		return $banners;
	}

	/**
	 * Returns an array with details for a language button.
	 * @return array
	 */
	protected function getLanguageButton() {
		return [
			'array-attributes' => [
				[
					'key' => 'href',
					'value' => '#p-lang'
				]
			],
			'tag-name' => 'a',
			'classes' => 'language-selector button',
			'label' => $this->msg( 'mobile-frontend-language-article-heading' )->text()
		];
	}

	/**
	 * Returns an array with details for a talk button.
	 * @param Title $talkTitle Title object of the talk page
	 * @param string $label Button label
	 * @return array
	 */
	protected function getTalkButton( $talkTitle, $label ) {
		return [
			'array-attributes' => [
				[
					'key' => 'href',
					'value' => $talkTitle->getLinkURL(),
				],
				[
					'key' => 'data-title',
					'value' => $talkTitle->getFullText(),
				]
			],
			'tag-name' => 'a',
			'classes' => 'talk button',
			'label' => $label,
		];
	}

	/**
	 * Returns an array of links for page secondary actions
	 * @param array $contentNavigationUrls
	 * @return array|null
	 */
	protected function getSecondaryActions( array $contentNavigationUrls ) {
		if ( $this->isFallbackEditor() || !$this->hasSecondaryActions() ) {
			return null;
		}

		$services = MediaWikiServices::getInstance();
		$skinOptions = $this->getSkinOptions();
		$namespaceInfo = $services->getNamespaceInfo();
		/** @var \MediaWiki\Minerva\LanguagesHelper $languagesHelper */
		$languagesHelper = $services->getService( 'Minerva.LanguagesHelper' );
		$buttons = [];
		// always add a button to link to the talk page
		// it will link to the wikitext talk page
		$title = $this->getTitle();
		$subjectPage = Title::newFromLinkTarget( $namespaceInfo->getSubjectPage( $title ) );
		$talkAtBottom = !$skinOptions->get( SkinOptions::TALK_AT_TOP ) ||
			$subjectPage->isMainPage();
		if ( !$this->getUserPageHelper()->isUserPage() &&
			$this->getPermissions()->isTalkAllowed() && $talkAtBottom &&
			// When showing talk at the bottom we restrict this so it is not shown to anons
			// https://phabricator.wikimedia.org/T54165
			// This whole code block can be removed when SkinOptions::TALK_AT_TOP is always true
			$this->getUser()->isRegistered()
		) {
			$namespaces = $contentNavigationUrls['associated-pages'];
			// FIXME [core]: This seems unnecessary..
			$subjectId = $title->getNamespaceKey( '' );
			$talkId = $subjectId === 'main' ? 'talk' : "{$subjectId}_talk";

			if ( isset( $namespaces[$talkId] ) ) {
				$talkButton = $namespaces[$talkId];
				$talkTitle = Title::newFromLinkTarget( $namespaceInfo->getTalkPage( $title ) );

				$buttons['talk'] = $this->getTalkButton( $talkTitle, $talkButton['text'] );
			}
		}

		if ( $languagesHelper->doesTitleHasLanguagesOrVariants( $title ) && $title->isMainPage() ) {
			$buttons['language'] = $this->getLanguageButton();
		}

		return $buttons;
	}

	/**
	 * @inheritDoc
	 * @return array
	 */
	protected function getJsConfigVars(): array {
		$title = $this->getTitle();
		$skinOptions = $this->getSkinOptions();
		$permissions = $this->getPermissions();

		return array_merge( parent::getJsConfigVars(), [
			'wgMinervaPermissions' => [
				'watchable' => $permissions->isAllowed( IMinervaPagePermissions::WATCHABLE ),
				'watch' => $permissions->isAllowed( IMinervaPagePermissions::WATCH ),
			],
			'wgMinervaFeatures' => $skinOptions->getAll(),
			'wgMinervaDownloadNamespaces' => $this->getConfig()->get( 'MinervaDownloadNamespaces' ),
		] );
	}

	/**
	 * Returns the javascript entry modules to load. Only modules that need to
	 * be overriden or added conditionally should be placed here.
	 * @return array
	 */
	public function getDefaultModules() {
		$modules = parent::getDefaultModules();

		// FIXME: T223204: Dequeue default content modules except for the history
		// action. Allow default content modules on history action in order to
		// enable toggling of the filters.
		// Long term this won't be necessary when T111565 is resolved and a
		// more general solution can be used.
		if ( $this->getContext()->getActionName() !== 'history' ) {
			// dequeue default content modules (toc, collapsible, etc.)
			$modules['content'] = array_diff( $modules['content'], [
				// T111565
				'jquery.makeCollapsible',
				// Minerva provides its own implementation. Loading this will break display.
				'mediawiki.toc'
			] );
			// dequeue styles associated with `content` key.
			$modules['styles']['content'] = array_diff( $modules['styles']['content'], [
				// T111565
				'jquery.makeCollapsible.styles',
			] );
		}
		$modules['styles']['core'] = $this->getSkinStyles();

		$modules['minerva'] = [
			'skins.minerva.scripts'
		];

		return $modules;
	}

	/**
	 * Provide styles required to present the server rendered page in this skin. Additional styles
	 * may be loaded dynamically by the client.
	 *
	 * Any styles returned by this method are loaded on the critical rendering path as linked
	 * stylesheets. I.e., they are required to load on the client before first paint.
	 *
	 * @return array
	 */
	protected function getSkinStyles(): array {
		$title = $this->getTitle();
		$skinOptions = $this->getSkinOptions();
		$request = $this->getRequest();
		$requestAction = $this->getContext()->getActionName();
		$viewAction = $requestAction === 'view';
		$styles = [
			'skins.minerva.base.styles',
			'skins.minerva.content.styles.images',
			'mediawiki.hlist',
			'codex-search-styles',
			'skins.minerva.icons.wikimedia',
			'skins.minerva.mainMenu.icons',
			'skins.minerva.mainMenu.styles',
		];

		// Warning box styles are needed when reviewing old revisions
		// and inside the fallback editor styles to action=edit page.
		if (
			$title->getNamespace() !== NS_MAIN ||
			$request->getText( 'oldid' ) ||
			!$viewAction
		) {
			$styles[] = 'skins.minerva.messageBox.styles';
		}

		if ( $title->isMainPage() ) {
			$styles[] = 'skins.minerva.mainPage.styles';
		} elseif ( $this->getUserPageHelper()->isUserPage() ) {
			$styles[] = 'skins.minerva.userpage.styles';
		}

		if ( $this->hasCategoryLinks() ) {
			$styles[] = 'skins.minerva.categories.styles';
		}

		if ( $this->getUser()->isRegistered() ) {
			$styles[] = 'skins.minerva.loggedin.styles';
		}

		// When any of these features are enabled in production
		// remove the if condition
		// and move the associated LESS file inside `skins.minerva.amc.styles`
		// into a more appropriate module.
		if (
			$skinOptions->get( SkinOptions::PERSONAL_MENU ) ||
			$skinOptions->get( SkinOptions::TALK_AT_TOP ) ||
			$skinOptions->get( SkinOptions::HISTORY_IN_PAGE_ACTIONS ) ||
			$skinOptions->get( SkinOptions::TOOLBAR_SUBMENU )
		) {
			// SkinOptions::PERSONAL_MENU + SkinOptions::TOOLBAR_SUBMENU uses ToggleList
			// SkinOptions::TALK_AT_TOP uses tabs.less
			// SkinOptions::HISTORY_IN_PAGE_ACTIONS + SkinOptions::TOOLBAR_SUBMENU uses pageactions.less
			$styles[] = 'skins.minerva.amc.styles';
		}

		if ( $skinOptions->get( SkinOptions::PERSONAL_MENU ) ) {
			// If ever enabled as the default, please remove the duplicate icons
			// inside skins.minerva.mainMenu.icons. See comment for MAIN_MENU_EXPANDED
			$styles[] = 'skins.minerva.personalMenu.icons';
		}

		if (
			$skinOptions->get( SkinOptions::MAIN_MENU_EXPANDED )
		) {
			// If ever enabled as the default, please review skins.minerva.mainMenu.icons
			// and remove any unneeded icons
			$styles[] = 'skins.minerva.mainMenu.advanced.icons';
		}
		if (
			$skinOptions->get( SkinOptions::PERSONAL_MENU ) ||
			$skinOptions->get( SkinOptions::TOOLBAR_SUBMENU )
		) {
			// SkinOptions::PERSONAL_MENU requires the `userTalk` icon.
			// SkinOptions::TOOLBAR_SUBMENU requires the rest of the icons including `overflow`.
			// Note `skins.minerva.overflow.icons` is pulled down by skins.minerva.scripts but the menu can
			// work without JS.
			$styles[] = 'skins.minerva.overflow.icons';
		}

		return $styles;
	}
}
