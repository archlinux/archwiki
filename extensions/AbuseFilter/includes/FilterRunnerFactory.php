<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesExecutorFactory;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Extension\AbuseFilter\Watcher\EmergencyWatcher;
use MediaWiki\Extension\AbuseFilter\Watcher\UpdateHitCountWatcher;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Stats\IBufferingStatsdDataFactory;
use Wikimedia\Stats\NullStatsdDataFactory;

class FilterRunnerFactory {
	public const SERVICE_NAME = ServiceNames::FilterRunnerFactory;

	public function __construct(
		private readonly AbuseFilterHookRunner $hookRunner,
		private readonly FilterProfiler $filterProfiler,
		private readonly ChangeTagger $changeTagger,
		private readonly FilterLookup $filterLookup,
		private readonly RuleCheckerFactory $ruleCheckerFactory,
		private readonly ConsequencesExecutorFactory $consExecutorFactory,
		private readonly AbuseLoggerFactory $abuseLoggerFactory,
		private readonly VariablesManager $varManager,
		private readonly EmergencyCache $emergencyCache,
		private readonly UpdateHitCountWatcher $updateHitCountWatcher,
		private readonly EmergencyWatcher $emergencyWatcher,
		private readonly BagOStuff $localCache,
		private readonly LoggerInterface $logger,
		private readonly LoggerInterface $editStashLogger,
		private readonly IBufferingStatsdDataFactory $statsdDataFactory,
		private readonly ServiceOptions $options
	) {
	}

	/**
	 * @param User $user
	 * @param Title $title
	 * @param VariableHolder $vars
	 * @param string $group
	 * @return FilterRunner
	 */
	public function newRunner(
		User $user,
		Title $title,
		VariableHolder $vars,
		string $group
	): FilterRunner {
		// TODO Add a method to this class taking these as params? Add a hook for custom watchers
		$watchers = [ $this->updateHitCountWatcher, $this->emergencyWatcher ];
		return new FilterRunner(
			$this->hookRunner,
			$this->filterProfiler,
			$this->changeTagger,
			$this->filterLookup,
			$this->ruleCheckerFactory,
			$this->consExecutorFactory,
			$this->abuseLoggerFactory,
			$this->varManager,
			$this->emergencyCache,
			$watchers,
			new EditStashCache(
				$this->localCache,
				// Bots do not use edit stashing, so avoid distorting the stats
				$user->isBot() ? new NullStatsdDataFactory() : $this->statsdDataFactory,
				$this->varManager,
				$this->editStashLogger,
				$title,
				$group
			),
			$this->logger,
			$this->options,
			$user,
			$title,
			$vars,
			$group
		);
	}
}
