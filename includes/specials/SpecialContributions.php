<?php
/**
 * Implements Special:Contributions
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
 *
 * @file
 * @ingroup SpecialPage
 */

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Special:Contributions, show user contributions in a paged list
 *
 * @ingroup SpecialPage
 */
class SpecialContributions extends IncludableSpecialPage {
	protected $opts;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var NamespaceInfo */
	private $namespaceInfo;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var UserNamePrefixSearch */
	private $userNamePrefixSearch;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var CommentFormatter */
	private $commentFormatter;

	/** @var UserFactory */
	private $userFactory;

	/** @var ContribsPager|null */
	private $pager = null;

	/**
	 * @param LinkBatchFactory|null $linkBatchFactory
	 * @param PermissionManager|null $permissionManager
	 * @param ILoadBalancer|null $loadBalancer
	 * @param ActorMigration|null $actorMigration
	 * @param RevisionStore|null $revisionStore
	 * @param NamespaceInfo|null $namespaceInfo
	 * @param UserNameUtils|null $userNameUtils
	 * @param UserNamePrefixSearch|null $userNamePrefixSearch
	 * @param UserOptionsLookup|null $userOptionsLookup
	 * @param CommentFormatter|null $commentFormatter
	 * @param UserFactory|null $userFactory
	 */
	public function __construct(
		LinkBatchFactory $linkBatchFactory = null,
		PermissionManager $permissionManager = null,
		ILoadBalancer $loadBalancer = null,
		ActorMigration $actorMigration = null,
		RevisionStore $revisionStore = null,
		NamespaceInfo $namespaceInfo = null,
		UserNameUtils $userNameUtils = null,
		UserNamePrefixSearch $userNamePrefixSearch = null,
		UserOptionsLookup $userOptionsLookup = null,
		CommentFormatter $commentFormatter = null,
		UserFactory $userFactory = null
	) {
		parent::__construct( 'Contributions' );
		// This class is extended and therefore falls back to global state - T269521
		$services = MediaWikiServices::getInstance();
		$this->linkBatchFactory = $linkBatchFactory ?? $services->getLinkBatchFactory();
		$this->permissionManager = $permissionManager ?? $services->getPermissionManager();
		$this->loadBalancer = $loadBalancer ?? $services->getDBLoadBalancer();
		$this->actorMigration = $actorMigration ?? $services->getActorMigration();
		$this->revisionStore = $revisionStore ?? $services->getRevisionStore();
		$this->namespaceInfo = $namespaceInfo ?? $services->getNamespaceInfo();
		$this->userNameUtils = $userNameUtils ?? $services->getUserNameUtils();
		$this->userNamePrefixSearch = $userNamePrefixSearch ?? $services->getUserNamePrefixSearch();
		$this->userOptionsLookup = $userOptionsLookup ?? $services->getUserOptionsLookup();
		$this->commentFormatter = $commentFormatter ?? $services->getCommentFormatter();
		$this->userFactory = $userFactory ?? $services->getUserFactory();
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();
		$out = $this->getOutput();
		// Modules required for viewing the list of contributions (also when included on other pages)
		$out->addModuleStyles( [
			'jquery.makeCollapsible.styles',
			'mediawiki.interface.helpers.styles',
			'mediawiki.special',
			'mediawiki.special.changeslist',
		] );
		$out->addModules( [
			'mediawiki.special.recentchanges',
			// Certain skins e.g. Minerva might have disabled this module.
			'mediawiki.page.ready'
		] );
		$this->addHelpLink( 'Help:User contributions' );

		$this->opts = [];
		$request = $this->getRequest();

		$target = $par ?? $request->getVal( 'target', '' );

		$this->opts['deletedOnly'] = $request->getBool( 'deletedOnly' );

		if ( !strlen( $target ) ) {
			if ( !$this->including() ) {
				$out->addHTML( $this->getForm( $this->opts ) );
			}

			return;
		}

		$user = $this->getUser();

		$this->opts['limit'] = $request->getInt( 'limit', $this->userOptionsLookup->getIntOption( $user, 'rclimit' ) );
		$this->opts['target'] = $target;
		$this->opts['topOnly'] = $request->getBool( 'topOnly' );
		$this->opts['newOnly'] = $request->getBool( 'newOnly' );
		$this->opts['hideMinor'] = $request->getBool( 'hideMinor' );

		$ns = $request->getVal( 'namespace', null );
		if ( $ns !== null && $ns !== '' && $ns !== 'all' ) {
			$this->opts['namespace'] = intval( $ns );
		} else {
			$this->opts['namespace'] = '';
		}

		// Backwards compatibility: Before using OOUI form the old HTML form had
		// fields for nsInvert and associated. These have now been replaced with the
		// wpFilters query string parameters. These are retained to keep old URIs working.
		$this->opts['associated'] = $request->getBool( 'associated' );
		$this->opts['nsInvert'] = (bool)$request->getVal( 'nsInvert' );
		$nsFilters = $request->getArray( 'wpfilters', null );
		if ( $nsFilters !== null ) {
			$this->opts['associated'] = in_array( 'associated', $nsFilters );
			$this->opts['nsInvert'] = in_array( 'nsInvert', $nsFilters );
		}

		$this->opts['tagfilter'] = array_filter( explode(
			'|',
			(string)$request->getVal( 'tagfilter' )
		), static function ( $el ) {
			return $el !== '';
		} );

		// Allows reverts to have the bot flag in recent changes. It is just here to
		// be passed in the form at the top of the page
		if ( $this->permissionManager->userHasRight( $user, 'markbotedits' ) && $request->getBool( 'bot' ) ) {
			$this->opts['bot'] = '1';
		}

		$skip = $request->getText( 'offset' ) || $request->getText( 'dir' ) == 'prev';
		# Offset overrides year/month selection
		if ( !$skip ) {
			$this->opts['year'] = $request->getIntOrNull( 'year' );
			$this->opts['month'] = $request->getIntOrNull( 'month' );

			$this->opts['start'] = $request->getVal( 'start' );
			$this->opts['end'] = $request->getVal( 'end' );
		}

		$id = 0;
		if ( ExternalUserNames::isExternal( $target ) ) {
			$userObj = $this->userFactory->newFromName( $target, UserFactory::RIGOR_NONE );
			if ( !$userObj ) {
				$out->addHTML( $this->getForm( $this->opts ) );
				return;
			}

			$out->addSubtitle( $this->contributionsSub( $userObj, $target ) );
			$out->setPageTitle( $this->msg( 'contributions-title', $target )->escaped() );
		} else {
			$nt = Title::makeTitleSafe( NS_USER, $target );
			if ( !$nt ) {
				$out->addHTML( $this->getForm( $this->opts ) );
				return;
			}
			$userObj = $this->userFactory->newFromName( $nt->getText(), UserFactory::RIGOR_NONE );
			if ( !$userObj ) {
				$out->addHTML( $this->getForm( $this->opts ) );
				return;
			}
			$id = $userObj->getId();

			$target = $nt->getText();
			$out->addSubtitle( $this->contributionsSub( $userObj, $target ) );
			$out->setPageTitle( $this->msg( 'contributions-title', $target )->escaped() );

			# For IP ranges, we want the contributionsSub, but not the skin-dependent
			# links under 'Tools', which may include irrelevant links like 'Logs'.
			if ( !IPUtils::isValidRange( $target ) &&
				( $this->userNameUtils->isIP( $target ) || $userObj->isRegistered() )
			) {
				// Don't add non-existent users, because hidden users
				// that we add here will be removed later to pretend
				// that they don't exist, and if users that actually don't
				// exist are added here and then not removed, it exposes
				// which users exist and are hidden vs. which actually don't
				// exist. But, do set the relevant user for single IPs.
				$this->getSkin()->setRelevantUser( $userObj );
			}
		}

		$this->opts = ContribsPager::processDateFilter( $this->opts );

		if ( $this->opts['namespace'] !== '' && $this->opts['namespace'] < NS_MAIN ) {
			$this->getOutput()->wrapWikiMsg(
				"<div class=\"mw-negative-namespace-not-supported error\">\n\$1\n</div>",
				[ 'negative-namespace-not-supported' ]
			);
			$out->addHTML( $this->getForm( $this->opts ) );
			return;
		}

		$feedType = $request->getVal( 'feed' );

		$feedParams = [
			'action' => 'feedcontributions',
			'user' => $target,
		];
		if ( $this->opts['topOnly'] ) {
			$feedParams['toponly'] = true;
		}
		if ( $this->opts['newOnly'] ) {
			$feedParams['newonly'] = true;
		}
		if ( $this->opts['hideMinor'] ) {
			$feedParams['hideminor'] = true;
		}
		if ( $this->opts['deletedOnly'] ) {
			$feedParams['deletedonly'] = true;
		}

		if ( $this->opts['tagfilter'] !== [] ) {
			$feedParams['tagfilter'] = $this->opts['tagfilter'];
		}
		if ( $this->opts['namespace'] !== '' ) {
			$feedParams['namespace'] = $this->opts['namespace'];
		}
		// Don't use year and month for the feed URL, but pass them on if
		// we redirect to API (if $feedType is specified)
		if ( $feedType && isset( $this->opts['year'] ) ) {
			$feedParams['year'] = $this->opts['year'];
		}
		if ( $feedType && isset( $this->opts['month'] ) ) {
			$feedParams['month'] = $this->opts['month'];
		}

		if ( $feedType ) {
			// Maintain some level of backwards compatibility
			// If people request feeds using the old parameters, redirect to API
			$feedParams['feedformat'] = $feedType;
			$url = wfAppendQuery( wfScript( 'api' ), $feedParams );

			$out->redirect( $url, '301' );

			return;
		}

		// Add RSS/atom links
		$this->addFeedLinks( $feedParams );

		if ( $this->getHookRunner()->onSpecialContributionsBeforeMainOutput(
			$id, $userObj, $this )
		) {
			if ( !$this->including() ) {
				$out->addHTML( $this->getForm( $this->opts ) );
			}
			$pager = $this->getPager( $userObj );
			if ( IPUtils::isValidRange( $target ) && !$pager->isQueryableRange( $target ) ) {
				// Valid range, but outside CIDR limit.
				$limits = $this->getConfig()->get( 'RangeContributionsCIDRLimit' );
				$limit = $limits[ IPUtils::isIPv4( $target ) ? 'IPv4' : 'IPv6' ];
				$out->addWikiMsg( 'sp-contributions-outofrange', $limit );
			} else {
				// @todo We just want a wiki ID here, not a "DB domain", but
				// current status of MediaWiki conflates the two. See T235955.
				$poolKey = $this->loadBalancer->getLocalDomainID() . ':SpecialContributions:';
				if ( $this->getUser()->isAnon() ) {
					$poolKey .= 'a:' . $this->getUser()->getName();
				} else {
					$poolKey .= 'u:' . $this->getUser()->getId();
				}
				$work = new PoolCounterWorkViaCallback( 'SpecialContributions', $poolKey, [
					'doWork' => function () use ( $pager, $out, $target ) {
						if ( !$pager->getNumRows() ) {
							$out->addWikiMsg( 'nocontribs', $target );
						} else {
							# Show a message about replica DB lag, if applicable
							$lag = $pager->getDatabase()->getSessionLagStatus()['lag'];
							if ( $lag > 0 ) {
								$out->showLagWarning( $lag );
							}

							$output = $pager->getBody();
							if ( !$this->including() ) {
								$output = $pager->getNavigationBar() .
									$output .
									$pager->getNavigationBar();
							}
							$out->addHTML( $output );
						}
					},
					'error' => function () use ( $out ) {
						$msg = $this->getUser()->isAnon()
							? 'sp-contributions-concurrency-ip'
							: 'sp-contributions-concurrency-user';
						$out->wrapWikiMsg( "<div class='errorbox'>\n$1\n</div>", $msg );
					}
				] );
				$work->execute();
			}

			$out->setPreventClickjacking( $pager->getPreventClickjacking() );

			# Show the appropriate "footer" message - WHOIS tools, etc.
			if ( IPUtils::isValidRange( $target ) && $pager->isQueryableRange( $target ) ) {
				$message = 'sp-contributions-footer-anon-range';
			} elseif ( IPUtils::isIPAddress( $target ) ) {
				$message = 'sp-contributions-footer-anon';
			} elseif ( $userObj->isAnon() ) {
				// No message for non-existing users
				$message = '';
			} elseif ( $userObj->isHidden() &&
				!$this->permissionManager->userHasRight( $this->getUser(), 'hideuser' )
			) {
				// User is registered, but make sure that the viewer can see them, to avoid
				// having different behavior for missing and hidden users; see T120883
				$message = '';
			} else {
				// Not hidden, or hidden but the viewer can still see it
				$message = 'sp-contributions-footer';
			}

			if ( $message && !$this->including() && !$this->msg( $message, $target )->isDisabled() ) {
				$out->wrapWikiMsg(
					"<div class='mw-contributions-footer'>\n$1\n</div>",
					[ $message, $target ] );
			}
		}
	}

	/**
	 * Generates the subheading with links
	 * @param User $userObj User object for the target
	 * @param string $targetName This mostly the same as $userObj->getName() but
	 * normalization may make it differ. // T272225
	 * @return string Appropriately-escaped HTML to be output literally
	 * @todo FIXME: Almost the same as getSubTitle in SpecialDeletedContributions.php.
	 * Could be combined.
	 */
	protected function contributionsSub( $userObj, $targetName ) {
		$isAnon = $userObj->isAnon();
		if ( !$isAnon && $userObj->isHidden() &&
			!$this->permissionManager->userHasRight( $this->getUser(), 'hideuser' )
		) {
			// T120883 if the user is hidden and the viewer cannot see hidden
			// users, pretend like it does not exist at all.
			$isAnon = true;
		}

		if ( $isAnon ) {
			// Show a warning message that the user being searched for doesn't exist.
			// UserNameUtils::isIP returns true for IP address and usemod IPs like '123.123.123.xxx',
			// but returns false for IP ranges. We don't want to suggest either of these are
			// valid usernames which we would with the 'contributions-userdoesnotexist' message.
			if ( !$this->userNameUtils->isIP( $userObj->getName() )
				&& !IPUtils::isValidRange( $userObj->getName() )
			) {
				$this->getOutput()->addHtml( Html::warningBox(
					$this->getOutput()->msg( 'contributions-userdoesnotexist',
						wfEscapeWikiText( $userObj->getName() ) )->parse(),
					'mw-userpage-userdoesnotexist'
				) );
				if ( !$this->including() ) {
					$this->getOutput()->setStatusCode( 404 );
				}
			}
			$user = htmlspecialchars( $userObj->getName() );
		} else {
			$user = $this->getLinkRenderer()->makeLink( $userObj->getUserPage(), $userObj->getName() );
		}
		$nt = $userObj->getUserPage();
		$talk = $userObj->getTalkPage();
		$links = '';

		// T211910. Don't show action links if a range is outside block limit
		$showForIp = IPUtils::isValid( $userObj ) ||
			( IPUtils::isValidRange( $userObj ) && $this->getPager( $userObj )->isQueryableRange( $userObj ) );

		// T276306. if the user is hidden and the viewer cannot see hidden, pretend that it does not exist
		$registeredAndVisible = $userObj->isRegistered() && ( !$userObj->isHidden()
				|| $this->permissionManager->userHasRight( $this->getUser(), 'hideuser' ) );

		if ( $talk && ( $registeredAndVisible || $showForIp ) ) {
			$tools = self::getUserLinks(
				$this,
				$userObj,
				$this->permissionManager,
				$this->getHookRunner()
			);
			$links = Html::openElement( 'span', [ 'class' => 'mw-changeslist-links' ] );
			foreach ( $tools as $tool ) {
				$links .= Html::rawElement( 'span', [], $tool ) . ' ';
			}
			$links = trim( $links ) . Html::closeElement( 'span' );

			// Show a note if the user is blocked and display the last block log entry.
			// Do not expose the autoblocks, since that may lead to a leak of accounts' IPs,
			// and also this will display a totally irrelevant log entry as a current block.
			if ( !$this->including() ) {
				// For IP ranges you must give DatabaseBlock::newFromTarget the CIDR string
				// and not a user object.
				if ( IPUtils::isValidRange( $userObj->getName() ) ) {
					$block = DatabaseBlock::newFromTarget( $userObj->getName(), $userObj->getName() );
				} else {
					$block = DatabaseBlock::newFromTarget( $userObj, $userObj );
				}

				if ( $block !== null && $block->getType() != DatabaseBlock::TYPE_AUTO ) {
					if ( $block->getType() == DatabaseBlock::TYPE_RANGE ) {
						$nt = $this->namespaceInfo->getCanonicalName( NS_USER )
							. ':' . $block->getTargetName();
					}

					$out = $this->getOutput(); // showLogExtract() wants first parameter by reference
					if ( $userObj->isAnon() ) {
						$msgKey = $block->isSitewide() ?
							'sp-contributions-blocked-notice-anon' :
							'sp-contributions-blocked-notice-anon-partial';
					} else {
						$msgKey = $block->isSitewide() ?
							'sp-contributions-blocked-notice' :
							'sp-contributions-blocked-notice-partial';
					}
					// Allow local styling overrides for different types of block
					$class = $block->isSitewide() ?
						'mw-contributions-blocked-notice' :
						'mw-contributions-blocked-notice-partial';
					LogEventsList::showLogExtract(
						$out,
						'block',
						$nt,
						'',
						[
							'lim' => 1,
							'showIfEmpty' => false,
							'msgKey' => [
								$msgKey,
								$userObj->getName() # Support GENDER in 'sp-contributions-blocked-notice'
							],
							'offset' => '', # don't use WebRequest parameter offset
							'wrap' => Html::rawElement(
								'div',
								[ 'class' => $class ],
								'$1'
							),
						]
					);
				}
			}
		}

		return Html::rawElement( 'div', [ 'class' => 'mw-contributions-user-tools' ],
			$this->msg( 'contributions-subtitle' )->rawParams( $user )->params( $userObj->getName() )
			. ' ' . $links
		);
	}

	/**
	 * Links to different places.
	 *
	 * @note This function is also called in DeletedContributionsPage
	 * @param SpecialPage $sp SpecialPage instance, for context
	 * @param User $target Target user object
	 * @param PermissionManager|null $permissionManager (Since 1.36)
	 * @param HookRunner|null $hookRunner (Since 1.36)
	 * @return array
	 */
	public static function getUserLinks(
		SpecialPage $sp,
		User $target,
		PermissionManager $permissionManager = null,
		HookRunner $hookRunner = null
	) {
		// Fallback to global state, if not provided
		$permissionManager = $permissionManager ?? MediaWikiServices::getInstance()->getPermissionManager();
		$hookRunner = $hookRunner ?? Hooks::runner();

		$id = $target->getId();
		$username = $target->getName();
		$userpage = $target->getUserPage();
		$talkpage = $target->getTalkPage();
		$isIP = IPUtils::isValid( $username );
		$isRange = IPUtils::isValidRange( $username );

		$linkRenderer = $sp->getLinkRenderer();

		$tools = [];
		# No talk pages for IP ranges.
		if ( !$isRange ) {
			$tools['user-talk'] = $linkRenderer->makeLink(
				$talkpage,
				$sp->msg( 'sp-contributions-talk' )->text()
			);
		}

		# Block / Change block / Unblock links
		if ( $permissionManager->userHasRight( $sp->getUser(), 'block' ) ) {
			if ( $target->getBlock() && $target->getBlock()->getType() != DatabaseBlock::TYPE_AUTO ) {
				$tools['block'] = $linkRenderer->makeKnownLink( # Change block link
					SpecialPage::getTitleFor( 'Block', $username ),
					$sp->msg( 'change-blocklink' )->text()
				);
				$tools['unblock'] = $linkRenderer->makeKnownLink( # Unblock link
					SpecialPage::getTitleFor( 'Unblock', $username ),
					$sp->msg( 'unblocklink' )->text()
				);
			} else { # User is not blocked
				$tools['block'] = $linkRenderer->makeKnownLink( # Block link
					SpecialPage::getTitleFor( 'Block', $username ),
					$sp->msg( 'blocklink' )->text()
				);
			}
		}

		# Block log link
		$tools['log-block'] = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Log', 'block' ),
			$sp->msg( 'sp-contributions-blocklog' )->text(),
			[],
			[ 'page' => $userpage->getPrefixedText() ]
		);

		# Suppression log link (T61120)
		if ( $permissionManager->userHasRight( $sp->getUser(), 'suppressionlog' ) ) {
			$tools['log-suppression'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'Log', 'suppress' ),
				$sp->msg( 'sp-contributions-suppresslog', $username )->text(),
				[],
				[ 'offender' => $username ]
			);
		}

		# Don't show some links for IP ranges
		if ( !$isRange ) {
			# Uploads: hide if IPs cannot upload (T220674)
			if ( !$isIP || $permissionManager->userHasRight( $target, 'upload' ) ) {
				$tools['uploads'] = $linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor( 'Listfiles', $username ),
					$sp->msg( 'sp-contributions-uploads' )->text()
				);
			}

			# Other logs link
			# Todo: T146628
			$tools['logs'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'Log', $username ),
				$sp->msg( 'sp-contributions-logs' )->text()
			);

			# Add link to deleted user contributions for privileged users
			# Todo: T183457
			if ( $permissionManager->userHasRight( $sp->getUser(), 'deletedhistory' ) ) {
				$tools['deletedcontribs'] = $linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor( 'DeletedContributions', $username ),
					$sp->msg( 'sp-contributions-deleted', $username )->text()
				);
			}
		}

		# Add a link to change user rights for privileged users
		$userrightsPage = new UserrightsPage();
		$userrightsPage->setContext( $sp->getContext() );
		if ( $userrightsPage->userCanChangeRights( $target ) ) {
			$tools['userrights'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'Userrights', $username ),
				$sp->msg( 'sp-contributions-userrights', $username )->text()
			);
		}

		$hookRunner->onContributionsToolLinks( $id, $userpage, $tools, $sp );

		return $tools;
	}

	/**
	 * Generates the namespace selector form with hidden attributes.
	 * @param array $pagerOptions with keys contribs, user, deletedOnly, limit, target, topOnly,
	 *  newOnly, hideMinor, namespace, associated, nsInvert, tagfilter, year, start, end
	 * @return string HTML fragment
	 */
	protected function getForm( array $pagerOptions ) {
		$this->opts['title'] = $this->getPageTitle()->getPrefixedText();
		// Modules required only for the form
		$this->getOutput()->addModules( [
			'mediawiki.special.contributions',
		] );
		$this->getOutput()->addModuleStyles( 'mediawiki.widgets.DateInputWidget.styles' );
		$this->getOutput()->enableOOUI();
		$fields = [];

		# Add hidden params for tracking except for parameters in $skipParameters
		$skipParameters = [
			'namespace',
			'nsInvert',
			'deletedOnly',
			'target',
			'year',
			'month',
			'start',
			'end',
			'topOnly',
			'newOnly',
			'hideMinor',
			'associated',
			'tagfilter'
		];

		foreach ( $this->opts as $name => $value ) {
			if ( in_array( $name, $skipParameters ) ) {
				continue;
			}

			$fields[$name] = [
				'name' => $name,
				'type' => 'hidden',
				'default' => $value,
			];
		}

		$target = $this->opts['target'] ?? null;
		$fields['target'] = [
			'type' => 'user',
			'default' => $target ?
				str_replace( '_', ' ', $target ) : '' ,
			'label' => $this->msg( 'sp-contributions-username' )->text(),
			'name' => 'target',
			'id' => 'mw-target-user-or-ip',
			'size' => 40,
			'autofocus' => !$target,
			'section' => 'contribs-top',
		];

		$ns = $this->opts['namespace'] ?? null;
		$fields['namespace'] = [
			'type' => 'namespaceselect',
			'label' => $this->msg( 'namespace' )->text(),
			'name' => 'namespace',
			'cssclass' => 'namespaceselector',
			'default' => $ns,
			'id' => 'namespace',
			'section' => 'contribs-top',
		];
		$fields['nsFilters'] = [
			'class' => HTMLMultiSelectField::class,
			'label' => '',
			'name' => 'wpfilters',
			'flatlist' => true,
			// Only shown when namespaces are selected.
			'cssclass' => $ns === '' ?
				'contribs-ns-filters mw-input-with-label mw-input-hidden' :
				'contribs-ns-filters mw-input-with-label',
			// `contribs-ns-filters` class allows these fields to be toggled on/off by JavaScript.
			// See resources/src/mediawiki.special.recentchanges.js
			'infusable' => true,
			'options-messages' => [
				'invert' => 'nsInvert',
				'namespace_association' => 'associated',
			],
			'section' => 'contribs-top',
		];
		$fields['tagfilter'] = [
			'type' => 'tagfilter',
			'cssclass' => 'mw-tagfilter-input',
			'id' => 'tagfilter',
			'label-message' => [ 'tag-filter', 'parse' ],
			'name' => 'tagfilter',
			'size' => 20,
			'section' => 'contribs-top',
		];

		if ( $this->permissionManager->userHasRight( $this->getUser(), 'deletedhistory' ) ) {
			$fields['deletedOnly'] = [
				'type' => 'check',
				'id' => 'mw-show-deleted-only',
				'label' => $this->msg( 'history-show-deleted' )->text(),
				'name' => 'deletedOnly',
				'section' => 'contribs-top',
			];
		}

		$fields['topOnly'] = [
			'type' => 'check',
			'id' => 'mw-show-top-only',
			'label' => $this->msg( 'sp-contributions-toponly' )->text(),
			'name' => 'topOnly',
			'section' => 'contribs-top',
		];
		$fields['newOnly'] = [
			'type' => 'check',
			'id' => 'mw-show-new-only',
			'label' => $this->msg( 'sp-contributions-newonly' )->text(),
			'name' => 'newOnly',
			'section' => 'contribs-top',
		];
		$fields['hideMinor'] = [
			'type' => 'check',
			'cssclass' => 'mw-hide-minor-edits',
			'id' => 'mw-show-new-only',
			'label' => $this->msg( 'sp-contributions-hideminor' )->text(),
			'name' => 'hideMinor',
			'section' => 'contribs-top',
		];

		// Allow additions at this point to the filters.
		$rawFilters = [];
		$this->getHookRunner()->onSpecialContributions__getForm__filters(
			$this, $rawFilters );
		foreach ( $rawFilters as $filter ) {
			// Backwards compatibility support for previous hook function signature.
			if ( is_string( $filter ) ) {
				$fields[] = [
					'type' => 'info',
					'default' => $filter,
					'raw' => true,
					'section' => 'contribs-top',
				];
				wfDeprecatedMsg(
					'A SpecialContributions::getForm::filters hook handler returned ' .
					'an array of strings, this is deprecated since MediaWiki 1.33',
					'1.33', false, false
				);
			} else {
				// Preferred append method.
				$fields[] = $filter;
			}
		}

		$fields['start'] = [
			'type' => 'date',
			'default' => '',
			'id' => 'mw-date-start',
			'label' => $this->msg( 'date-range-from' )->text(),
			'name' => 'start',
			'section' => 'contribs-date',
		];
		$fields['end'] = [
			'type' => 'date',
			'default' => '',
			'id' => 'mw-date-end',
			'label' => $this->msg( 'date-range-to' )->text(),
			'name' => 'end',
			'section' => 'contribs-date',
		];

		$htmlForm = HTMLForm::factory( 'ooui', $fields, $this->getContext() );
		$htmlForm
			->setMethod( 'get' )
			// When offset is defined, the user is paging through results
			// so we hide the form by default to allow users to focus on browsing
			// rather than defining search parameters
			->setCollapsibleOptions(
				( $pagerOptions['target'] ?? null ) ||
				( $pagerOptions['start'] ?? null ) ||
				( $pagerOptions['end'] ?? null )
			)
			->setAction( wfScript() )
			->setSubmitTextMsg( 'sp-contributions-submit' )
			->setWrapperLegendMsg( 'sp-contributions-search' );

		$explain = $this->msg( 'sp-contributions-explain' );
		if ( !$explain->isBlank() ) {
			$htmlForm->addFooterText( "<p id='mw-sp-contributions-explain'>{$explain->parse()}</p>" );
		}

		$htmlForm->loadData();

		return $htmlForm->getHTML( false );
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$search = $this->userNameUtils->getCanonical( $search );
		if ( !$search ) {
			// No prefix suggestion for invalid user
			return [];
		}
		// Autocomplete subpage as user list - public to allow caching
		return $this->userNamePrefixSearch
			->search( UserNamePrefixSearch::AUDIENCE_PUBLIC, $search, $limit, $offset );
	}

	/**
	 * @param User $targetUser The normalized target user
	 * @return ContribsPager
	 */
	private function getPager( $targetUser ) {
		if ( $this->pager === null ) {
			$options = [
				'namespace' => $this->opts['namespace'],
				'tagfilter' => $this->opts['tagfilter'],
				'start' => $this->opts['start'] ?? '',
				'end' => $this->opts['end'] ?? '',
				'deletedOnly' => $this->opts['deletedOnly'],
				'topOnly' => $this->opts['topOnly'],
				'newOnly' => $this->opts['newOnly'],
				'hideMinor' => $this->opts['hideMinor'],
				'nsInvert' => $this->opts['nsInvert'],
				'associated' => $this->opts['associated'],
			];

			$this->pager = new ContribsPager(
				$this->getContext(),
				$options,
				$this->getLinkRenderer(),
				$this->linkBatchFactory,
				$this->getHookContainer(),
				$this->loadBalancer,
				$this->actorMigration,
				$this->revisionStore,
				$this->namespaceInfo,
				$targetUser,
				$this->commentFormatter
			);
		}

		return $this->pager;
	}

	protected function getGroupName() {
		return 'users';
	}
}
