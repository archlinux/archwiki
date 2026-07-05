<?php

namespace MediaWiki\Extension\CheckUser\Investigate\Pagers;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Hook\CheckUserFormatRowHook;
use MediaWiki\Extension\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\Extension\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\Extension\CheckUser\Services\TokenQueryManager;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\LinkBatchFactory;
use Psr\Log\LoggerInterface;

class TimelinePagerFactory implements PagerFactory {
	public function __construct(
		private readonly LinkRenderer $linkRenderer,
		private readonly CheckUserFormatRowHook $formatRowHookRunner,
		private readonly TokenQueryManager $tokenQueryManager,
		private readonly DurationManager $durationManager,
		private readonly TimelineService $service,
		private readonly TimelineRowFormatterFactory $rowFormatterFactory,
		private readonly LinkBatchFactory $linkBatchFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( IContextSource $context ): TimelinePager {
		$rowFormatter = $this->rowFormatterFactory->createRowFormatter(
			$context->getUser(),
			$context->getLanguage()
		);

		return new TimelinePager(
			$context,
			$this->linkRenderer,
			$this->formatRowHookRunner,
			$this->tokenQueryManager,
			$this->durationManager,
			$this->service,
			$rowFormatter,
			$this->linkBatchFactory,
			$this->logger
		);
	}
}
