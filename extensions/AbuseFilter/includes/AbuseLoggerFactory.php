<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Title\Title;
use MediaWiki\User\ActorStore;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\LBFactory;

class AbuseLoggerFactory {
	public const SERVICE_NAME = 'AbuseFilterAbuseLoggerFactory';

	/**
	 * The default amount of time after which a duplicate log entry can be inserted. 24 hours (in
	 * seconds).
	 *
	 * @var int
	 */
	private const DEFAULT_DEBOUNCE_DELAY = 24 * 60 * 60;

	/** @var CentralDBManager */
	private $centralDBManager;
	/** @var FilterLookup */
	private $filterLookup;
	/** @var VariablesBlobStore */
	private $varBlobStore;
	/** @var VariablesManager */
	private $varManager;
	/** @var EditRevUpdater */
	private $editRevUpdater;
	/** @var LBFactory */
	private $lbFactory;
	/** @var ActorStore */
	private $actorStore;
	/** @var ServiceOptions */
	private $options;
	/** @var string */
	private $wikiID;
	/** @var string */
	private $requestIP;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param CentralDBManager $centralDBManager
	 * @param FilterLookup $filterLookup
	 * @param VariablesBlobStore $varBlobStore
	 * @param VariablesManager $varManager
	 * @param EditRevUpdater $editRevUpdater
	 * @param LBFactory $lbFactory
	 * @param ActorStore $actorStore
	 * @param ServiceOptions $options
	 * @param string $wikiID
	 * @param string $requestIP
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		CentralDBManager $centralDBManager,
		FilterLookup $filterLookup,
		VariablesBlobStore $varBlobStore,
		VariablesManager $varManager,
		EditRevUpdater $editRevUpdater,
		LBFactory $lbFactory,
		ActorStore $actorStore,
		ServiceOptions $options,
		string $wikiID,
		string $requestIP,
		LoggerInterface $logger
	) {
		$this->centralDBManager = $centralDBManager;
		$this->filterLookup = $filterLookup;
		$this->varBlobStore = $varBlobStore;
		$this->varManager = $varManager;
		$this->editRevUpdater = $editRevUpdater;
		$this->lbFactory = $lbFactory;
		$this->actorStore = $actorStore;
		$this->options = $options;
		$this->wikiID = $wikiID;
		$this->requestIP = $requestIP;
		$this->logger = $logger;
	}

	/**
	 * @param int $delay
	 * @return ProtectedVarsAccessLogger
	 */
	public function getProtectedVarsAccessLogger(
		int $delay = self::DEFAULT_DEBOUNCE_DELAY
	) {
		return new ProtectedVarsAccessLogger(
			$this->logger,
			$this->lbFactory,
			$this->actorStore,
			$delay
		);
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param VariableHolder $vars
	 * @return AbuseLogger
	 */
	public function newLogger(
		Title $title,
		User $user,
		VariableHolder $vars
	): AbuseLogger {
		return new AbuseLogger(
			$this->centralDBManager,
			$this->filterLookup,
			$this->varBlobStore,
			$this->varManager,
			$this->editRevUpdater,
			$this->lbFactory,
			$this->options,
			$this->wikiID,
			$this->requestIP,
			$title,
			$user,
			$vars
		);
	}
}
