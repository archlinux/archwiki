<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Linter\Test;

use MediaWiki\Content\JavaScriptContent;
use MediaWiki\Content\JavaScriptContentHandler;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Content\WikitextContentHandler;
use MediaWiki\Linter\LintUpdate;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWikiIntegrationTestCase;
use RefreshLinksJob;
use WikiPage;

/**
 * @group Database
 * @covers \MediaWiki\Linter\LintUpdate
 */
class LintUpdateTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			MainConfigNames::ParsoidSettings => [
				'linting' => true
			],
			'LinterParseOnDerivedDataUpdate' => true,
			// Ensure that parser cache contents don't
			// affect tests.
			'ParserCacheType' => CACHE_NONE,
		] );
	}

	/**
	 * Assert that we trigger a parse, and that parse triggers onParserLogLinterData,
	 * and in turn triggers the ParserLogLinterData hook via Parsoid.
	 */
	public function testUpdate() {
		// NOTE: This performs an edit, so do it before installing the temp hook below!
		$rrev = $this->newRenderedRevision();

		$hookCalled = false;
		$this->setTemporaryHook( 'ParserLogLinterData', static function () use ( &$hookCalled ) {
			$hookCalled = true;
		}, false );

		$update = $this->newLintUpdate( $rrev );
		$update->doUpdate();

		$this->assertTrue( $hookCalled );
	}

	/**
	 * Assert that we don't parse if the content model is not supported.
	 */
	public function testSkipModel() {
		$contentHandler = $this->createNoOpMock(
			JavaScriptContentHandler::class,
			[ 'getParserOutput' ]
		);
		$contentHandler->expects( $this->never() )->method( 'getParserOutput' );
		$contentHandlers = $this->getConfVar( MainConfigNames::ContentHandlers );
		$this->overrideConfigValue( MainConfigNames::ContentHandlers, [
			CONTENT_MODEL_JAVASCRIPT => [
				'factory' => fn () => $contentHandler,
			],
		] + $contentHandlers );

		$page = $this->getExistingTestPage();
		$rev = new MutableRevisionRecord( $page );
		$rev->setSlot(
			SlotRecord::newUnsaved(
				SlotRecord::MAIN,
				new JavascriptContent( '{}' )
			)
		);
		// Clear the local cache in the ParserOutputAccess
		$this->resetServices();

		$update = $this->newLintUpdate( $this->newRenderedRevision( $page, $rev ) );
		$update->doUpdate();
	}

	/**
	 * Assert that we don't parse if the given revision is no longer the
	 * latest revision.
	 */
	public function testSkipOld() {
		// This may use the "real" wikitext content handler
		$page = $this->getExistingTestPage();
		$rev = new MutableRevisionRecord( $page );
		$rev->setSlot(
			SlotRecord::newUnsaved(
				SlotRecord::MAIN,
				new WikitextContent( 'bla bla' )
			)
		);

		// make it not the current revision
		$rev->setId( $page->getLatest() - 1 );
		$newRev = $this->newRenderedRevision( $page, $rev );

		// Ok, now set up a mock content handler for the remainder
		$contentHandler = $this->createNoOpMock(
			WikitextContentHandler::class,
			[ 'getParserOutput' ]
		);
		$contentHandler->expects( $this->never() )->method( 'getParserOutput' );
		$contentHandlers = $this->getConfVar( MainConfigNames::ContentHandlers );
		$this->overrideConfigValue( MainConfigNames::ContentHandlers, [
			CONTENT_MODEL_WIKITEXT => [
				'factory' => fn () => $contentHandler,
			],
		] + $contentHandlers );

		// Clear the local cache in the ParserOutputAccess
		$this->resetServices();

		$update = $this->newLintUpdate( $newRev );
		$update->doUpdate();
	}

	/**
	 * Assert that a LintUpdate is triggered on edit through the RevisionDataUpdates
	 * hook, and in turn triggers the ParserLogLinterData hook via Parsoid.
	 *
	 * @covers \MediaWiki\Linter\Hooks::onRevisionDataUpdates
	 */
	public function testPageEditIntegration() {
		// Clear the local cache in the ParserOutputAccess
		$this->resetServices();

		$hookCalled = 0;
		$this->setTemporaryHook( 'ParserLogLinterData', static function () use ( &$hookCalled ) {
			$hookCalled++;
		}, false );

		$status = $this->editPage( 'JustSomePage', new WikitextContent( 'hello world' ) );

		$this->assertSame( 1, $hookCalled );

		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $status->getNewRevision()->getPage() );
	}

	/**
	 * Assert that a LintUpdate is triggered from RefreshLinksJob through the
	 * RevisionDataUpdates hook, and in turn triggers the ParserLogLinterData
	 * hook via Parsoid.
	 *
	 * @covers \MediaWiki\Linter\Hooks::onRevisionDataUpdates
	 */
	public function testRefreshLinksJobIntegration() {
		// NOTE: This performs an edit, so do it before installing the temp hook below!
		$page = $this->getExistingTestPage();
		// Clear the local cache in the ParserOutputAccess
		$this->resetServices();

		$hookCalled = 0;
		$this->setTemporaryHook( 'ParserLogLinterData', static function () use ( &$hookCalled ) {
			$hookCalled++;
		}, false );

		$job = new RefreshLinksJob( $page, [] );
		$job->run();
		$this->assertSame( 1, $hookCalled );
	}

	private function newRenderedRevision( ?WikiPage $page = null, ?RevisionRecord $rev = null ) {
		$page = $this->getExistingTestPage();
		$title = $page->getTitle();

		$rev ??= $this->getServiceContainer()->getRevisionLookup()->getRevisionByTitle( $title );
		$pOpt = $page->makeParserOptions( 'canonical' );

		$rrev = new RenderedRevision(
			$rev,
			$pOpt,
			$this->getServiceContainer()->getContentRenderer(),
			static function () {
			}
		);

		$rrev->setRevisionParserOutput( new ParserOutput( 'testing' ) );
		// Clear the local cache in the ParserOutputAccess
		$this->resetServices();

		return $rrev;
	}

	private function newLintUpdate( RenderedRevision $renderedRevision ) {
		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();
		$parserOutputAccess = $this->getServiceContainer()->getParserOutputAccess();

		return new LintUpdate(
			$wikiPageFactory,
			$parserOutputAccess,
			$renderedRevision
		);
	}

}
