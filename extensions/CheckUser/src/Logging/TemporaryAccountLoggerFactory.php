<?php

namespace MediaWiki\CheckUser\Logging;

use MediaWiki\Title\TitleFactory;
use MediaWiki\User\ActorStore;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IConnectionProvider;

class TemporaryAccountLoggerFactory {

	/**
	 * The default amount of time after which a duplicate log entry can be inserted.
	 *
	 * @var int
	 */
	private const DEFAULT_DEBOUNCE_DELAY = 24 * 60 * 60;

	private ActorStore $actorStore;
	private LoggerInterface $logger;
	private IConnectionProvider $dbProvider;
	private TitleFactory $titleFactory;

	public function __construct(
		ActorStore $actorStore,
		LoggerInterface $logger,
		IConnectionProvider $dbProvider,
		TitleFactory $titleFactory
	) {
		$this->actorStore = $actorStore;
		$this->logger = $logger;
		$this->dbProvider = $dbProvider;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @param int $delay
	 * @return TemporaryAccountLogger
	 */
	public function getLogger(
		int $delay = self::DEFAULT_DEBOUNCE_DELAY
	) {
		return new TemporaryAccountLogger(
			$this->actorStore,
			$this->logger,
			$this->dbProvider,
			$this->titleFactory,
			$delay
		);
	}
}
