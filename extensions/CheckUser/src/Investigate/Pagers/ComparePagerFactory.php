<?php

namespace MediaWiki\Extension\CheckUser\Investigate\Pagers;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Investigate\Services\CompareService;
use MediaWiki\Extension\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\Extension\CheckUser\Services\TokenQueryManager;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\User\UserFactory;

class ComparePagerFactory implements PagerFactory {
	public function __construct(
		private readonly LinkRenderer $linkRenderer,
		private readonly TokenQueryManager $tokenQueryManager,
		private readonly DurationManager $durationManager,
		private readonly CompareService $compare,
		private readonly UserFactory $userFactory,
		private readonly LinkBatchFactory $linkBatchFactory,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( IContextSource $context ): ComparePager {
		return new ComparePager(
			$context,
			$this->linkRenderer,
			$this->tokenQueryManager,
			$this->durationManager,
			$this->compare,
			$this->userFactory,
			$this->linkBatchFactory
		);
	}
}
