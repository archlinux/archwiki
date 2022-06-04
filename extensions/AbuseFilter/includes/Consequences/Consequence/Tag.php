<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences\Consequence;

use MediaWiki\Extension\AbuseFilter\ActionSpecifier;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;

/**
 * Consequence that adds change tags once the edit is saved
 */
class Tag extends Consequence {
	/** @var string|null */
	private $accountName;
	/** @var string[] */
	private $tags;
	/** @var ChangeTagger */
	private $tagger;

	/**
	 * @param Parameters $parameters
	 * @param string|null $accountName Of the account being created, if this is an account creation
	 * @param string[] $tags
	 * @param ChangeTagger $tagger
	 */
	public function __construct( Parameters $parameters, ?string $accountName, array $tags, ChangeTagger $tagger ) {
		parent::__construct( $parameters );
		$this->accountName = $accountName;
		$this->tags = $tags;
		$this->tagger = $tagger;
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): bool {
		$specifier = new ActionSpecifier(
			$this->parameters->getAction(),
			$this->parameters->getTarget(),
			$this->parameters->getUser(),
			$this->accountName
		);
		$this->tagger->addTags( $specifier, $this->tags );
		return true;
	}
}
