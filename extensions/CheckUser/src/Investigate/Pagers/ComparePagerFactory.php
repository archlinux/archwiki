<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\Investigate\Services\CompareService;
use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\User\UserFactory;

class ComparePagerFactory implements PagerFactory {
	private LinkRenderer $linkRenderer;
	private TokenQueryManager $tokenQueryManager;
	private DurationManager $durationManager;
	private CompareService $compare;
	private UserFactory $userFactory;
	private LinkBatchFactory $linkBatchFactory;

	public function __construct(
		LinkRenderer $linkRenderer,
		TokenQueryManager $tokenQueryManager,
		DurationManager $durationManager,
		CompareService $compare,
		UserFactory $userFactory,
		LinkBatchFactory $linkBatchFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->durationManager = $durationManager;
		$this->compare = $compare;
		$this->userFactory = $userFactory;
		$this->linkBatchFactory = $linkBatchFactory;
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
