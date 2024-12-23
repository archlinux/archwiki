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

use MediaWiki\Linter\LintError;
use MediaWiki\Linter\RecordLintJob;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\UpdateQueryBuilder;

/**
 * @group Database
 * @covers \MediaWiki\Linter\RecordLintJob
 */
class RecordLintJobTest extends MediaWikiIntegrationTestCase {

	private function getDatabase() {
		return $this->getServiceContainer()->get( 'Linter.Database' );
	}

	private function newRecordLintJob( PageReference $page, array $params ) {
		$services = $this->getServiceContainer();
		return new RecordLintJob(
			$page,
			$params,
			$services->get( 'Linter.TotalsLookup' ),
			$this->getDatabase(),
			$services->get( 'Linter.CategoryManager' )
		);
	}

	/**
	 * @param string $titleText
	 * @param int|null $ns
	 * @return array
	 */
	private function createTitleAndPage( string $titleText, ?int $ns = 0 ) {
		$title = Title::newFromText( $titleText, $ns );
		$page = $this->getExistingTestPage( $title );

		return [
			'title' => $title,
			'pageID' => $page->getRevisionRecord()->getPageId(),
			'revID' => $page->getRevisionRecord()->getID()
		];
	}

	/**
	 * Get just the lint error linter_tag field value for a page
	 *
	 * @param int $pageId
	 * @return mixed
	 */
	private function getTagForPage( int $pageId ) {
		$queryPageTag = new SelectQueryBuilder( $this->db );
		$queryPageTag
			->select( 'linter_tag' )
			->table( 'linter' )
			->where( [ 'linter_page' => $pageId ] )
			->caller( __METHOD__ );
		return $queryPageTag->fetchField();
	}

	/**
	 * Get just the lint error linter_template field value for a page
	 *
	 * @param int $pageId
	 * @return mixed
	 */
	private function getTemplateForPage( int $pageId ) {
		$queryPageTemplate = new SelectQueryBuilder( $this->db );
		$queryPageTemplate
			->select( 'linter_template' )
			->table( 'linter' )
			->where( [ 'linter_page' => $pageId ] )
			->caller( __METHOD__ );
		return $queryPageTemplate->fetchField();
	}

	/**
	 * Get just the linter_namespace field value from the linter table for a page
	 *
	 * @param int $pageId
	 * @return mixed
	 */
	private function getNamespaceForPage( int $pageId ) {
		$queryLinterPageNamespace = new SelectQueryBuilder( $this->db );
		$queryLinterPageNamespace
			->select( 'linter_namespace' )
			->table( 'linter' )
			->where( [ 'linter_page' => $pageId ] )
			->caller( __METHOD__ );
		return $queryLinterPageNamespace->fetchField();
	}

	/**
	 * Set just the linter_namespace field value from the linter table for a page
	 *
	 * @param int $pageId
	 */
	private function setNamespaceForPageToNull( int $pageId ) {
		$queryLinterPageNamespace = new UpdateQueryBuilder( $this->db );
		$queryLinterPageNamespace
			->update( 'linter' )
			->set( [ 'linter_namespace' => null ] )
			->where( [ 'linter_page' => $pageId ] )
			->caller( __METHOD__ )
			->execute();
	}

	public function testRun() {
		$error = [
			'type' => 'fostered',
			'location' => [ 0, 10 ],
			'params' => [],
			'dbid' => null,
		];
		$titleAndPage = $this->createTitleAndPage( 'TestPage' );
		$job = $this->newRecordLintJob( $titleAndPage[ 'title' ], [
			'errors' => [ $error ],
			'revision' => $titleAndPage[ 'revID' ]
		] );
		$this->assertTrue( $job->run() );
		$db = $this->getDatabase();
		$errorsFromDb = array_values( $db->getForPage( $titleAndPage[ 'pageID' ] ) );
		$this->assertCount( 1, $errorsFromDb );
		$this->assertInstanceOf( LintError::class, $errorsFromDb[ 0 ] );
		$this->assertEquals( $error[ 'type' ], $errorsFromDb[ 0 ]->category );
		$this->assertEquals( $error[ 'location' ], $errorsFromDb[ 0 ]->location );
		$this->assertEquals( $error[ 'params' ], $errorsFromDb[ 0 ]->params );
	}

	public function testWriteTagAndTemplate() {
		$error = [
			'type' => 'obsolete-tag',
			'location' => [ 0, 10 ],
			'params' => [
				"name" => "center",
				"templateInfo" => [ "name" => "Template:Echo" ]
			],
			'dbid' => null,
		];
		$titleAndPage = $this->createTitleAndPage( 'TestPage2' );
		$job = $this->newRecordLintJob( $titleAndPage[ 'title' ], [
			'errors' => [ $error ],
			'revision' => $titleAndPage[ 'revID' ]
		] );
		$this->assertTrue( $job->run() );
		$pageId = $titleAndPage[ 'pageID' ];
		$db = $this->getDatabase();
		$errorsFromDb = array_values( $db->getForPage( $pageId ) );
		$this->assertCount( 1, $errorsFromDb );
		$this->assertInstanceOf( LintError::class, $errorsFromDb[0] );
		$this->assertEquals( $error[ 'type' ], $errorsFromDb[0]->category );
		$this->assertEquals( $error[ 'location' ], $errorsFromDb[0]->location );
		$this->assertEquals( $error[ 'params' ], $errorsFromDb[0]->params );
		$tag = $this->getTagForPage( $pageId );
		$this->assertEquals( $error[ 'params' ][ 'name' ], $tag );
		$template = $this->getTemplateForPage( $pageId );
		$this->assertEquals( $error[ 'params' ][ 'templateInfo' ][ 'name' ], $template );
	}

	public function testWriteTagAndTemplateLengthExceeded() {
		// Verify special case test for write code encountering params with tag and template string lengths exceeded
		$tagWithMoreThan30Characters = "center tag exceeding 30 characters";
		$tagTruncated = "center tag exceeding 30 charac";
		$templateWithMoreThan250Characters = str_repeat( "Template:Echo longer than 250 characters ", 8 );
		$templateTruncated = "Template:Echo longer than 250 characters Template:Echo longer than 250 characters " .
			"Template:Echo longer than 250 characters Template:Echo longer than 250 characters " .
			"Template:Echo longer than 250 characters Template:Echo longer than 250 characters Temp";

		$error = [
			'type' => 'obsolete-tag',
			'location' => [ 0, 10 ],
			'params' => [
				"name" => $tagWithMoreThan30Characters,
				"templateInfo" => [ "name" => $templateWithMoreThan250Characters ]
			],
			'dbid' => null,
		];
		$titleAndPage = $this->createTitleAndPage( 'TestPage2' );
		$job = $this->newRecordLintJob( $titleAndPage[ 'title' ], [
			'errors' => [ $error ],
			'revision' => $titleAndPage[ 'revID' ]
		] );
		$this->assertTrue( $job->run() );
		$pageId = $titleAndPage[ 'pageID' ];
		$db = $this->getDatabase();
		$errorsFromDb = array_values( $db->getForPage( $pageId ) );
		$this->assertCount( 1, $errorsFromDb );
		$this->assertInstanceOf( LintError::class, $errorsFromDb[0] );
		$this->assertEquals( $error[ 'type' ], $errorsFromDb[0]->category );
		$this->assertEquals( $error[ 'location' ], $errorsFromDb[0]->location );
		$this->assertEquals( $error[ 'params' ], $errorsFromDb[0]->params );
		$tag = $this->getTagForPage( $pageId );
		$this->assertEquals( $tagTruncated, $tag );
		$template = $this->getTemplateForPage( $pageId );
		$this->assertEquals( $templateTruncated, $template );
	}

	/**
	 * @param string $titleText
	 * @param int $namespace
	 * @return array
	 */
	private function createTitleAndPageAndRunJob( string $titleText, int $namespace ): array {
		$titleAndPage = $this->createTitleAndPage( $titleText, $namespace );
		$error = [
			'type' => 'fostered',
			'location' => [ 0, 10 ],
			'params' => [],
			'dbid' => null,
		];
		$job = $this->newRecordLintJob( $titleAndPage[ 'title' ], [
			'errors' => [ $error ],
			'revision' => $titleAndPage[ 'revID' ]
		] );
		$this->assertTrue( $job->run() );
		return $titleAndPage;
	}

	/**
	 * @param array $namespaceIds
	 * @param array $setToID
	 * @return array
	 */
	private function createPagesWithNamespace( array $namespaceIds, array $setToID ): array {
		$titleAndPages = [];
		foreach ( $namespaceIds as $index => $namespaceId ) {
			$titleAndPage = $this->createTitleAndPageAndRunJob(
				'TestPageNamespace' . $index,
				intval( $namespaceId ) );
			$titleAndPages[] = $titleAndPage;
			// To test the migration code, set some namespaces to null to simulate having a mix of valid and null values
			if ( !$setToID[ $index ] ) {
				$this->setNamespaceForPageToNull( $titleAndPage['pageID'] );
			}
		}
		return $titleAndPages;
	}

	/**
	 * @param array $pages
	 * @param array $namespaceIds
	 * @return void
	 */
	private function checkPagesNamespace( array $pages, array $namespaceIds ) {
		foreach ( $pages as $index => $page ) {
			$pageId = $page[ 'pageID' ];
			$namespace = $this->getNamespaceForPage( $pageId );
			$namespaceId = $namespaceIds[ $index ];
			$this->assertSame( "$namespaceId", $namespace );
		}
	}

	public function testMigrateNamespace() {
		// Create groups of records that do not need migrating to ensure batching works properly
		$namespaceIds = [ '0', '1', '2', '3', '4', '5', '4', '3', '2', '1', '0', '1', '2' ];
		$setToID = [ false, true, true, true, false, false, true, true, false, false, false, true, false ];

		$titleAndPages = $this->createPagesWithNamespace( $namespaceIds, $setToID );

		// Verify the create page function did not populate the linter_namespace field for TestPageNamespace0
		$pageId = $titleAndPages[ 0 ][ 'pageID' ];
		$namespace = $this->getNamespaceForPage( $pageId );
		$this->assertNull( $namespace );

		// migrate unpopulated namespace_id(s) from the page table to linter table
		$database = $this->getDatabase();
		$database->migrateNamespace( 2, 3, 0 );

		// Verify all linter records now have proper namespace IDs in the linter_namespace field
		$this->checkPagesNamespace( $titleAndPages, $namespaceIds );
	}

	/**
	 * @param string $titleText
	 * @param array $error
	 * @return array
	 */
	private function createTitleAndPageForTagsAndRunJob( string $titleText, array $error ): array {
		$titleAndPage = $this->createTitleAndPage( $titleText );
		$job = $this->newRecordLintJob( $titleAndPage[ 'title' ], [
			'errors' => [ $error ],
			'revision' => $titleAndPage[ 'revID' ]
		] );
		$this->assertTrue( $job->run() );
		return $titleAndPage;
	}

	/**
	 * Set the linter_tag and linter_template field values to an empty string for a page
	 * to test that the database migration code handles existing records with fields set
	 * and records where the fields are not yet set, and need migration.
	 * @param int $pageId
	 */
	private function setTagAndTemplateForPageToEmptyString( int $pageId ) {
		$queryLinterPageNamespace = new UpdateQueryBuilder( $this->db );
		$queryLinterPageNamespace
			->update( 'linter' )
			->set( [ 'linter_template' => '', 'linter_tag' => '' ] )
			->where( [ 'linter_page' => $pageId ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param array $writeEnables
	 * @param array $error
	 * @return array
	 */
	private function createPagesWithTagAndTemplate( array $writeEnables, array $error ): array {
		$titleAndPages = [];
		foreach ( $writeEnables as $index => $enable ) {
			$titleAndPage = $this->createTitleAndPageForTagsAndRunJob( 'TestPage' . $index, $error );
			$titleAndPages[] = $titleAndPage;
			// clear the tag and template field data for select test records
			if ( !$enable ) {
				$this->setTagAndTemplateForPageToEmptyString( $titleAndPage[ 'pageID' ] );
			}
		}
		return $titleAndPages;
	}

	/**
	 * @param array $pages
	 * @return void
	 */
	private function checkPagesTagAndTemplate( array $pages ) {
		foreach ( $pages as $page ) {
			$pageId = $page[ 'pageID' ];
			$tag = $this->getTagForPage( $pageId );
			$this->assertEquals( "center", $tag );
			$template = $this->getTemplateForPage( $pageId );
			$this->assertEquals( "Template:Echo", $template );
		}
	}

	public function testMigrateTagAndTemplate() {
		$error = [
			'type' => 'obsolete-tag',
			'location' => [ 0, 10 ],
			'params' => [ "name" => "center",
				"templateInfo" => [ "name" => "Template:Echo" ] ],
			'dbid' => null,
		];

		// Create groups of records that do not need migrating to ensure batching works properly
		$writeEnables = [ false, true, true, true, false, false, true, true, false, false, false, true, false ];
		$titleAndPages = $this->createPagesWithTagAndTemplate( $writeEnables, $error );

		// Create special case test of migrate code encountering brackets - linter_params = '[]'
		$error = [
			'type' => 'wikilink-in-extlink',
			'location' => [ 0, 10 ],
			'params' => [],
			'dbid' => null,
		];
		$titleAndPageBrackets = $this->createTitleAndPageForTagsAndRunJob(
			'TestPageTagAndTemplateBrackets',
			$error );

		// Create special case test for migrate code encountering 'multi-part-template-block'
		$error = [
			'type' => 'obsolete-tag',
			'location' => [ 0, 10 ],
			'params' => '{"name":"center","templateInfo":{"multiPartTemplateBlock":true}}',
			'dbid' => null,
		];
		$titleAndPageMultipart = $this->createTitleAndPageForTagsAndRunJob(
			'TestPageTagAndTemplateMultipart',
			$error );

		// Create special case test for params containing tag and template info strings exceeding the fields lengths
		$tagWithMoreThan30Characters = "center tag exceeding 30 characters";
		$templateWithMoreThan250Characters = str_repeat( "Template:Echo longer than 250 characters ", 8 );
		$error = [
			'type' => 'obsolete-tag',
			'location' => [ 0, 10 ],
			'params' => [ "name" => $tagWithMoreThan30Characters,
				"templateInfo" => $templateWithMoreThan250Characters ],
			'dbid' => null,
		];
		$titleAndPageLengthExceeded = $this->createTitleAndPageForTagsAndRunJob(
			'TestPageTagAndTemplateLengthExceeded',
			$error );

		// Verify the create page function did not populate the linter_tag and linter_template field for TestPage0
		$pageId = $titleAndPages[ 0 ][ 'pageID' ];
		$tag = $this->getTagForPage( $pageId );
		$this->assertSame( "", $tag );
		$template = $this->getTemplateForPage( $pageId );
		$this->assertSame( "", $template );

		// Migrate unpopulated tag and template info from the params field
		$database = $this->getDatabase();
		$database->migrateTemplateAndTagInfo( 3, 0 );

		// Verify all linter records have the proper tag and template field info migrated from the params field
		$this->checkPagesTagAndTemplate( $titleAndPages );

		// Verify special case test of migrate code encountering brackets - linter_params = '[]'
		$tag = $this->getTagForPage( $titleAndPageBrackets[ 'pageID' ] );
		$this->assertSame( "", $tag );
		$template = $this->getTemplateForPage( $titleAndPageBrackets[ 'pageID' ] );
		$this->assertSame( "", $template );

		// Verify special case test for migrate code encountering 'multi-part-template-block'
		$tag = $this->getTagForPage( $titleAndPageMultipart[ 'pageID' ] );
		$this->assertEquals( "center", $tag );
		$template = $this->getTemplateForPage( $titleAndPageMultipart[ 'pageID' ] );
		$this->assertEquals( "multi-part-template-block", $template );

		// Verify special case test for migrate code encountering params with tag and template string length exceeded
		$tagTruncated = "center tag exceeding 30 charac";
		$templateTruncated = "Template:Echo longer than 250 characters Template:Echo longer than 250 characters " .
			"Template:Echo longer than 250 characters Template:Echo longer than 250 characters " .
			"Template:Echo longer than 250 characters Template:Echo longer than 250 characters Temp";

		$tag = $this->getTagForPage( $titleAndPageLengthExceeded[ 'pageID' ] );
		$this->assertEquals( $tagTruncated, $tag );
		$template = $this->getTemplateForPage( $titleAndPageLengthExceeded[ 'pageID' ] );
		$this->assertEquals( $templateTruncated, $template );
	}

	public function testDropInlineMediaCaptionLints() {
		$error = [
			'type' => 'inline-media-caption',
			'location' => [ 0, 10 ],
			'params' => [],
			'dbid' => null,
		];
		$titleAndPage = $this->createTitleAndPage( 'TestPageMediaCaption' );
		$job = $this->newRecordLintJob( $titleAndPage[ 'title' ], [
			'errors' => [ $error ],
			'revision' => $titleAndPage[ 'revID' ]
		] );
		$this->assertTrue( $job->run() );
		$errorsFromDb = array_values( $this->getDatabase()->getForPage( $titleAndPage['pageID'] ) );
		$this->assertCount( 0, $errorsFromDb );
	}
}
