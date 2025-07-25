<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Stats\StatsFactory;

class GlobalContributionsPagerFactory {

	use ContributionsRangeTrait;

	private LinkRenderer $linkRenderer;
	private LinkBatchFactory $linkBatchFactory;
	private HookContainer $hookContainer;
	private RevisionStore $revisionStore;
	private NamespaceInfo $namespaceInfo;
	private CommentFormatter $commentFormatter;
	private UserFactory $userFactory;
	private TempUserConfig $tempUserConfig;
	private Config $config;
	private CheckUserLookupUtils $lookupUtils;
	private CentralIdLookup $centralIdLookup;
	private CheckUserApiRequestAggregator $apiRequestAggregator;
	private CheckUserGlobalContributionsLookup $globalContributionsLookup;
	private PermissionManager $permissionManager;
	private GlobalPreferencesFactory $globalPreferencesFactory;
	private IConnectionProvider $dbProvider;
	private JobQueueGroup $jobQueueGroup;
	private StatsFactory $statsFactory;

	public function __construct(
		LinkRenderer $linkRenderer,
		LinkBatchFactory $linkBatchFactory,
		HookContainer $hookContainer,
		RevisionStore $revisionStore,
		NamespaceInfo $namespaceInfo,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory,
		TempUserConfig $tempUserConfig,
		Config $config,
		CheckUserLookupUtils $lookupUtils,
		CentralIdLookup $centralIdLookup,
		CheckUserApiRequestAggregator $apiRequestAggregator,
		CheckUserGlobalContributionsLookup $globalContributionsLookup,
		PermissionManager $permissionManager,
		GlobalPreferencesFactory $globalPreferencesFactory,
		IConnectionProvider $dbProvider,
		JobQueueGroup $jobQueueGroup,
		StatsFactory $statsFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->hookContainer = $hookContainer;
		$this->revisionStore = $revisionStore;
		$this->namespaceInfo = $namespaceInfo;
		$this->commentFormatter = $commentFormatter;
		$this->userFactory = $userFactory;
		$this->tempUserConfig = $tempUserConfig;
		$this->config = $config;
		$this->lookupUtils = $lookupUtils;
		$this->centralIdLookup = $centralIdLookup;
		$this->apiRequestAggregator = $apiRequestAggregator;
		$this->globalContributionsLookup = $globalContributionsLookup;
		$this->permissionManager = $permissionManager;
		$this->globalPreferencesFactory = $globalPreferencesFactory;
		$this->dbProvider = $dbProvider;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->statsFactory = $statsFactory;
	}

	/**
	 * @param IContextSource $context
	 * @param array $options
	 * @param UserIdentity $target IP address or username for user contributions lookup
	 * @return GlobalContributionsPager
	 */
	public function createPager(
		IContextSource $context,
		array $options,
		UserIdentity $target
	): GlobalContributionsPager {
		return new GlobalContributionsPager(
			$this->linkRenderer,
			$this->linkBatchFactory,
			$this->hookContainer,
			$this->revisionStore,
			$this->namespaceInfo,
			$this->commentFormatter,
			$this->userFactory,
			$this->tempUserConfig,
			$this->lookupUtils,
			$this->centralIdLookup,
			$this->apiRequestAggregator,
			$this->globalContributionsLookup,
			$this->permissionManager,
			$this->globalPreferencesFactory,
			$this->dbProvider,
			$this->jobQueueGroup,
			$this->statsFactory,
			$context,
			$options,
			$target
		);
	}
}
