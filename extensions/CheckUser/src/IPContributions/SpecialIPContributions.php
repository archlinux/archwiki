<?php

namespace MediaWiki\CheckUser\IPContributions;

use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @ingroup SpecialPage
 */
class SpecialIPContributions extends ContributionsSpecialPage {
	private const BASE_HELP_URL = 'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:';

	private IPContributionsPagerFactory $pagerFactory;
	private ?IPContributionsPager $pager = null;
	private CheckUserPermissionManager $checkUserPermissionManager;

	public function __construct(
		PermissionManager $permissionManager,
		IConnectionProvider $dbProvider,
		NamespaceInfo $namespaceInfo,
		UserNameUtils $userNameUtils,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		UserIdentityLookup $userIdentityLookup,
		DatabaseBlockStore $blockStore,
		IPContributionsPagerFactory $pagerFactory,
		CheckUserPermissionManager $checkUserPermissionManager
	) {
		parent::__construct(
			$permissionManager,
			$dbProvider,
			$namespaceInfo,
			$userNameUtils,
			$userNamePrefixSearch,
			$userOptionsLookup,
			$userFactory,
			$userIdentityLookup,
			$blockStore,
			'IPContributions'
		);
		$this->pagerFactory = $pagerFactory;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Merely declarative
	 */
	public function isIncludable() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getTargetField( $target ) {
		return [
			'type' => 'user',
			'default' => str_replace( '_', ' ', $target ),
			'label' => $this->msg( 'checkuser-ip-contributions-target-label' )->text(),
			'name' => 'target',
			'id' => 'mw-target-user-or-ip',
			'size' => 40,
			'autofocus' => $target === '',
			'section' => 'contribs-top',
			'validation-callback' => function ( $target ) {
				if ( !$this->isValidIPOrQueryableRange( $target, $this->getConfig() ) ) {
					return $this->msg( 'checkuser-ip-contributions-target-error-no-ip' );
				}
				return true;
			},
			'excludenamed' => true,
			'excludetemp' => true,
			'ipallowed' => true,
			'iprange' => true,
			'iprangelimits' => $this->getQueryableRangeLimit( $this->getConfig() ),
			'required' => true,
		];
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Merely declarative
	 */
	public function isRestricted() {
		return true;
	}

	/** @inheritDoc */
	public function userCanExecute( User $user ) {
		// Implemented so that Special:SpecialPages can hide Special:IPContributions if the user does not have the
		// necessary rights, but still show it if the user just hasn't checked the preference or is blocked.
		// The user is denied access for reasons other than rights in ::execute.
		$permissionCheck = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$user
		);
		return $permissionCheck->getPermission() === null;
	}

	/**
	 * @inheritDoc
	 * @throws ErrorPageError
	 * @throws UserBlockedError
	 */
	public function checkPermissions(): void {
		// These checks are the same as in AbstractTemporaryAccountHandler.
		//
		// Note we don't call parent::checkPermissions() here: The parent method
		// would call userCanExecute(), which essentially does the same we do
		// here but throwing a generic PermissionsError exception, while here we
		// throw different exceptions depending on the cause of the error.
		$permStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$this->getAuthority()
		);

		if ( !$permStatus->isGood() ) {
			if ( $permStatus->hasMessage( 'checkuser-tempaccount-reveal-ip-permission-error-description' ) ) {
				throw new ErrorPageError(
					$this->msg( 'checkuser-ip-contributions-permission-error-title' ),
					$this->msg( 'checkuser-ip-contributions-permission-error-description' )
				);
			}

			$block = $permStatus->getBlock();
			if ( $block ) {
				throw new UserBlockedError(
					$block,
					$this->getAuthority()->getUser(),
					$this->getLanguage(),
					$this->getRequest()->getIP()
				);
			}

			throw new PermissionsError( $permStatus->getPermission() );
		}

		$canSeeDeletedHistory = $this->permissionManager->userHasRight(
			$this->getAuthority()->getUser(),
			'deletedhistory'
		);

		if ( $this->isArchive() && !$canSeeDeletedHistory ) {
			throw new PermissionsError( 'deletedhistory' );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// Add to the $opts array now, so that parent::getForm() can add this as a
		// hidden field. This ensures the search form displayed on each tab submits
		// in the correct mode.
		$this->opts['isArchive'] = $this->isArchive();

		parent::execute( $par );

		// Setting $overrideBaseUrl=true is needed to prevent addHelpLink()
		// from trying to encode the anchor character (#)
		$this->addHelpLink(
			self::BASE_HELP_URL . 'Extension:CheckUser#Special:IPContributions_usage',
			true
		);

		$target = $this->opts['target'] ?? null;
		if ( $target && !IPUtils::isIPAddress( $target ) ) {
			$this->getOutput()->setSubtitle(
				new MessageWidget( [
					'type' => 'error',
					'label' => new HtmlSnippet(
						$this->msg( 'checkuser-ip-contributions-target-error-no-ip-banner', $target )->parse()
					)
				] )
			);
		} elseif ( $target && !$this->isValidIPOrQueryableRange( $target, $this->getConfig() ) ) {
			// Valid range, but outside CIDR limit.
			$limits = $this->getQueryableRangeLimit( $this->getConfig() );
			$limit = $limits[ IPUtils::isIPv4( $target ) ? 'IPv4' : 'IPv6' ];
			$this->getOutput()->addWikiMsg( 'sp-contributions-outofrange', $limit );
		} else {
			$this->getOutput()->addJsConfigVars( 'wgIPRangeTarget', $target );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function isArchive() {
		return $this->getRequest()->getBool( 'isArchive' );
	}

	/**
	 * @inheritDoc
	 */
	public function getPager( $target ) {
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
				'tagInvert' => $this->opts['tagInvert'],
				'isArchive' => $this->opts['isArchive'],
			];

			$this->pager = $this->pagerFactory->createPager(
				$this->getContext(),
				$options,
				$target
			);
		}

		return $this->pager;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'checkuser-ip-contributions' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormWrapperLegendMessageKey() {
		return 'checkuser-ip-contributions-search-form-wrapper';
	}

	/**
	 * @inheritDoc
	 */
	protected function getResultsPageTitleMessageKey( UserIdentity $target ) {
		return $this->opts['isArchive'] ?
			'checkuser-ip-contributions-archive-results-title' :
			'checkuser-ip-contributions-results-title';
	}

	/** @inheritDoc */
	protected function contributionsSub( $userObj, $targetName ) {
		$contributionsSub = parent::contributionsSub( $userObj, $targetName );

		// Add subtitle text describing that the data shown is limited to wgCUDMaxAge seconds ago. The count should
		// be in days, as this makes it easier to translate the message.
		$contributionsSub .= $this->msg( 'checkuser-ip-contributions-subtitle' )
			->numParams( round( $this->getConfig()->get( 'CUDMaxAge' ) / 86400 ) )
			->parse();

		return $contributionsSub;
	}

	/** @inheritDoc */
	public function shouldShowBlockLogExtract( UserIdentity $target ): bool {
		return parent::shouldShowBlockLogExtract( $target ) &&
			$this->isValidIPOrQueryableRange( $target->getName(), $this->getConfig() );
	}
}
