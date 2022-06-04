<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use Psr\Log\LoggerInterface;
use Title;
use User;

class ConsequencesExecutorFactory {
	public const SERVICE_NAME = 'AbuseFilterConsequencesExecutorFactory';

	/** @var ConsequencesLookup */
	private $consLookup;
	/** @var ConsequencesFactory */
	private $consFactory;
	/** @var ConsequencesRegistry */
	private $consRegistry;
	/** @var FilterLookup */
	private $filterLookup;
	/** @var LoggerInterface */
	private $logger;
	/** @var ServiceOptions */
	private $options;

	/**
	 * @param ConsequencesLookup $consLookup
	 * @param ConsequencesFactory $consFactory
	 * @param ConsequencesRegistry $consRegistry
	 * @param FilterLookup $filterLookup
	 * @param LoggerInterface $logger
	 * @param ServiceOptions $options
	 */
	public function __construct(
		ConsequencesLookup $consLookup,
		ConsequencesFactory $consFactory,
		ConsequencesRegistry $consRegistry,
		FilterLookup $filterLookup,
		LoggerInterface $logger,
		ServiceOptions $options
	) {
		$this->consLookup = $consLookup;
		$this->consFactory = $consFactory;
		$this->consRegistry = $consRegistry;
		$this->filterLookup = $filterLookup;
		$this->logger = $logger;
		$options->assertRequiredOptions( ConsequencesExecutor::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
	}

	/**
	 * @param User $user
	 * @param Title $title
	 * @param VariableHolder $vars
	 * @return ConsequencesExecutor
	 */
	public function newExecutor( User $user, Title $title, VariableHolder $vars ): ConsequencesExecutor {
		return new ConsequencesExecutor(
			$this->consLookup,
			$this->consFactory,
			$this->consRegistry,
			$this->filterLookup,
			$this->logger,
			$this->options,
			$user,
			$title,
			$vars
		);
	}
}
