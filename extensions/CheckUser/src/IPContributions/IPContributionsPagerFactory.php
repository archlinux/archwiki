<?php

namespace MediaWiki\Extension\CheckUser\IPContributions;

use InvalidArgumentException;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;

class IPContributionsPagerFactory {

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
		private readonly JobQueueGroup $jobQueueGroup,
	) {
	}

	/**
	 * @param IContextSource $context
	 * @param array $options
	 * @param UserIdentity $target IP address for temporary user contributions lookup
	 * @return IPContributionsPager
	 */
	public function createPager(
		IContextSource $context,
		array $options,
		UserIdentity $target
	): IPContributionsPager {
		$username = $target->getName();
		if ( !$this->isValidIPOrQueryableRange( $username, $this->config ) ) {
			throw new InvalidArgumentException( "Invalid target: $username" );
		}
		return new IPContributionsPager(
			$this->linkRenderer,
			$this->linkBatchFactory,
			$this->hookContainer,
			$this->revisionStore,
			$this->namespaceInfo,
			$this->commentFormatter,
			$this->userFactory,
			$this->tempUserConfig,
			$this->lookupUtils,
			$this->jobQueueGroup,
			$context,
			$options,
			$target
		);
	}
}
