<?php

namespace MediaWiki\Extension\TemplateData;

use MediaWiki\Language\Language;
use MessageLocalizer;

/**
 * @license GPL-2.0-or-later
 * @codeCoverageIgnore Trivial facade
 */
class TemplateDataMessageLocalizer implements MessageLocalizer {

	public function __construct(
		private readonly Language $language,
	) {
	}

	/** @inheritDoc */
	public function msg( $key, ...$params ) {
		return wfMessage( $key, ...$params )->inLanguage( $this->language );
	}

}
