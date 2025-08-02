<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers\Mocks;

use MediaWiki\Html\TemplateParser;

class MockTemplateParser extends TemplateParser {

	/**
	 * @var array|null the parameters provided on the last call to processTemplate.
	 */
	public $lastCalledWith;

	/**
	 * Mocked processTemplate that returns an empty string.
	 * Does not test the actual processing of the template
	 * but used to unit test the formatRow by validating
	 * that the template parameters are as expected.
	 *
	 * It saves the arguments from the last call to a
	 * property for later access.
	 *
	 * @inheritDoc
	 */
	public function processTemplate( $templateName, $args, array $scopes = [] ) {
		$this->lastCalledWith = [ $templateName, $args, $scopes ];
		return '';
	}
}
