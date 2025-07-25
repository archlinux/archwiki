<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Stats\StatsFactory;

/**
 * @ingroup SpecialPage
 */
class SpecialGlobalContributions extends ContributionsSpecialPage {

	use ContributionsRangeTrait;

	/**
	 * Prometheus timing metric name tracking the execution time of Special:GlobalContributions.
	 */
	public const GLOBAL_CONTRIBUTIONS_EXECUTE_DURATION_METRIC_NAME =
		'checkuser_globalcontributions_execute_duration_seconds';

	private const BASE_HELP_URL = 'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:';

	private CentralIdLookup $centralIdLookup;
	private GlobalContributionsPagerFactory $pagerFactory;
	private StatsFactory $statsFactory;

	private ?GlobalContributionsPager $pager = null;

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
		CentralIdLookup $centralIdLookup,
		GlobalContributionsPagerFactory $pagerFactory,
		StatsFactory $statsFactory
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
			'GlobalContributions'
		);
		$this->centralIdLookup = $centralIdLookup;
		$this->pagerFactory = $pagerFactory;
		$this->statsFactory = $statsFactory;
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
			'label' => $this->msg( 'checkuser-global-contributions-target-label' )->text(),
			'placeholder' => $this->msg( 'checkuser-global-contributions-target-placeholder' )->text(),
			'name' => 'target',
			'id' => 'mw-target-user-or-ip',
			'size' => 40,
			'autofocus' => $target === '',
			'section' => 'contribs-top',
			'ipallowed' => true,
			'iprange' => true,
			'iprangelimits' => $this->getQueryableRangeLimit( $this->getConfig() ),
			'required' => true,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireLogin();

		$start = hrtime( true );

		parent::execute( $par );

		// Setting $overrideBaseUrl=true is needed to prevent addHelpLink()
		// from trying to encode the anchor character (#)
		$this->addHelpLink(
			self::BASE_HELP_URL . 'Extension:CheckUser#Special:GlobalContributions_usage',
			true
		);

		$target = $this->opts['target'] ?? null;

		if ( $target === null ) {
			$message = $this->msg( 'checkuser-global-contributions-summary' )
				->numParams( $this->getMaxAgeForMessage() )
				->parse();
			$this->getOutput()->prependHTML( "<div class='mw-specialpage-summary'>\n$message\n</div>" );
		} elseif (
			IPUtils::isValidRange( $target ) &&
			!$this->isQueryableRange( $target, $this->getConfig() )
		) {
			// Valid range, but outside CIDR limit.
			$limits = $this->getQueryableRangeLimit( $this->getConfig() );
			$limit = $limits[ IPUtils::isIPv4( $target ) ? 'IPv4' : 'IPv6' ];
			$this->getOutput()->addWikiMsg( 'sp-contributions-outofrange', $limit );
		} elseif ( $this->isQueryableRange( $target, $this->getConfig() ) ) {
			$this->getOutput()->addJsConfigVars( 'wgIPRangeTarget', $target );
		}

		$time = hrtime( true ) - $start;

		$this->statsFactory->getTiming( self::GLOBAL_CONTRIBUTIONS_EXECUTE_DURATION_METRIC_NAME )
			->observeNanoseconds( $time );
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyFields( &$fields ) {
		$fields['namespace']['include'] = $this->namespaceInfo->getCommonNamespaces();

		// Some of these tags may be disabled on external wikis via `$wgSoftwareTags`
		// but any tag returned here is guaranteed to be consistent on any wiki it
		// is enabled on
		$fields['tagfilter']['useAllTags'] = false;
		$fields['tagfilter']['activeOnly'] = false;
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
				'revisionsOnly' => true,
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
		return $this->msg( 'checkuser-global-contributions' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormWrapperLegendMessageKey() {
		return 'checkuser-global-contributions-search-form-wrapper';
	}

	/**
	 * @inheritDoc
	 */
	protected function getResultsPageTitleMessageKey( UserIdentity $target ) {
		return 'checkuser-global-contributions-results-title';
	}

	/**
	 * @return float The max age of contributions in days rounded to the nearest whole number
	 */
	private function getMaxAgeForMessage() {
		return round( $this->getConfig()->get( 'CUDMaxAge' ) / 86400 );
	}

	/** @inheritDoc */
	protected function contributionsSub( $userObj, $targetName ) {
		$contributionsSub = parent::contributionsSub( $userObj, $targetName );

		// Add subtitle text describing that the data shown is limited to wgCUDMaxAge seconds ago. The count should
		// be in days, as this makes it easier to translate the message.
		$contributionsSub .= $this->msg( 'checkuser-global-contributions-subtitle' )
			->numParams(
				$this->getMaxAgeForMessage(),
				GlobalContributionsPager::REVISION_COUNT_LIMIT
			)->parse();

		// Allow linking to relevant tools to surface more global contributions (T380562).
		if ( IPUtils::isValidRange( $targetName ) ) {
			$toolsMsg = $this->msg( 'checkuser-global-contributions-ip-range-tools' );
		} elseif ( IPUtils::isIPAddress( $userObj->getName() ) ) {
			$toolsMsg = $this->msg( 'checkuser-global-contributions-anon-tools' );
		} else {
			$toolsMsg = $this->msg( 'checkuser-global-contributions-registered-user-tools' );
		}

		if ( $toolsMsg && !$toolsMsg->isDisabled() ) {
			$contributionsSub .= $toolsMsg
				->params( $userObj->getName() )
				->numParams( $this->getMaxAgeForMessage() )
				->parseAsBlock();
		}

		return $contributionsSub;
	}

	/**
	 * Prevent the base ContributionsSpecialPage class from generating the unregistered user
	 * message, as this is referring to the local user. Instead, GlobalContributionsPager will
	 * check against the central (global) account and manage its own message visibility.
	 *
	 * @inheritDoc
	 */
	protected function addContributionsSubWarning( $userObj ) {
	}

	/**
	 * Don't render the action links, as they refer to the local account instead of the global one
	 *
	 * @inheritDoc
	 */
	protected function shouldDisplayActionLinks( User $userObj ): bool {
		return false;
	}

	/**
	 * Don't show the account information, as it refers to the local account instead of the global one
	 *
	 * @inheritDoc
	 */
	protected function shouldDisplayAccountInformation( User $userObj ): bool {
		return false;
	}

	/**
	 * Replace the local user link with a link to the global user account
	 *
	 * @inheritDoc
	 */
	protected function getUserLink( User $userObj ): string {
		$target = $userObj->getName();
		if ( $this->centralIdLookup->centralIdFromName( $target, $this->getAuthority() ) ) {
			return $this->getLinkRenderer()->makeLink(
				SpecialPage::getTitleFor( 'CentralAuth', $target ),
				$target
			);
		} else {
			return $target;
		}
	}
}
