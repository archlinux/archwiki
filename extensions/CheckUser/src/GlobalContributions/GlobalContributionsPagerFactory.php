<?php

namespace MediaWiki\Extension\CheckUser\GlobalContributions;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\ChangeTags\ChangeTagsStoreFactory;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\UserLinkRenderer;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\RevisionStoreFactory;
use MediaWiki\Site\SiteLookup;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\ReadOnlyMode;

class GlobalContributionsPagerFactory {

	use ContributionsRangeTrait;

	public function __construct(
		private readonly LinkRenderer $linkRenderer,
		private readonly LinkBatchFactory $linkBatchFactory,
		private readonly HookContainer $hookContainer,
		private readonly RevisionStore $revisionStore,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly CommentFormatter $commentFormatter,
		private readonly UserFactory $userFactory,
		private readonly TempUserConfig $tempUserConfig,
		private readonly Config $config,
		private readonly CheckUserLookupUtils $lookupUtils,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly CheckUserGlobalContributionsLookup $globalContributionsLookup,
		private readonly PermissionManager $permissionManager,
		private readonly GlobalPreferencesFactory $globalPreferencesFactory,
		private readonly IConnectionProvider $dbProvider,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly UserLinkRenderer $userLinkRenderer,
		private readonly RevisionStoreFactory $revisionStoreFactory,
		private readonly ChangeTagsStoreFactory $changeTagsStoreFactory,
		private readonly SiteLookup $siteLookup,
		private readonly ReadOnlyMode $readOnlyMode,
	) {
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
			$this->globalContributionsLookup,
			$this->permissionManager,
			$this->globalPreferencesFactory,
			$this->dbProvider,
			$this->jobQueueGroup,
			$this->userLinkRenderer,
			$this->revisionStoreFactory,
			$this->changeTagsStoreFactory,
			$this->siteLookup,
			$this->readOnlyMode,
			$context,
			$options,
			$target
		);
	}
}
