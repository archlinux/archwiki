<?php

namespace Cite\Tests;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ParserOptions;

/**
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class CiteDbTest extends \MediaWikiIntegrationTestCase {
	/** @inheritDoc */
	protected $tablesUsed = [ 'page' ];

	/**
	 * Edge case where a parser call within `<ref>` parse clears the original parser state.
	 * @see https://phabricator.wikimedia.org/T240248
	 * @covers \Cite\ReferenceStack::pushRef
	 */
	public function testReferenceStackError() {
		$this->insertPage( 'Cite-tracking-category-cite-error', '{{PAGENAME}}', NS_MEDIAWIKI );

		$services = MediaWikiServices::getInstance();
		// Reset the MessageCache in order to force it to clone a new parser.
		$services->resetServiceForTesting( 'MessageCache' );
		$services->getMessageCache()->enable();

		$parserOutput = $services->getParser()->parse(
			'
				<ref name="a">text #1</ref>
				<ref name="a">text #2</ref>
				<ref>text #3</ref>
			',
			Title::makeTitle( NS_MAIN, mt_rand() ),
			ParserOptions::newFromAnon()
		);

		$this->assertStringContainsString(
			'cite_ref-2',
			$parserOutput->getText(),
			'Internal counter should not reset to 1 for text #3'
		);
	}

}
