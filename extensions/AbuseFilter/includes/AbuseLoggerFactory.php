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
	public const SERVICE_NAME = ServiceNames::AbuseLoggerFactory;

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

	public function __construct(
		private readonly CentralDBManager $centralDBManager,
		private readonly FilterLookup $filterLookup,
		private readonly VariablesBlobStore $varBlobStore,
		private readonly VariablesManager $varManager,
		private readonly EditRevUpdater $editRevUpdater,
		private readonly AbuseFilterPermissionManager $afPermissionManager,
		private readonly RuleCheckerFactory $ruleCheckerFactory,
		private readonly LBFactory $lbFactory,
		private readonly ActorStore $actorStore,
		private readonly TitleFactory $titleFactory,
		private readonly ServiceOptions $options,
		private readonly string $wikiID,
		private readonly string $requestIP,
		private readonly LoggerInterface $logger,
		private readonly AbuseFilterHookRunner $hookRunner
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
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
