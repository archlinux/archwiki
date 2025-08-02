<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\Hook\CheckUserFormatRowHook;
use MediaWiki\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Linker\LinkRenderer;
use Psr\Log\LoggerInterface;

class TimelinePagerFactory implements PagerFactory {
	private LinkRenderer $linkRenderer;
	private CheckUserFormatRowHook $formatRowHookRunner;
	private TokenQueryManager $tokenQueryManager;
	private DurationManager $durationManager;
	private TimelineService $service;
	private TimelineRowFormatterFactory $rowFormatterFactory;
	private LinkBatchFactory $linkBatchFactory;
	private LoggerInterface $logger;

	public function __construct(
		LinkRenderer $linkRenderer,
		CheckUserFormatRowHook $formatRowHookRunner,
		TokenQueryManager $tokenQueryManager,
		DurationManager $durationManager,
		TimelineService $service,
		TimelineRowFormatterFactory $rowFormatterFactory,
		LinkBatchFactory $linkBatchFactory,
		LoggerInterface $logger
	) {
		$this->linkRenderer = $linkRenderer;
		$this->formatRowHookRunner = $formatRowHookRunner;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->durationManager = $durationManager;
		$this->service = $service;
		$this->rowFormatterFactory = $rowFormatterFactory;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( IContextSource $context ): TimelinePager {
		$rowFormatter = $this->rowFormatterFactory->createRowFormatter(
			$context->getUser(), $context->getLanguage()
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
