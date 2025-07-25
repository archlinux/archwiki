<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\ActorStore;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\LBFactory;

class AbuseLoggerFactory {
	public const SERVICE_NAME = 'AbuseFilterAbuseLoggerFactory';

	public const CONSTRUCTOR_OPTIONS = [
		'AbuseFilterLogIP',
		'AbuseFilterNotifications',
		'AbuseFilterNotificationsPrivate',
	];

	/**
	 * The default amount of time after which a duplicate log entry can be inserted. 24 hours (in
	 * seconds).
	 *
	 * @var int
	 */
	private const DEFAULT_DEBOUNCE_DELAY = 24 * 60 * 60;

	private CentralDBManager $centralDBManager;
	private FilterLookup $filterLookup;
	private VariablesBlobStore $varBlobStore;
	private VariablesManager $varManager;
	private EditRevUpdater $editRevUpdater;
	private AbuseFilterPermissionManager $afPermissionManager;
	private RuleCheckerFactory $ruleCheckerFactory;
	private LBFactory $lbFactory;
	private ActorStore $actorStore;
	private TitleFactory $titleFactory;
	private ServiceOptions $options;
	private string $wikiID;
	private string $requestIP;
	private LoggerInterface $logger;
	private AbuseFilterHookRunner $hookRunner;

	public function __construct(
		CentralDBManager $centralDBManager,
		FilterLookup $filterLookup,
		VariablesBlobStore $varBlobStore,
		VariablesManager $varManager,
		EditRevUpdater $editRevUpdater,
		AbuseFilterPermissionManager $afPermissionManager,
		RuleCheckerFactory $ruleCheckerFactory,
		LBFactory $lbFactory,
		ActorStore $actorStore,
		TitleFactory $titleFactory,
		ServiceOptions $options,
		string $wikiID,
		string $requestIP,
		LoggerInterface $logger,
		AbuseFilterHookRunner $hookRunner
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->centralDBManager = $centralDBManager;
		$this->filterLookup = $filterLookup;
		$this->varBlobStore = $varBlobStore;
		$this->varManager = $varManager;
		$this->editRevUpdater = $editRevUpdater;
		$this->afPermissionManager = $afPermissionManager;
		$this->ruleCheckerFactory = $ruleCheckerFactory;
		$this->lbFactory = $lbFactory;
		$this->actorStore = $actorStore;
		$this->titleFactory = $titleFactory;
		$this->options = $options;
		$this->wikiID = $wikiID;
		$this->requestIP = $requestIP;
		$this->logger = $logger;
		$this->hookRunner = $hookRunner;
	}

	/**
	 * @param int $delay
	 * @return ProtectedVarsAccessLogger
	 */
	public function getProtectedVarsAccessLogger(
		int $delay = self::DEFAULT_DEBOUNCE_DELAY
	): ProtectedVarsAccessLogger {
		return new ProtectedVarsAccessLogger(
			$this->logger,
			$this->lbFactory,
			$this->actorStore,
			$this->hookRunner,
			$this->titleFactory,
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
			$this->ruleCheckerFactory,
			$this->afPermissionManager,
			$this->options,
			$this->wikiID,
			$this->requestIP,
			$title,
			$user,
			$vars
		);
	}
}
