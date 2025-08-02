<?php

namespace MediaWiki\CheckUser\CheckUser;

use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetActionsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\CheckUser\CheckUser\Widgets\CIDRCalculator;
use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CheckUser\Services\CheckUserUtilityService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Html\FormOptions;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logging\LogFormatterFactory;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use OOUI\IconWidget;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

class SpecialCheckUser extends SpecialPage {
	/**
	 * The possible subtypes represented as constants.
	 * The constants represent the old string values
	 * for backwards compatibility.
	 */
	public const SUBTYPE_GET_IPS = 'subuserips';

	public const SUBTYPE_GET_ACTIONS = 'subactions';

	public const SUBTYPE_GET_USERS = 'subipusers';

	/**
	 * @var FormOptions the form parameters.
	 */
	protected $opts;

	private LinkBatchFactory $linkBatchFactory;
	private UserGroupManager $userGroupManager;
	private CentralIdLookup $centralIdLookup;
	private PermissionManager $permissionManager;
	private UserIdentityLookup $userIdentityLookup;
	private TokenQueryManager $tokenQueryManager;
	private IConnectionProvider $dbProvider;
	private UserFactory $userFactory;
	private CheckUserLogService $checkUserLogService;
	private CommentFormatter $commentFormatter;
	private UserEditTracker $userEditTracker;
	private UserNamePrefixSearch $userNamePrefixSearch;
	private UserNameUtils $userNameUtils;
	private HookRunner $hookRunner;
	private CheckUserUtilityService $checkUserUtilityService;
	private CommentStore $commentStore;
	private UserAgentClientHintsLookup $clientHintsLookup;
	private UserAgentClientHintsFormatter $clientHintsFormatter;
	private CheckUserLookupUtils $checkUserLookupUtils;
	private LogFormatterFactory $logFormatterFactory;
	private UserOptionsLookup $userOptionsLookup;
	private DatabaseBlockStore $blockStore;

	public function __construct(
		LinkBatchFactory $linkBatchFactory,
		UserGroupManager $userGroupManager,
		CentralIdLookupFactory $centralIdLookupFactory,
		PermissionManager $permissionManager,
		UserIdentityLookup $userIdentityLookup,
		TokenQueryManager $tokenQueryManager,
		IConnectionProvider $dbProvider,
		UserFactory $userFactory,
		CheckUserLogService $checkUserLogService,
		CommentFormatter $commentFormatter,
		UserEditTracker $userEditTracker,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserNameUtils $userNameUtils,
		HookRunner $hookRunner,
		CheckUserUtilityService $checkUserUtilityService,
		CommentStore $commentStore,
		UserAgentClientHintsLookup $clientHintsLookup,
		UserAgentClientHintsFormatter $clientHintsFormatter,
		CheckUserLookupUtils $checkUserLookupUtils,
		LogFormatterFactory $logFormatterFactory,
		UserOptionsLookup $userOptionsLookup,
		DatabaseBlockStore $blockStore
	) {
		parent::__construct( 'CheckUser', 'checkuser' );

		$this->linkBatchFactory = $linkBatchFactory;
		$this->userGroupManager = $userGroupManager;
		$this->centralIdLookup = $centralIdLookupFactory->getLookup();
		$this->permissionManager = $permissionManager;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->dbProvider = $dbProvider;
		$this->userFactory = $userFactory;
		$this->checkUserLogService = $checkUserLogService;
		$this->commentFormatter = $commentFormatter;
		$this->userEditTracker = $userEditTracker;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userNameUtils = $userNameUtils;
		$this->hookRunner = $hookRunner;
		$this->checkUserUtilityService = $checkUserUtilityService;
		$this->commentStore = $commentStore;
		$this->clientHintsLookup = $clientHintsLookup;
		$this->clientHintsFormatter = $clientHintsFormatter;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$this->logFormatterFactory = $logFormatterFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->blockStore = $blockStore;
	}

	/** @inheritDoc */
	public function doesWrites() {
		// logging
		return true;
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->addHelpLink( 'Help:Extension:CheckUser' );
		$this->checkPermissions();
		// Logging and blocking requires writing so stop from here if read-only mode
		$this->checkReadOnly();

		// Blocked users are not allowed to run checkuser queries (bug T157883)
		$block = $this->getUser()->getBlock();
		if ( $block && $block->isSitewide() ) {
			throw new UserBlockedError( $block );
		}

		$request = $this->getRequest();

		$opts = new FormOptions();
		$opts->add( 'reason', '' );
		$opts->add( 'checktype', '' );
		$opts->add( 'period', 0 );
		$opts->add( 'offset', '' );
		$opts->add( 'limit', 0 );
		$opts->add( 'dir', '' );
		$opts->add( 'token', '' );
		$opts->fetchValuesFromRequest( $request );

		// If the client has provided a token, they are trying to paginate.
		//  If the token is valid, then use the values from this and later
		//  don't log this as a new check.
		$tokenData = $this->tokenQueryManager->getDataFromRequest( $this->getRequest() );
		$validatedRequest = $this->getRequest();
		$user = '';
		if ( $tokenData ) {
			foreach (
				array_diff( AbstractCheckUserPager::TOKEN_MANAGED_FIELDS, array_keys( $tokenData ) ) as $key
			) {
				$opts->reset( $key );
				$validatedRequest->unsetVal( $key );
			}
			foreach ( $tokenData as $key => $value ) {
				// Update the FormOptions
				if ( $key === 'user' ) {
					$user = $value;
				} else {
					$opts->setValue( $key, $value, true );
				}
				// Update the actual request so that IndexPager.php reads the validated values.
				//  (used for dir, offset and limit)
				$validatedRequest->setVal( $key, $value );
			}
		} else {
			$user = trim(
				$request->getText( 'user', $request->getText( 'ip', $subPage ?? '' ) )
			);
		}
		$this->getContext()->setRequest( $validatedRequest );
		$this->opts = $opts;

		// Normalise 'user' parameter and ignore if not valid (T217713)
		// It must be valid when making a link to Special:CheckUserLog/<user>.
		$userTitle = Title::makeTitleSafe( NS_USER, $user );
		$user = $userTitle ? $userTitle->getText() : '';

		$out = $this->getOutput();
		$links = [];
		$out->enableOOUI();
		$out->addModuleStyles( 'oojs-ui.styles.icons-interactions' );
		$icon = new IconWidget( [ 'icon' => 'lightbulb' ] );
		$investigateLink = $this->getLinkRenderer()->makeKnownLink(
			SpecialPage::getTitleFor( 'Investigate' ),
			$this->msg( 'checkuser-link-investigate-label' )->text()
		);
		$out->setIndicators( [ 'investigate-link' => $icon . $investigateLink ] );
		$query = [];
		if ( $user !== '' ) {
			$query['targets'] = $user;
		}
		$links[] = Html::rawElement(
			'span',
			[],
			$this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Investigate' ),
				$this->msg( $user ? 'checkuser-investigate-this-user' : 'checkuser-show-investigate' )->text(),
				[],
				$query
			)
		);
		if ( $this->permissionManager->userHasRight( $this->getUser(), 'checkuser-log' ) ) {
			$links[] = Html::rawElement(
				'span',
				[],
				$this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUserLog' ),
					$this->msg( 'checkuser-showlog' )->text()
				)
			);
			if ( $user !== '' ) {
				$links[] = Html::rawElement(
					'span',
					[],
					$this->getLinkRenderer()->makeKnownLink(
						SpecialPage::getTitleFor( 'CheckUserLog', $user ),
						$this->msg( 'checkuser-recent-checks' )->text()
					)
				);
			}
		}

		if ( count( $links ) ) {
			$out->addSubtitle( Html::rawElement(
				'span',
				[ 'class' => 'mw-checkuser-links-no-parentheses' ],
				Html::openElement( 'span' ) .
				implode(
					Html::closeElement( 'span' ) . Html::openElement( 'span' ),
					$links
				) .
				Html::closeElement( 'span' )
			) );
		}

		$userIdentity = null;
		$isIP = false;
		$xfor = false;
		$m = [];
		if ( IPUtils::isIPAddress( $user ) ) {
			// A single IP address or an IP range
			$userIdentity = UserIdentityValue::newAnonymous( IPUtils::sanitizeIP( $user ) );
			$isIP = true;
		} elseif ( preg_match( '/^(.+)\/xff$/', $user, $m ) && IPUtils::isIPAddress( $m[1] ) ) {
			// A single IP address or range with XFF string included
			$userIdentity = UserIdentityValue::newAnonymous( IPUtils::sanitizeIP( $m[1] ) );
			$xfor = true;
			$isIP = true;
		} else {
			// A user?
			if ( $user ) {
				$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $user );
			}
		}

		$this->showIntroductoryText();
		$this->showForm( $user, $isIP );

		// Perform one of the various submit operations...
		if ( $request->wasPosted() ) {
			$checkType = $this->opts->getValue( 'checktype' );
			if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
				$out->wrapWikiMsg( '<div class="error">$1</div>', 'checkuser-token-fail' );
			} elseif ( !$this->checkReason() ) {
				$out->addWikiMsg( 'checkuser-noreason' );
			} elseif ( $checkType == self::SUBTYPE_GET_IPS ) {
				if ( $isIP || !$user ) {
					$out->addWikiMsg( 'nouserspecified' );
				} elseif ( !$userIdentity || !$userIdentity->getId() ) {
					$out->addWikiMsg( 'nosuchusershort', $user );
				} else {
					$pager = $this->getPager( self::SUBTYPE_GET_IPS, $userIdentity, 'userips' );
					$out->addHtml( $pager->getBody() );
				}
			} elseif ( $checkType == self::SUBTYPE_GET_ACTIONS ) {
				if ( $isIP && $userIdentity ) {
					// Target is a IP or range
					if ( !$this->checkUserLookupUtils->isValidIPOrRange( $userIdentity->getName() ) ) {
						$out->addWikiMsg( 'checkuser-range-outside-limit', $userIdentity->getName() );
					} else {
						$logType = $xfor ? 'ipedits-xff' : 'ipedits';

						// Ordered in descent by timestamp. Can cause large filesorts on range scans.
						$pager = $this->getPager( self::SUBTYPE_GET_ACTIONS, $userIdentity, $logType, $xfor );
						$out->addHTML( $pager->getBody() );
					}
				} else {
					// Target is a username
					if ( !$user ) {
						$out->addWikiMsg( 'nouserspecified' );
					} elseif ( !$userIdentity || !$userIdentity->getId() ) {
						$out->addHTML( $this->msg( 'nosuchusershort', $user )->parseAsBlock() );
					} else {
						// Sorting might take some time
						// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
						@set_time_limit( 60 );

						$pager = $this->getPager( self::SUBTYPE_GET_ACTIONS, $userIdentity, 'useredits' );
						$out->addHTML( $pager->getBody() );
					}
				}
			} elseif ( $checkType == self::SUBTYPE_GET_USERS ) {
				if ( !$isIP || !$userIdentity ) {
					$out->addWikiMsg( 'badipaddress' );
				} elseif ( !$this->checkUserLookupUtils->isValidIPOrRange( $userIdentity->getName() ) ) {
					$out->addWikiMsg( 'checkuser-range-outside-limit', $userIdentity->getName() );
				} else {
					$logType = $xfor ? 'ipusers-xff' : 'ipusers';

					$pager = $this->getPager( self::SUBTYPE_GET_USERS, $userIdentity, $logType, $xfor );
					$out->addHTML( $pager->getBody() );
				}
			}
		}
		// Add CIDR calculation convenience JS form
		$this->addJsCIDRForm();
		$out->addJsConfigVars(
			'wgCheckUserDisplayClientHints',
			$this->getConfig()->get( 'CheckUserDisplayClientHints' )
		);
		$out->addModules( 'ext.checkUser' );
		$out->addModuleStyles( [
			'mediawiki.interface.helpers.styles',
			'ext.checkUser.styles',
		] );
	}

	protected function showIntroductoryText() {
		$config = $this->getConfig();
		$cidrLimit = $config->get( 'CheckUserCIDRLimit' );
		$maximumRowCount = $config->get( 'CheckUserMaximumRowCount' );
		$this->getOutput()->addWikiMsg(
			'checkuser-summary',
			$cidrLimit['IPv4'],
			$cidrLimit['IPv6'],
			Message::numParam( $maximumRowCount )
		);
	}

	/**
	 * Show the CheckUser query form
	 *
	 * @param string $user
	 * @param bool $isIP
	 */
	protected function showForm( string $user, bool $isIP ) {
		// Fill in requested type if it makes sense
		$ipAllowed = true;
		$checktype = $this->opts->getValue( 'checktype' );
		if ( $checktype == self::SUBTYPE_GET_USERS && $isIP ) {
			$checkTypeValidated = $checktype;
			$ipAllowed = false;
		} elseif ( $checktype == self::SUBTYPE_GET_IPS && !$isIP ) {
			$checkTypeValidated = $checktype;
		} elseif ( $checktype == self::SUBTYPE_GET_ACTIONS ) {
			$checkTypeValidated = $checktype;
		// Defaults otherwise
		} elseif ( $isIP ) {
			$checkTypeValidated = self::SUBTYPE_GET_ACTIONS;
		} else {
			$checkTypeValidated = self::SUBTYPE_GET_IPS;
			$ipAllowed = false;
		}

		$fields = [
			'target' => [
				'type' => 'user',
				// validation in execute() currently
				'exists' => false,
				'ipallowed' => $ipAllowed,
				'iprange' => $ipAllowed,
				'name' => 'user',
				'label-message' => 'checkuser-target',
				'default' => $user,
				'id' => 'checktarget',
			],
			'radiooptions' => [
				'type' => 'radio',
				'options-messages' => [
					'checkuser-ips' => self::SUBTYPE_GET_IPS,
					'checkuser-actions' => self::SUBTYPE_GET_ACTIONS,
					'checkuser-users' => self::SUBTYPE_GET_USERS,
				],
				'id' => 'checkuserradios',
				'default' => $checkTypeValidated,
				'name' => 'checktype',
				'nodata' => 'yes',
				'flatlist' => true,
			],
			'period' => [
				'type' => 'select',
				'id' => 'period',
				'label-message' => 'checkuser-period',
				'options-messages' => [
					'checkuser-week-1' => 7,
					'checkuser-week-2' => 14,
					'checkuser-month' => 30,
					'checkuser-month-2' => 60,
					'checkuser-all' => 0,
				],
				'default' => $this->opts->getValue( 'period' ),
				'name' => 'period',
			],
			'reason' => [
				'type' => 'text',
				'default' => $this->opts->getValue( 'reason' ),
				'label-message' => 'checkuser-reason',
				'size' => 46,
				'maxlength' => 150,
				'id' => 'checkreason',
				'name' => 'reason',
			],
		];

		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() );
		$form->setMethod( 'post' )
			->setWrapperLegendMsg( 'checkuser-query' )
			->setSubmitTextMsg( 'checkuser-check' )
			->setId( 'checkuserform' )
			->setSubmitId( 'checkusersubmit' )
			->setSubmitName( 'checkusersubmit' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Make a quick JS form for admins to calculate block ranges
	 */
	protected function addJsCIDRForm() {
		$out = $this->getOutput();
		$out->addHTML( ( new CIDRCalculator( $out ) )->getHtml() );
	}

	/**
	 * @return bool
	 */
	protected function checkReason(): bool {
		return ( !$this->getConfig()->get( 'CheckUserForceSummary' ) || strlen( $this->opts->getValue( 'reason' ) ) );
	}

	/**
	 * Gets the pager for the specific check type.
	 * Returns null if the checktype is not recognised.
	 *
	 * @param string $checkType
	 * @param UserIdentity $userIdentity
	 * @param string $logType
	 * @param bool|null $xfor
	 * @return AbstractCheckUserPager|null
	 */
	public function getPager( string $checkType, UserIdentity $userIdentity, string $logType, ?bool $xfor = null ) {
		switch ( $checkType ) {
			case self::SUBTYPE_GET_IPS:
				return new CheckUserGetIPsPager(
					$this->opts,
					$userIdentity,
					$logType,
					$this->tokenQueryManager,
					$this->userGroupManager,
					$this->centralIdLookup,
					$this->dbProvider,
					$this->getSpecialPageFactory(),
					$this->userIdentityLookup,
					$this->checkUserLogService,
					$this->userFactory,
					$this->checkUserLookupUtils,
					$this->userOptionsLookup,
					$this->blockStore
				);
			case self::SUBTYPE_GET_USERS:
				return new CheckUserGetUsersPager(
					$this->opts,
					$userIdentity,
					$xfor ?? false,
					$logType,
					$this->tokenQueryManager,
					$this->permissionManager,
					$this->userGroupManager,
					$this->centralIdLookup,
					$this->dbProvider,
					$this->getSpecialPageFactory(),
					$this->userIdentityLookup,
					$this->userFactory,
					$this->checkUserLogService,
					$this->checkUserLookupUtils,
					$this->userEditTracker,
					$this->checkUserUtilityService,
					$this->clientHintsLookup,
					$this->clientHintsFormatter,
					$this->userOptionsLookup,
					$this->blockStore,
					$this->linkBatchFactory
				);
			case self::SUBTYPE_GET_ACTIONS:
				return new CheckUserGetActionsPager(
					$this->opts,
					$userIdentity,
					$xfor,
					$logType,
					$this->tokenQueryManager,
					$this->userGroupManager,
					$this->centralIdLookup,
					$this->linkBatchFactory,
					$this->dbProvider,
					$this->getSpecialPageFactory(),
					$this->userIdentityLookup,
					$this->userFactory,
					$this->checkUserLookupUtils,
					$this->checkUserLogService,
					$this->commentFormatter,
					$this->userEditTracker,
					$this->hookRunner,
					$this->checkUserUtilityService,
					$this->commentStore,
					$this->clientHintsLookup,
					$this->clientHintsFormatter,
					$this->logFormatterFactory,
					$this->userOptionsLookup,
					$this->blockStore
				);
			default:
				return null;
		}
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
		if ( !$this->userNameUtils->isValid( $search ) ) {
			// No prefix suggestion for invalid user
			return [];
		}
		// Autocomplete subpage as user list - public to allow caching
		return $this->userNamePrefixSearch->search( UserNamePrefixSearch::AUDIENCE_PUBLIC, $search, $limit, $offset );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}
}
