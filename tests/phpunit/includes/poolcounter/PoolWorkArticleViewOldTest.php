<?php

use MediaWiki\Json\JsonCodec;
use MediaWiki\Parser\RevisionOutputCache;
use MediaWiki\Revision\RevisionRecord;
use Psr\Log\NullLogger;

/**
 * @covers PoolWorkArticleViewOld
 * @group Database
 */
class PoolWorkArticleViewOldTest extends PoolWorkArticleViewTest {

	/** @var RevisionOutputCache */
	private $cache = null;

	/**
	 * @param WikiPage $page
	 * @param RevisionRecord|null $rev
	 * @param ParserOptions|null $options
	 *
	 * @return PoolWorkArticleViewOld
	 */
	protected function newPoolWorkArticleView(
		WikiPage $page,
		RevisionRecord $rev = null,
		$options = null
	) {
		if ( !$options ) {
			$options = ParserOptions::newFromAnon();
		}

		if ( !$rev ) {
			$rev = $page->getRevisionRecord();
		}

		if ( !$this->cache ) {
			$this->installRevisionOutputCache();
		}

		$renderer = $this->getServiceContainer()->getRevisionRenderer();

		return new PoolWorkArticleViewOld(
			'test:' . $rev->getId(),
			$this->cache,
			$rev,
			$options,
			$renderer,
			$this->getLoggerSpi()
		);
	}

	/**
	 * @param BagOStuff|null $bag
	 *
	 * @return RevisionOutputCache
	 */
	private function installRevisionOutputCache( $bag = null ) {
		$this->cache = new RevisionOutputCache(
			'test',
			new WANObjectCache( [ 'cache' => $bag ?: new HashBagOStuff() ] ),
			60 * 60,
			'20200101223344',
			new JsonCodec(),
			new NullStatsdDataFactory(),
			new NullLogger()
		);

		return $this->cache;
	}

	public function testUpdateCachedOutput() {
		$options = ParserOptions::newFromAnon();
		$page = $this->getExistingTestPage( __METHOD__ );

		$cache = $this->installRevisionOutputCache();

		$work = $this->newPoolWorkArticleView( $page, null, $options );
		/** @var Status $status */
		$status = $work->execute();
		$this->assertTrue( $status->isGood() );

		$cachedOutput = $cache->get( $page->getRevisionRecord(), $options );
		$this->assertNotEmpty( $cachedOutput );
		$this->assertSame( $status->getValue()->getText(), $cachedOutput->getText() );
	}

	public function testDoesNotCacheNotSafe() {
		$page = $this->getExistingTestPage( __METHOD__ );

		$cache = $this->installRevisionOutputCache();

		$parserOptions = ParserOptions::newFromAnon();
		$parserOptions->setWrapOutputClass( 'wrapwrap' ); // Not safe to cache!

		$work = $this->newPoolWorkArticleView( $page, null, $parserOptions );
		/** @var Status $status */
		$status = $work->execute();
		$this->assertTrue( $status->isGood() );

		$this->assertFalse( $cache->get( $page->getRevisionRecord(), $parserOptions ) );
	}

	public function testDoWorkWithFakeRevision() {
		// PoolWorkArticleViewOld caches the results, but things with null revid should
		// not be cached.
		$this->expectException( InvalidArgumentException::class );
		parent::testDoWorkWithFakeRevision();
	}
}
