<?php

namespace MediaWiki\CheckUser\CheckUser\Pagers;

use LogicException;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\CheckUser\Widgets\HTMLFieldsetCheckUser;
use MediaWiki\CheckUser\ClientHints\ClientHintsLookupResults;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CheckUser\Services\CheckUserUtilityService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Html\FormOptions;
use MediaWiki\Html\Html;
use MediaWiki\Html\ListToggle;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use MediaWiki\Xml\Xml;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

class CheckUserGetUsersPager extends AbstractCheckUserPager {
	/** @var bool Whether the user performing this check has the block right. */
	protected bool $canPerformBlocks;

	/**
	 * @var bool|null Lazy-loaded via ::shouldShowBlockFieldset. Whether the block fieldset
	 *   (and checkboxes next to each result line) should be shown. Null if not yet determined.
	 */
	private ?bool $shouldShowBlockFieldset = null;

	/** @var array[] */
	protected $userSets;

	/** @var string|false */
	private $centralAuthToollink;

	/** @var array|false */
	private $globalBlockingToollink;

	/** @var string[][] */
	private $aliases;

	private ClientHintsLookupResults $clientHintsLookupResults;

	private PermissionManager $permissionManager;
	private UserEditTracker $userEditTracker;
	private CheckUserUtilityService $checkUserUtilityService;
	private UserAgentClientHintsLookup $clientHintsLookup;
	private UserAgentClientHintsFormatter $clientHintsFormatter;
	private ExtensionRegistry $extensionRegistry;
	private LinkBatchFactory $linkBatchFactory;

	public function __construct(
		FormOptions $opts,
		UserIdentity $target,
		bool $xfor,
		string $logType,
		TokenQueryManager $tokenQueryManager,
		PermissionManager $permissionManager,
		UserGroupManager $userGroupManager,
		CentralIdLookup $centralIdLookup,
		IConnectionProvider $dbProvider,
		SpecialPageFactory $specialPageFactory,
		UserIdentityLookup $userIdentityLookup,
		UserFactory $userFactory,
		CheckUserLogService $checkUserLogService,
		CheckUserLookupUtils $checkUserLookupUtils,
		UserEditTracker $userEditTracker,
		CheckUserUtilityService $checkUserUtilityService,
		UserAgentClientHintsLookup $clientHintsLookup,
		UserAgentClientHintsFormatter $clientHintsFormatter,
		UserOptionsLookup $userOptionsLookup,
		DatabaseBlockStore $blockStore,
		LinkBatchFactory $linkBatchFactory,
		?IContextSource $context = null,
		?LinkRenderer $linkRenderer = null,
		?int $limit = null
	) {
		parent::__construct( $opts, $target, $logType, $tokenQueryManager,
			$userGroupManager, $centralIdLookup, $dbProvider, $specialPageFactory,
			$userIdentityLookup, $checkUserLogService, $userFactory, $checkUserLookupUtils,
			$userOptionsLookup, $blockStore, $context, $linkRenderer, $limit );
		$this->checkType = SpecialCheckUser::SUBTYPE_GET_USERS;
		$this->xfor = $xfor;
		$this->canPerformBlocks = $permissionManager->userHasRight( $this->getUser(), 'block' )
			&& !$this->getUser()->getBlock();
		$this->centralAuthToollink = ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' )
			? $this->getConfig()->get( 'CheckUserCAtoollink' ) : false;
		$this->globalBlockingToollink = ExtensionRegistry::getInstance()->isLoaded( 'GlobalBlocking' )
			? $this->getConfig()->get( 'CheckUserGBtoollink' ) : false;
		$this->aliases = $this->getLanguage()->getSpecialPageAliases();
		$this->permissionManager = $permissionManager;
		$this->userEditTracker = $userEditTracker;
		$this->checkUserUtilityService = $checkUserUtilityService;
		$this->clientHintsLookup = $clientHintsLookup;
		$this->clientHintsFormatter = $clientHintsFormatter;
		$this->extensionRegistry = ExtensionRegistry::getInstance();
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * Returns nothing as formatUserRow
	 * is instead used.
	 *
	 * @inheritDoc
	 */
	public function formatRow( $row ): string {
		return '';
	}

	/** @inheritDoc */
	public function getBody() {
		$this->getOutput()->addModuleStyles( $this->getModuleStyles() );
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}

		if ( $this->mResult->numRows() ) {
			# Do any special query batches before display
			$this->doBatchLookups();
		}

		# Don't use any extra rows returned by the query
		$numRows = count( $this->userSets['ids'] );

		$s = $this->getStartBody();
		if ( $numRows ) {
			$keys = array_keys( $this->userSets['ids'] );
			if ( $this->mIsBackwards ) {
				$keys = array_reverse( $keys );
			}
			foreach ( $keys as $user_text ) {
				$s .= $this->formatUserRow( $user_text );
			}
			$s .= $this->getFooter();
		} else {
			$s .= $this->getEmptyBody();
		}
		$s .= $this->getEndBody();
		return $s;
	}

	/**
	 * Gets a row for the results for 'Get users'
	 *
	 * @param string $user_text the username for the current row.
	 * @return string
	 */
	public function formatUserRow( string $user_text ): string {
		$templateParams = [];

		$userIsIP = IPUtils::isIPAddress( $user_text );

		// Load user object
		$user = new UserIdentityValue(
			$this->userSets['ids'][$user_text],
			$userIsIP ? IPUtils::prettifyIP( $user_text ) ?? $user_text : $user_text
		);
		$hidden = $this->userFactory->newFromUserIdentity( $user )->isHidden()
			&& !$this->getAuthority()->isAllowed( 'hideuser' );
		if ( $hidden ) {
			// User is hidden from the current authority, so the current authority cannot block this user either.
			// As such, the checkbox (used for blocking the user) should not be shown.
			$templateParams['canPerformBlocksOrLocks'] = false;
			$templateParams['userText'] = '';
			$templateParams['userLink'] = Html::element(
				'span',
				[ 'class' => 'history-deleted' ],
				$this->msg( 'rev-deleted-user' )->text()
			);
		} else {
			$templateParams['canPerformBlocksOrLocks'] = $this->shouldShowBlockFieldset();
			$templateParams['userText'] = $user->getName();
			$userNonExistent = !IPUtils::isIPAddress( $user ) && !$user->isRegistered();
			if ( $userNonExistent ) {
				$templateParams['userLinkClass'] = 'mw-checkuser-nonexistent-user';
			}
			$templateParams['userLink'] = Linker::userLink( $user->getId(), $user, $user );
			$templateParams['userToolLinks'] = Linker::userToolLinksRedContribs(
				$user->getId(),
				$user,
				$this->userEditTracker->getUserEditCount( $user ),
				// don't render parentheses in HTML markup (CSS will provide)
				false
			);
			if ( $userIsIP ) {
				$templateParams['userLinks'] = $this->msg( 'checkuser-userlinks-ip', $user )->parse();
			} elseif ( !$userNonExistent ) {
				if ( $this->msg( 'checkuser-userlinks' )->exists() ) {
					$templateParams['userLinks'] =
						$this->msg( 'checkuser-userlinks', htmlspecialchars( $user ) )->parse();
				}
			}
			// Add global user tools links
			// Add CentralAuth link for real registered users
			if ( $this->centralAuthToollink !== false
				&& !$userIsIP
				&& !$userNonExistent
			) {
				// Get CentralAuth SpecialPage name in UserLang from the first Alias name
				$spca = $this->aliases['CentralAuth'][0];
				$calinkAlias = str_replace( '_', ' ', $spca );
				$centralCAUrl = WikiMap::getForeignURL(
					$this->centralAuthToollink,
					'Special:CentralAuth'
				);
				if ( $centralCAUrl === false ) {
					throw new ConfigException(
						"Could not retrieve URL for CentralAuth: $this->centralAuthToollink"
					);
				}
				$linkCA = Html::element( 'a',
					[
						'href' => $centralCAUrl . "/" . $user,
						'title' => $this->msg( 'centralauth' )->text(),
					],
					$calinkAlias
				);
				$templateParams['centralAuthLink'] = $this->msg( 'parentheses' )->rawParams( $linkCA )->escaped();
			}
			// Add GlobalBlocking link to CentralWiki
			if ( $this->globalBlockingToollink !== false ) {
				// Get GlobalBlock SpecialPage name in UserLang from the first Alias name
				$centralGBUrl = WikiMap::getForeignURL(
					$this->globalBlockingToollink['centralDB'],
					'Special:GlobalBlock'
				);
				$spgb = $this->aliases['GlobalBlock'][0];
				$gblinkAlias = str_replace( '_', ' ', $spgb );
				if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
					// If CentralAuth is installed, we can ue the global groups of the CentralUser.
					$gbUserGroups = CentralAuthUser::getInstance( $this->getUser() )->getGlobalGroups();
				} elseif ( $centralGBUrl !== false ) {
					// Case wikimap configured without CentralAuth extension
					// Get effective Local user groups since there is a wikimap but there is no CA
					$gbUserGroups = $this->userGroupManager->getUserEffectiveGroups( $this->getUser() );
				} else {
					// If CentralAuth is not installed and also if the central Special:GlobalBlock page failed to be
					// generated, then check that the user has the globalblock right locally instead of the user group
					// check.
					$gbUserGroups = [ '' ];

					$gbUserCanDo = $this->permissionManager->userHasRight( $this->getUser(), 'globalblock' );
					if ( $gbUserCanDo ) {
						$this->globalBlockingToollink['groups'] = $gbUserGroups;
					}
				}
				// Only load the script for users in the configured global(local) group(s) or
				// for local user with globalblock permission if there is no WikiMap
				if ( count( array_intersect( $this->globalBlockingToollink['groups'], $gbUserGroups ) ) ) {
					if ( $centralGBUrl !== false ) {
						$linkGB = Html::element( 'a',
							[
								'href' => $centralGBUrl . "/" . $user,
								'title' => $this->msg( 'globalblocking-block-submit-new' )->text(),
							],
							$gblinkAlias
						);
					} else {
						// If we could not generate the URL for Special:GlobalBlock on the central wiki, we should be
						// able to use the local Special:GlobalBlock page as a backup.
						$gbtitle = $this->getPageTitle( 'GlobalBlock' );
						$linkGB = $this->getLinkRenderer()->makeKnownLink(
							$gbtitle,
							$gblinkAlias,
							[ 'title' => $this->msg( 'globalblocking-block-submit-new' ) ]
						);
					}
					$templateParams['globalBlockLink'] = $this->msg( 'parentheses' )->rawParams( $linkGB )->escaped();
				}
			}
			// Check if this user or IP is blocked. If so, give a link to the block log...
			$templateParams['flags'] = $this->userBlockFlags( $userIsIP ? $user : '', $user );
		}
		// Show edit time range
		$templateParams['timeRange'] = $this->getTimeRangeString(
			$this->userSets['first'][$user_text],
			$this->userSets['last'][$user_text]
		);
		// Total edit count
		$templateParams['editCount'] = $this->userSets['edits'][$user_text];
		// List out each IP/XFF combo for this username
		$templateParams['infoSets'] = [];
		for ( $i = ( count( $this->userSets['infosets'][$user_text] ) - 1 ); $i >= 0; $i-- ) {
			// users_infosets[$name][$i] is array of [ $row->ip, XFF ];
			$row = [];
			[ $clientIP, $xffString ] = $this->userSets['infosets'][$user_text][$i];
			// IP link
			$row['ipLink'] = $this->getSelfLink( $clientIP, [ 'user' => $clientIP ] );
			// XFF string, link to /xff search
			if ( $xffString ) {
				// Flag our trusted proxies
				[ $client ] = $this->checkUserUtilityService->getClientIPfromXFF( $xffString );
				// XFF was trusted if client came from it
				$trusted = ( $client === $clientIP );
				$row['xffTrusted'] = $trusted;
				$row['xff'] = $this->getSelfLink( $xffString, [ 'user' => $client . '/xff' ] );
			}
			$templateParams['infoSets'][] = $row;
		}
		// List out each agent for this username
		for ( $i = ( count( $this->userSets['agentsets'][$user_text] ) - 1 ); $i >= 0; $i-- ) {
			$templateParams['agentsList'][] = $this->userSets['agentsets'][$user_text][$i];
		}
		// Show Client Hints data if display is enabled.
		$templateParams['displayClientHints'] = $this->displayClientHints;
		if ( $this->displayClientHints ) {
			$templateParams['clientHintsList'] = [];
			[ $usagesOfClientHints, $clientHintsDataObjects ] = $this->clientHintsLookupResults
				->getGroupedClientHintsDataForReferenceIds( $this->userSets['clienthints'][$user_text] );
			// Sort the $usagesOfClientHints array such that the ClientHintsData object that is most used
			// by the user referenced in $user_text is shown first and the ClientHintsData object least used is
			// shown last. This is done to be consistent with the way that User-Agent strings are shown as well
			// as ensuring that if there are more than 10 items the ClientHintsData objects used on the most reference
			// IDs are shown.
			arsort( $usagesOfClientHints, SORT_NUMERIC );
			// Limit the number displayed to at most 10 starting at the
			// ClientHintsData object associated with the most rows
			// in the results. This is to be consistent with User-Agent
			// strings which are also limited to 10 strings.
			$i = 0;
			foreach ( array_keys( $usagesOfClientHints ) as $clientHintsDataIndex ) {
				// If 10 Client Hints data objects have been displayed,
				// then don't show any more (similar to User-Agent strings).
				if ( $i === 10 ) {
					break;
				}
				$clientHintsDataObject = $clientHintsDataObjects[$clientHintsDataIndex];
				if ( $clientHintsDataObject ) {
					$formattedClientHintsData = $this->clientHintsFormatter
						->formatClientHintsDataObject( $clientHintsDataObject );
					if ( $formattedClientHintsData ) {
						// If the Client Hints data object is valid and evaluates to a non-empty
						// human readable string, then add it to the list to display.
						$i++;
						$templateParams['clientHintsList'][] = $formattedClientHintsData;
					}
				}
			}
		}
		return $this->templateParser->processTemplate( 'GetUsersLine', $templateParams );
	}

	/** @inheritDoc */
	protected function preprocessResults( $result ) {
		$this->userSets = [
			'first' => [],
			'last' => [],
			'edits' => [],
			'ids' => [],
			'infosets' => [],
			'agentsets' => [],
			'clienthints' => [],
		];
		$referenceIdsForLookup = new ClientHintsReferenceIds();

		$batch = $this->linkBatchFactory->newLinkBatch();
		$batch->setCaller( __METHOD__ );

		foreach ( $result as $row ) {
			// Use the IP as the user_text if the actor ID is NULL and the IP is not NULL (T353953).
			if ( $row->actor === null && $row->ip ) {
				$row->user_text = $row->ip;
			}

			if ( !array_key_exists( $row->user_text, $this->userSets['edits'] ) ) {
				$user = new UserIdentityValue( $row->user ?? 0, $row->user_text );
				$batch->addUser( $user );

				$this->userSets['last'][$row->user_text] = $row->timestamp;
				$this->userSets['edits'][$row->user_text] = 0;
				$this->userSets['ids'][$row->user_text] = $row->user ?? 0;
				$this->userSets['infosets'][$row->user_text] = [];
				$this->userSets['agentsets'][$row->user_text] = [];
				$this->userSets['clienthints'][$row->user_text] = new ClientHintsReferenceIds();
			}
			if ( $this->displayClientHints ) {
				$referenceIdsForLookup->addReferenceIds(
					$row->client_hints_reference_id,
					$row->client_hints_reference_type
				);
				$this->userSets['clienthints'][$row->user_text]->addReferenceIds(
					$row->client_hints_reference_id,
					$row->client_hints_reference_type
				);
			}
			$this->userSets['edits'][$row->user_text]++;
			$this->userSets['first'][$row->user_text] = $row->timestamp;
			// Prettify IP
			$formattedIP = IPUtils::prettifyIP( $row->ip ) ?? $row->ip;
			// Treat blank or NULL xffs as empty strings
			$xff = empty( $row->xff ) ? null : $row->xff;
			$xff_ip_combo = [ $formattedIP, $xff ];
			// Add this IP/XFF combo for this username if it's not already there
			if ( !in_array( $xff_ip_combo, $this->userSets['infosets'][$row->user_text] ) ) {
				$this->userSets['infosets'][$row->user_text][] = $xff_ip_combo;
			}
			// Add this agent string if it's not already there; 10 max.
			if ( count( $this->userSets['agentsets'][$row->user_text] ) < 10 ) {
				if ( !in_array( $row->agent, $this->userSets['agentsets'][$row->user_text] ) ) {
					$this->userSets['agentsets'][$row->user_text][] = $row->agent;
				}
			}
		}

		$batch->execute();

		// Lookup the Client Hints data objects from the DB
		// and then batch format the ClientHintsData objects
		// for display.
		if ( $this->displayClientHints ) {
			$this->clientHintsLookupResults = $this->clientHintsLookup
				->getClientHintsByReferenceIds( $referenceIdsForLookup );
		}
	}

	/** @inheritDoc */
	public function getQueryInfo( ?string $table = null ): array {
		if ( $table === null ) {
			throw new LogicException(
				"This ::getQueryInfo method must be provided with the table to generate " .
				"the correct query info"
			);
		}

		if ( $table === self::CHANGES_TABLE ) {
			$queryInfo = $this->getQueryInfoForCuChanges();
		} elseif ( $table === self::LOG_EVENT_TABLE ) {
			$queryInfo = $this->getQueryInfoForCuLogEvent();
		} elseif ( $table === self::PRIVATE_LOG_EVENT_TABLE ) {
			$queryInfo = $this->getQueryInfoForCuPrivateEvent();
		}

		// Apply index and IP WHERE conditions.
		$queryInfo['options']['USE INDEX'] = [
			$table => $this->checkUserLookupUtils->getIndexName( $this->xfor, $table )
		];
		$ipExpr = $this->checkUserLookupUtils->getIPTargetExpr( $this->target->getName(), $this->xfor, $table );
		if ( $ipExpr !== null ) {
			$queryInfo['conds'][] = $ipExpr;
		}

		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuChanges(): array {
		$queryInfo = [
			'fields' => [
				'timestamp' => 'cuc_timestamp',
				'ip' => 'cuc_ip',
				'agent' => 'cuc_agent',
				'xff' => 'cuc_xff',
				'actor' => 'cuc_actor',
				'user' => 'actor_cuc_actor.actor_user',
				'user_text' => 'actor_cuc_actor.actor_name',
			],
			'tables' => [ 'cu_changes', 'actor_cuc_actor' => 'actor' ],
			'conds' => [],
			'join_conds' => [ 'actor_cuc_actor' => [ 'JOIN', 'actor_cuc_actor.actor_id=cuc_actor' ] ],
			'options' => [],
		];
		// When displaying Client Hints data, add the reference type and reference ID to each row.
		if ( $this->displayClientHints ) {
			$queryInfo['fields']['client_hints_reference_id'] =
				UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES
				];
			$queryInfo['fields']['client_hints_reference_type'] =
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES;
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuLogEvent(): array {
		$queryInfo = [
			'fields' => [
				'timestamp' => 'cule_timestamp',
				'ip' => 'cule_ip',
				'agent' => 'cule_agent',
				'xff' => 'cule_xff',
				'actor' => 'cule_actor',
				'user' => 'actor_cule_actor.actor_user',
				'user_text' => 'actor_cule_actor.actor_name',
			],
			'tables' => [ 'cu_log_event', 'actor_cule_actor' => 'actor' ],
			'conds' => [],
			'join_conds' => [ 'actor_cule_actor' => [ 'JOIN', 'actor_cule_actor.actor_id=cule_actor' ] ],
			'options' => [],
		];
		// When displaying Client Hints data, add the reference type and reference ID to each row.
		if ( $this->displayClientHints ) {
			$queryInfo['fields']['client_hints_reference_id'] =
				UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT
				];
			$queryInfo['fields']['client_hints_reference_type'] =
				UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT;
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuPrivateEvent(): array {
		$queryInfo = [
			'fields' => [
				'timestamp' => 'cupe_timestamp',
				'ip' => 'cupe_ip',
				'agent' => 'cupe_agent',
				'xff' => 'cupe_xff',
				'actor' => 'cupe_actor',
				'user' => 'actor_cupe_actor.actor_user',
				'user_text' => 'actor_cupe_actor.actor_name',
			],
			'tables' => [ 'cu_private_event', 'actor_cupe_actor' => 'actor' ],
			'conds' => [],
			'join_conds' => [ 'actor_cupe_actor' => [ 'LEFT JOIN', 'actor_cupe_actor.actor_id=cupe_actor' ] ],
			'options' => [],
		];
		// When displaying Client Hints data, add the reference type and reference ID to each row.
		if ( $this->displayClientHints ) {
			$queryInfo['fields']['client_hints_reference_id'] =
				UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[
					UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT
				];
			$queryInfo['fields']['client_hints_reference_type'] =
				UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT;
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getStartBody(): string {
		$s = $this->getCheckUserHelperFieldsetHTML() . $this->getNavigationBar();
		if ( $this->shouldShowBlockFieldset() ) {
			$s .= ( new ListToggle( $this->getOutput() ) )->getHTML();
		}

		$divClasses = [ 'mw-checkuser-get-users-results' ];

		if ( $this->displayClientHints ) {
			// Class used to indicate whether Client Hints are enabled
			// TODO: Remove this class and old CSS code once display
			// is on all wikis (T341110).
			$divClasses[] = 'mw-checkuser-clienthints-enabled-temporary-class';
		}

		$s .= Xml::openElement(
			'div',
			[
				'id' => 'checkuserresults',
				'class' => implode( ' ', $divClasses )
			]
		);

		$s .= '<ul>';

		return $s;
	}

	/**
	 * Should the block fieldset and checkboxes be shown to the user. Has non-trivial side-effects
	 * (e.g. adding JS modules), but calling this method more than once does not repeat the side-effects.
	 *
	 * @return bool Whether the block fieldset should be added to the bottom of the results, and also whether the
	 *   checkboxes next to each result line should be shown.
	 */
	private function shouldShowBlockFieldset(): bool {
		// If this method has already been called, return the cached value.
		if ( $this->shouldShowBlockFieldset !== null ) {
			return $this->shouldShowBlockFieldset;
		}
		// If there are no results, then there will be no IPs or users to block. Therefore, no need to show the
		// block fieldset.
		if ( !$this->getNumRows() ) {
			return false;
		}
		// Add links to the MultiLock tool if the user can use it.
		$checkUserCAMultiLock = $this->getConfig()->get( 'CheckUserCAMultiLock' );
		if ( $checkUserCAMultiLock !== false ) {
			if ( !$this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
				// $wgCheckUserCAMultiLock shouldn't be enabled if CA is not loaded
				throw new ConfigException( '$wgCheckUserCAMultiLock requires CentralAuth extension.' );
			}

			$caUserGroups = CentralAuthUser::getInstance( $this->getUser() )->getGlobalGroups();
			// Only load the script for users in the configured global group(s)
			if ( count( array_intersect( $checkUserCAMultiLock['groups'], $caUserGroups ) ) ) {
				$centralMLUrl = WikiMap::getForeignURL(
					$checkUserCAMultiLock['centralDB'],
					// Use canonical name instead of local name so that it works
					// even if the local language is different from central wiki
					'Special:MultiLock'
				);
				if ( $centralMLUrl === false ) {
					throw new ConfigException(
						"Could not retrieve URL for {$checkUserCAMultiLock['centralDB']}"
					);
				}
				$this->getOutput()->addJsConfigVars( 'wgCUCAMultiLockCentral', $centralMLUrl );
				$this->getOutput()->addModules( 'ext.checkUser' );
				// Always show the block form if links to Special:MultiLock are going to be added to the page.
				$this->shouldShowBlockFieldset = true;
				return $this->shouldShowBlockFieldset;
			}
		}
		// If the Special:MultiLock links are not being added, then only show the block form if the user can
		// perform blocks on the local wiki.
		$this->shouldShowBlockFieldset = $this->canPerformBlocks;
		return $this->shouldShowBlockFieldset;
	}

	/** @inheritDoc */
	protected function getEndBody(): string {
		$s = '</ul></div>';
		if ( $this->shouldShowBlockFieldset() ) {
			$s .= ( new ListToggle( $this->getOutput() ) )->getHTML();
		}
		$s .= $this->getNavigationBar();
		if ( $this->shouldShowBlockFieldset() ) {
			$fieldset = new HTMLFieldsetCheckUser( [], $this->getContext(), '' );
			$fieldset->outerClass = 'mw-checkuser-massblock';
			if ( $this->canPerformBlocks ) {
				$fieldset->addFields( [
					'block-accounts-button' => [
						'type' => 'submit',
						'buttonlabel-message' => 'checkuser-massblock-commit-accounts',
						'cssclass' => 'mw-checkuser-massblock-accounts-button mw-checkuser-massblock-button',
					],
					'block-ips-button' => [
						'type' => 'submit',
						'buttonlabel-message' => 'checkuser-massblock-commit-ips',
						'cssclass' => 'mw-checkuser-massblock-ips-button mw-checkuser-massblock-button',
					],
				] )->setHeaderHtml( $this->msg( 'checkuser-massblock-text' )->escaped() );
			} else {
				$fieldset->setHeaderHtml( $this->msg( 'checkuser-massblock-text-multi-lock-only' )->escaped() );
			}

			$s .= $fieldset->setWrapperLegendMsg( 'checkuser-massblock' )
				->suppressDefaultSubmit()
				->prepareForm()
				->getHtml( false );
		}

		return $s;
	}
}
