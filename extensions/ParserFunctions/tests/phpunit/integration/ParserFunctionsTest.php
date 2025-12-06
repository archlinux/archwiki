<?php

namespace MediaWiki\Extension\ParserFunctions\Tests;

use MediaWiki\MainConfigNames;
use MediaWiki\Page\WikiPage;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\Title;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \MediaWiki\Extension\ParserFunctions\ParserFunctions
 */
class ParserFunctionsTest extends \MediaWikiIntegrationTestCase {
	public function testIfexist() {
		$statsHelper = StatsFactory::newUnitTestingHelper();
		$this->setService( 'StatsFactory', $statsHelper->getStatsFactory() );
		$this->overrideConfigValue( MainConfigNames::CacheEpoch, '20030516000000' );
		ConvertibleTimestamp::setFakeTime( '2025-01-01T11:00:00' );

		// Edit the source page
		$status = $this->editPage( 'Source', '{{#ifexist:Target|yes|no}}' );
		$this->assertStatusGood( $status );
		$sourceId = $status->getNewRevision()->getPageId();

		// Run updates, so that the link is inserted
		$this->runDeferredUpdates();

		// Cache its ParserOutput as if for a page view
		$parserOutput = $this->parse( 'Source' );

		// Confirm that the parse worked
		$this->assertSame( "<p>no\n</p>", $parserOutput->getRawText() );
		$linkInfos = $parserOutput->getLinkList( ParserOutputLinkTypes::EXISTENCE );
		$linkStrings = [];
		foreach ( $linkInfos as $link ) {
			$linkStrings[] = (string)$link['link'];
		}
		$this->assertSame( [ '0:Target' ], $linkStrings );

		// Confirm that there is nothing in pagelinks
		$this->newSelectQueryBuilder()
			->select( 'pl_from' )
			->from( 'pagelinks' )
			->caller( __METHOD__ )
			->assertEmptyResult();

		// Confirm that there is something in existencelinks
		$this->newSelectQueryBuilder()
			->select( [ 'exl_from', 'lt_namespace', 'lt_title' ] )
			->from( 'existencelinks' )
			->join( 'linktarget', null, 'lt_id=exl_target_id' )
			->caller( __METHOD__ )
			->assertRowValue( [ $sourceId, '0', 'Target' ] );

		// Wait one hour
		$fakeTime = '2025-01-01T12:00:00';
		ConvertibleTimestamp::setFakeTime( $fakeTime );

		// Confirm that the parser cache works
		$statsHelper->consumeAllFormatted();
		$this->parse( 'Source' );
		$this->assertSame( 1,
			$statsHelper->count( 'parseroutputaccess_cache_total{type=hit}' )
		);

		// Create the target page
		$this->editPage( 'Target', 'x' );

		// Run jobs, so that page_touched is updated
		$this->runJobs( [], [ 'type' => 'htmlCacheUpdate' ] );
		$this->assertTouched( 'Source', $fakeTime );

		// The parser cache should now be invalidated
		$statsHelper->consumeAllFormatted();
		$parserOutput = $this->parse( 'Source' );
		$this->assertEquals( "<p>yes\n</p>", $parserOutput->getRawText() );
		$this->assertSame( 1,
			$statsHelper->count( 'parseroutputaccess_cache_total{type=miss}' )
		);

		// Delete the target page and check page_touched
		$fakeTime = '2025-01-01T13:00:00';
		ConvertibleTimestamp::setFakeTime( $fakeTime );
		$this->deletePage( 'Target' );
		$this->runJobs( [], [ 'type' => 'htmlCacheUpdate' ] );
		$this->assertTouched( 'Source', $fakeTime );
	}

	private function assertTouched( string $title, string $time ) {
		$this->newSelectQueryBuilder()
			->select( 'page_touched' )
			->from( 'page' )
			->where( [ 'page_namespace' => 0, 'page_title' => $title ] )
			->caller( __METHOD__ )
			->assertFieldValue( $this->getDb()->timestamp( $time ) );
	}

	private function parse( string $title ): ParserOutput {
		$parserOutputAccess = $this->getServiceContainer()->getParserOutputAccess();
		$parserOutputAccess->clearLocalCache();
		$parserOptions = ParserOptions::newFromAnon();
		$status = $parserOutputAccess->getParserOutput(
			new WikiPage( Title::newFromText( $title ) ),
			$parserOptions
		);
		$this->assertStatusGood( $status );
		return $status->value;
	}
}
