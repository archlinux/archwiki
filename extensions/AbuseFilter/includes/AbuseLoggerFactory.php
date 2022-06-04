<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

class AbuseLoggerFactory {
	public const SERVICE_NAME = 'AbuseFilterAbuseLoggerFactory';

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
	/** @var ILoadBalancer */
	private $loadBalancer;
	/** @var ServiceOptions */
	private $options;
	/** @var string */
	private $wikiID;
	/** @var string */
	private $requestIP;

	/**
	 * @param CentralDBManager $centralDBManager
	 * @param FilterLookup $filterLookup
	 * @param VariablesBlobStore $varBlobStore
	 * @param VariablesManager $varManager
	 * @param EditRevUpdater $editRevUpdater
	 * @param ILoadBalancer $loadBalancer
	 * @param ServiceOptions $options
	 * @param string $wikiID
	 * @param string $requestIP
	 */
	public function __construct(
		CentralDBManager $centralDBManager,
		FilterLookup $filterLookup,
		VariablesBlobStore $varBlobStore,
		VariablesManager $varManager,
		EditRevUpdater $editRevUpdater,
		ILoadBalancer $loadBalancer,
		ServiceOptions $options,
		string $wikiID,
		string $requestIP
	) {
		$this->centralDBManager = $centralDBManager;
		$this->filterLookup = $filterLookup;
		$this->varBlobStore = $varBlobStore;
		$this->varManager = $varManager;
		$this->editRevUpdater = $editRevUpdater;
		$this->loadBalancer = $loadBalancer;
		$this->options = $options;
		$this->wikiID = $wikiID;
		$this->requestIP = $requestIP;
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
			$this->loadBalancer,
			$this->options,
			$this->wikiID,
			$this->requestIP,
			$title,
			$user,
			$vars
		);
	}
}
