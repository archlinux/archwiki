<?php

namespace MediaWiki\Extension\TemplateData;

use Language;
use MessageLocalizer;

/**
 * @codeCoverageIgnore Trivial facade
 */
class TemplateDataMessageLocalizer implements MessageLocalizer {

	/** @var Language */
	private $language;

	/**
	 * @param Language $language
	 */
	public function __construct( Language $language ) {
		$this->language = $language;
	}

	/** @inheritDoc */
	public function msg( $key, ...$params ) {
		return wfMessage( $key, ...$params )->inLanguage( $this->language );
	}

}
