<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\ActionSpecifier;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\User\UserIdentityUtils;
use Psr\Log\LoggerInterface;

class ConsequencesExecutorFactory {
	public const SERVICE_NAME = ServiceNames::ConsequencesExecutorFactory;

	public function __construct(
		private readonly ConsequencesLookup $consLookup,
		private readonly ConsequencesFactory $consFactory,
		private readonly ConsequencesRegistry $consRegistry,
		private readonly FilterLookup $filterLookup,
		private readonly LoggerInterface $logger,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( ConsequencesExecutor::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * @param ActionSpecifier $specifier
	 * @param VariableHolder $vars
	 * @return ConsequencesExecutor
	 */
	public function newExecutor( ActionSpecifier $specifier, VariableHolder $vars ): ConsequencesExecutor {
		return new ConsequencesExecutor(
			$this->consLookup,
			$this->consFactory,
			$this->consRegistry,
			$this->filterLookup,
			$this->logger,
			$this->userIdentityUtils,
			$this->options,
			$specifier,
			$vars
		);
	}
}
