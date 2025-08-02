<?php

/**
 * @covers \MediaWiki\Extension\Notifications\Hooks::onLinksUpdateComplete
 * @group Database
 */
class PageLinkedEventIntegrationTest extends MediaWikiIntegrationTestCase {

	/**
	 * User who created a page should get a notification when a link to it is added on another page.
	 */
	public function testEventCreatedOnEdit() {
		$this->clearHook( 'BeforeEchoEventInsert' );
		$this->overrideConfigValue( 'EchoUseJobQueue', false );

		$creator = new TestUser( 'Creator' );
		$linker = new TestUser( 'Linker' );

		$this->editPage(
			'Target page',
			'',
			'',
			NS_MAIN,
			$creator->getUser()
		);

		$this->editPage(
			'Test page',
			'[[Target page]]',
			'',
			NS_MAIN,
			$linker->getUser()
		);

		// Verify that an event was generated
		$this->assertSelect(
			'echo_event',
			[ 'COUNT(*)' ],
			[ 'event_type' => 'page-linked' ],
			[ [ '1' ] ]
		);
	}

	private function importRevisions( $xml, $performer ) {
		$source = new ImportStringSource( $xml );
		$importer = $this->getServiceContainer()
			->getWikiImporterFactory()
			->getWikiImporter( $source, $performer );
		// `true` means to assign edits to the test users we created
		$importer->setUsernamePrefix( 'import', true );
		$importer->doImport();
	}

	/**
	 * User who created a page should NOT get a notification when a link to it is added in an imported
	 * revision.
	 */
	public function testEventNotCreatedOnImport() {
		$this->clearHook( 'BeforeEchoEventInsert' );
		$this->overrideConfigValue( 'EchoUseJobQueue', false );

		$creator = new TestUser( 'Creator' );
		$linker = new TestUser( 'Linker' );
		$importer = new TestUser( 'Importer' );

		$this->editPage(
			'Target page',
			'',
			'',
			NS_MAIN,
			$creator->getUser()
		);

		// phpcs:disable Generic.Files.LineLength
		$this->importRevisions( <<<'XML'
			<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.11/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.11/ http://www.mediawiki.org/xml/export-0.11.xsd" version="0.11" xml:lang="en">
				<page>
					<title>Test page</title>
					<ns>0</ns>
					<revision>
						<contributor>
							<username>Linker</username>
						</contributor>
						<text xml:space="preserve">[[Target page]]</text>
						<timestamp>2021-02-03T04:05:06Z</timestamp>
					</revision>
				</page>
			</mediawiki>
		XML, $importer->getUser() );
		// phpcs:enable Generic.Files.LineLength

		// Verify that the import worked
		$this->assertSelect(
			'page',
			[ 'COUNT(*)' ],
			[ 'page_namespace' => 0, 'page_title' => 'Test_page' ],
			[ [ '1' ] ]
		);

		// Verify that no event was generated
		$this->assertSelect(
			'echo_event',
			[ 'COUNT(*)' ],
			[ 'event_type' => 'page-linked' ],
			[ [ '0' ] ]
		);
	}

}
