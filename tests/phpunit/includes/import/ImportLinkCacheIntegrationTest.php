<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;

/**
 * Integration test that checks import success and
 * LinkCache integration.
 *
 * @group large
 * @group Database
 * @covers \ImportStreamSource
 * @covers \ImportReporter
 *
 * @author mwjames
 */
class ImportLinkCacheIntegrationTest extends MediaWikiIntegrationTestCase {

	private $importStreamSource;

	protected function setUp(): void {
		parent::setUp();

		$file = dirname( __DIR__ ) . '/../data/import/ImportLinkCacheIntegrationTest.xml';

		$this->importStreamSource = ImportStreamSource::newFromFile( $file );

		if ( !$this->importStreamSource->isGood() ) {
			$this->fail( "Import source for {$file} failed" );
		}
	}

	public function testImportForImportSource() {
		$this->doImport( $this->importStreamSource );

		// Imported title
		$loremIpsum = Title::makeTitle( NS_MAIN, 'Lorem ipsum' );

		$this->assertSame(
			$loremIpsum->getArticleID(),
			$loremIpsum->getArticleID( IDBAccessObject::READ_LATEST )
		);

		$categoryLoremIpsum = Title::makeTitle( NS_CATEGORY, 'Lorem ipsum' );

		$this->assertSame(
			$categoryLoremIpsum->getArticleID(),
			$categoryLoremIpsum->getArticleID( IDBAccessObject::READ_LATEST )
		);
	}

	/**
	 * @depends testImportForImportSource
	 */
	public function testReImportForImportSource() {
		$this->doImport( $this->importStreamSource );

		// ReImported title
		$loremIpsum = Title::makeTitle( NS_MAIN, 'Lorem ipsum' );

		$this->assertSame(
			$loremIpsum->getArticleID(),
			$loremIpsum->getArticleID( IDBAccessObject::READ_LATEST )
		);

		$categoryLoremIpsum = Title::makeTitle( NS_CATEGORY, 'Lorem ipsum' );

		$this->assertSame(
			$categoryLoremIpsum->getArticleID(),
			$categoryLoremIpsum->getArticleID( IDBAccessObject::READ_LATEST )
		);
	}

	private function doImport( $importStreamSource ) {
		$importer = $this->getServiceContainer()
			->getWikiImporterFactory()
			->getWikiImporter( $importStreamSource->value, $this->getTestSysop()->getAuthority() );
		$importer->setDebug( true );

		$reporter = new ImportReporter(
			$importer,
			false,
			'',
			false,
			new RequestContext()
		);

		$reporter->open();
		$importer->doImport();
		$this->assertStatusGood( $reporter->close() );
	}

}
