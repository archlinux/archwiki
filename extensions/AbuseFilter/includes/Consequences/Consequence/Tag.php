<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences\Consequence;

use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;

/**
 * Consequence that adds change tags once the edit is saved
 */
class Tag extends Consequence {

	public function __construct(
		Parameters $parameters,
		private readonly array $tags,
		private readonly ChangeTagger $tagger
	) {
		parent::__construct( $parameters );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): bool {
		$this->tagger->addTags( $this->parameters->getActionSpecifier(), $this->tags );
		return true;
	}
}
