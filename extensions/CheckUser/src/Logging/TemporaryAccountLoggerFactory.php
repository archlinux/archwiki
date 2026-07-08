<?php

namespace MediaWiki\Extension\CheckUser\Logging;

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

	public function __construct(
		private readonly ActorStore $actorStore,
		private readonly LoggerInterface $logger,
		private readonly IConnectionProvider $dbProvider,
		private readonly TitleFactory $titleFactory,
	) {
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

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias( TemporaryAccountLoggerFactory::class, 'MediaWiki\\CheckUser\\Logging\\TemporaryAccountLoggerFactory' );
// @codeCoverageIgnoreEnd
