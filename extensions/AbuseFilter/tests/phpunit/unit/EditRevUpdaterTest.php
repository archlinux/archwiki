<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use InvalidArgumentException;
use MediaWiki\Extension\AbuseFilter\CentralDBManager;
use MediaWiki\Extension\AbuseFilter\EditRevUpdater;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionLookup;
use MediaWikiUnitTestCase;
use Title;
use TitleValue;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use WikiPage;

/**
 * @group Test
 * @group AbuseFilter
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\EditRevUpdater
 */
class EditRevUpdaterTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf(
			EditRevUpdater::class,
			new EditRevUpdater(
				$this->createMock( CentralDBManager::class ),
				$this->createMock( RevisionLookup::class ),
				$this->createMock( ILoadBalancer::class ),
				''
			)
		);
	}

	/**
	 * @param DBConnRef|null $localDB
	 * @param IDatabase|null $centralDB
	 * @param RevisionLookup|null $revLookup
	 * @return EditRevUpdater
	 */
	private function getUpdater(
		DBConnRef $localDB = null,
		IDatabase $centralDB = null,
		RevisionLookup $revLookup = null
	): EditRevUpdater {
		$localDB = $localDB ?? $this->createMock( DBConnRef::class );
		$lb = $this->createMock( ILoadBalancer::class );
		$lb->method( 'getConnectionRef' )->willReturn( $localDB );
		$centralDB = $centralDB ?? $this->createMock( IDatabase::class );
		$dbManager = $this->createMock( CentralDBManager::class );
		$dbManager->method( 'getConnection' )->willReturn( $centralDB );
		return new EditRevUpdater(
			$dbManager,
			$revLookup ?? $this->createMock( RevisionLookup::class ),
			$lb,
			'fake-wiki-id'
		);
	}

	/**
	 * @param LinkTarget $target
	 * @return array
	 */
	private function getPageAndRev( LinkTarget $target ): array {
		$title = Title::newFromLinkTarget( $target );
		// Legacy code. Yay.
		$title->mArticleID = 123456;
		return [ new WikiPage( $title ), new MutableRevisionRecord( $title ) ];
	}

	/**
	 * @covers ::updateRev
	 * @covers ::getCacheKey
	 */
	public function testUpdateRev_noIDs() {
		$titleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater' );
		$this->assertFalse( $this->getUpdater()->updateRev( ...$this->getPageAndRev( $titleValue ) ) );
	}

	/**
	 * @covers ::setLastEditPage
	 * @covers ::setLogIdsForTarget
	 * @covers ::updateRev
	 * @covers ::getCacheKey
	 */
	public function testUpdateRev_differentPages() {
		$titleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater' );
		$updater = $this->getUpdater();
		$title = Title::makeTitle( NS_HELP, 'Foobar' );
		// Legacy code. Yay.
		$title->mArticleID = 123456;
		$updater->setLastEditPage( new WikiPage( $title ) );
		$updater->setLogIdsForTarget( $titleValue, [ 'local' => [ 1, 2 ], 'global' => [] ] );
		$this->assertFalse( $updater->updateRev( ...$this->getPageAndRev( $titleValue ) ) );
	}

	/**
	 * @covers ::setLastEditPage
	 * @covers ::setLogIdsForTarget
	 * @covers ::updateRev
	 * @covers ::getCacheKey
	 */
	public function testUpdateRev_nullEdit() {
		$titleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater' );
		[ $page, $rev ] = $this->getPageAndRev( $titleValue );
		$rev->setParentId( 42 );
		$revLookup = $this->createMock( RevisionLookup::class );
		$revLookup->expects( $this->once() )->method( 'getRevisionById' )->with( 42 )->willReturn( $rev );
		$updater = $this->getUpdater( null, null, $revLookup );
		$updater->setLastEditPage( $page );
		$updater->setLogIdsForTarget( $titleValue, [ 'local' => [ 1 ], 'global' => [ 1 ] ] );

		$this->assertFalse( $updater->updateRev( $page, $rev ) );
	}

	/**
	 * @param array $ids
	 * @covers ::setLastEditPage
	 * @covers ::setLogIdsForTarget
	 * @covers ::updateRev
	 * @covers ::getCacheKey
	 * @dataProvider provideIDsSuccess
	 */
	public function testUpdateRev_success( array $ids ) {
		$titleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater' );
		[ $page, $rev ] = $this->getPageAndRev( $titleValue );
		$localDB = $this->createMock( DBConnRef::class );
		$localDB->expects( $ids['local'] ? $this->once() : $this->never() )->method( 'update' );
		$centralDB = $this->createMock( IDatabase::class );
		$centralDB->expects( $ids['global'] ? $this->once() : $this->never() )->method( 'update' );
		$updater = $this->getUpdater( $localDB, $centralDB );
		$updater->setLastEditPage( $page );
		$updater->setLogIdsForTarget( $titleValue, $ids );

		$this->assertTrue( $updater->updateRev( $page, $rev ) );
	}

	public function provideIDsSuccess(): array {
		return [
			'local only' => [ [ 'local' => [ 1, 2 ], 'global' => [] ] ],
			'global only' => [ [ 'local' => [], 'global' => [ 1, 2 ] ] ],
			'local and global' => [ [ 'local' => [ 1, 2 ], 'global' => [ 1, 2 ] ] ],
		];
	}

	/**
	 * @covers ::setLastEditPage
	 * @covers ::setLogIdsForTarget
	 * @covers ::updateRev
	 * @covers ::getCacheKey
	 */
	public function testUpdateRev_multipleTitles() {
		$goodTitleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater' );
		$badTitleValue = new TitleValue( NS_PROJECT, 'These should not be used' );
		$goodIDs = [ 'local' => [ 1, 2 ], 'global' => [] ];
		$badIDs = [ 'local' => [], 'global' => [ 1, 2 ] ];
		[ $page, $rev ] = $this->getPageAndRev( $goodTitleValue );
		$localDB = $this->createMock( DBConnRef::class );
		$localDB->expects( $this->once() )->method( 'update' );
		$centralDB = $this->createMock( IDatabase::class );
		$centralDB->expects( $this->never() )->method( 'update' );
		$updater = $this->getUpdater( $localDB, $centralDB );
		$updater->setLastEditPage( $page );
		$updater->setLogIdsForTarget( $goodTitleValue, $goodIDs );
		$updater->setLogIdsForTarget( $badTitleValue, $badIDs );

		$this->assertTrue( $updater->updateRev( $page, $rev ) );
	}

	/**
	 * @covers ::clearLastEditPage
	 * @covers ::updateRev
	 */
	public function testClearLastEditPage() {
		$titleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater-clear' );
		[ $page, $revisionRecord ] = $this->getPageAndRev( $titleValue );
		$updater = $this->getUpdater();
		$updater->setLastEditPage( $page );
		$updater->setLogIdsForTarget( $titleValue, [ 'local' => [ 1, 2 ], 'global' => [] ] );
		$updater->clearLastEditPage();
		$this->assertFalse( $updater->updateRev( $page, $revisionRecord ) );
	}

	/**
	 * @param array $ids
	 * @covers ::setLogIdsForTarget
	 * @dataProvider provideInvalidIDs
	 */
	public function testSetLogIdsForTarget_invalid( array $ids ) {
		$updater = $this->getUpdater();
		$this->expectException( InvalidArgumentException::class );
		$updater->setLogIdsForTarget( new TitleValue( NS_MAIN, 'x' ), $ids );
	}

	public function provideInvalidIDs(): array {
		return [
			'empty' => [ [] ],
			'missing key' => [ [ 'local' => [ 1 ] ] ],
			'extra key' => [ [ 'local' => [ 1 ], 'global' => [ 1 ], 'foo' => [ 1 ] ] ],
		];
	}
}
