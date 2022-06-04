<?php

use MediaWiki\Revision\SlotRecord;
use Psr\Log\NullLogger;

/**
 * @group Database
 * @coversDefaultClass ImportableOldRevisionImporter
 */
class ImportableOldRevisionImporterTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed[] = 'change_tag';
		$this->tablesUsed[] = 'change_tag_def';

		ChangeTags::defineTag( 'tag1' );
	}

	public function provideTestCases() {
		yield [ [] ];
		yield [ [ "tag1" ] ];
	}

	/**
	 * @covers ::import
	 * @dataProvider provideTestCases
	 */
	public function testImport( $expectedTags ) {
		$services = $this->getServiceContainer();

		$title = Title::newFromText( __CLASS__ . rand() );
		$revision = new WikiRevision( $services->getMainConfig() );
		$revision->setTitle( $title );
		$revision->setTags( $expectedTags );
		$content = ContentHandler::makeContent( 'dummy edit', $title );
		$revision->setContent( SlotRecord::MAIN, $content );

		$importer = new ImportableOldRevisionImporter(
			true,
			new NullLogger(),
			$services->getDBLoadBalancer(),
			$services->getRevisionStore(),
			$services->getSlotRoleRegistry(),
			$services->getWikiPageFactory(),
			$services->getPageUpdaterFactory(),
			$services->getUserFactory()
		);
		$result = $importer->import( $revision );
		$this->assertTrue( $result );

		$page = WikiPage::factory( $title );
		$tags = ChangeTags::getTags(
			$services->getDBLoadBalancer()->getConnection( DB_PRIMARY ),
			null,
			$page->getLatest()
		);
		$this->assertSame( $expectedTags, $tags );
	}
}
