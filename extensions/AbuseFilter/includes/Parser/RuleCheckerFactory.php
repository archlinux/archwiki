<?php

namespace MediaWiki\Extension\AbuseFilter\Parser;

use MediaWiki\Extension\AbuseFilter\KeywordsManager;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Language\Language;
use Psr\Log\LoggerInterface;
use Wikimedia\Equivset\Equivset;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Stats\IBufferingStatsdDataFactory;

class RuleCheckerFactory {
	public const SERVICE_NAME = ServiceNames::RuleCheckerFactory;

	public function __construct(
		private readonly Language $contLang,
		private readonly BagOStuff $cache,
		private readonly LoggerInterface $logger,
		private readonly KeywordsManager $keywordsManager,
		private readonly VariablesManager $varManager,
		private readonly IBufferingStatsdDataFactory $statsdDataFactory,
		private readonly Equivset $equivset,
		private readonly int $conditionsLimit
	) {
	}

	/**
	 * @param VariableHolder|null $vars
	 * @return FilterEvaluator
	 */
	public function newRuleChecker( ?VariableHolder $vars = null ): FilterEvaluator {
		return new FilterEvaluator(
			$this->contLang,
			$this->cache,
			$this->logger,
			$this->keywordsManager,
			$this->varManager,
			$this->statsdDataFactory,
			$this->equivset,
			$this->conditionsLimit,
			$vars
		);
	}
}
