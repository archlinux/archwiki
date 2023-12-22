<?php

namespace MediaWiki\Extension\DiscussionTools\Tests;

use ImportStringSource;
use MediaWiki\MediaWikiServices;
use TestUser;

/**
 * @group DiscussionTools
 * @group Database
 * @covers \MediaWiki\Extension\DiscussionTools\ThreadItemStore
 */
class ThreadItemStoreTest extends IntegrationTestCase {

	/** @var @inheritDoc */
	protected $tablesUsed = [
		'user',
		'page',
		'revision',
		'discussiontools_items',
		'discussiontools_item_pages',
		'discussiontools_item_revisions',
		'discussiontools_item_ids',
	];

	/**
	 * @dataProvider provideInsertCases
	 */
	public function testInsertThreadItems( string $dir ): void {
		if (
			$this->db->getType() === 'mysql' &&
			strpos( $this->db->getSoftwareLink(), 'MySQL' ) &&
			!$this->getCliArg( 'use-normal-tables' )
		) {
			$this->markTestSkipped( 'Set PHPUNIT_USE_NORMAL_TABLES=1 env variable to run these tests, ' .
				'otherwise they would fail due to a MySQL bug with temporary tables (T256006)' );
		}

		// Create users for the imported revisions
		new TestUser( 'X' );
		new TestUser( 'Y' );
		new TestUser( 'Z' );

		// Import revisions
		$source = new ImportStringSource( static::getText( "$dir/dump.xml" ) );
		$importer = MediaWikiServices::getInstance()
			->getWikiImporterFactory()
			->getWikiImporter( $source );
		// `true` means to assign edits to the users we created above
		$importer->setUsernamePrefix( 'import', true );
		$importer->doImport();

		// Check that expected data has been stored in the database
		$expected = [];
		$actual = [];
		$tables = [
			'discussiontools_items' => [ 'it_id' ],
			'discussiontools_item_pages' => [ 'itp_id' ],
			// We reuse rows causing the primary key to be all out of order.
			// Use a consistent ordering for the output here.
			'discussiontools_item_revisions' => [ 'itr_revision_id', 'itr_items_id', 'itr_itemid_id' ],
			'discussiontools_item_ids' => [ 'itid_id' ],
		];
		foreach ( $tables as $table => $order ) {
			$expected[$table] = static::getJson( "../$dir/$table.json", true );

			$res = wfGetDb( DB_REPLICA )->newSelectQueryBuilder()
				->from( $table )
				->field( '*' )
				->caller( __METHOD__ )
				->orderBy( $order )
				->fetchResultSet();
			foreach ( $res as $i => $row ) {
				foreach ( $row as $key => $val ) {
					$actual[$table][$i][$key] = $val;
				}
			}
		}

		// Optionally write updated content to the JSON files
		if ( getenv( 'DISCUSSIONTOOLS_OVERWRITE_TESTS' ) ) {
			foreach ( $tables as $table => $order ) {
				static::overwriteJsonFile( "../$dir/$table.json", $actual[$table] );
			}
		}

		static::assertEquals( $expected, $actual );
	}

	public static function provideInsertCases(): array {
		return [
			[ 'cases/ThreadItemStore/1simple-example' ],
			[ 'cases/ThreadItemStore/2archived-section' ],
			[ 'cases/ThreadItemStore/3indistinguishable-comments' ],
			[ 'cases/ThreadItemStore/4transcluded-section' ],
			[ 'cases/ThreadItemStore/5changed-comment-indentation' ],
			[ 'cases/ThreadItemStore/6changed-heading-level' ],
			[ 'cases/ThreadItemStore/7identical-rev-timestamp' ],
			[ 'cases/ThreadItemStore/8indistinguishable-comments-same-page' ],
		];
	}
}
