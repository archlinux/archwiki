<?php

namespace MediaWiki\Tests\Unit\Revision;

use CommentStoreComment;
use DummyContentForTesting;
use InvalidArgumentException;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\MutableRevisionSlots;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\RevisionSlotsUpdate;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Assert\PreconditionException;

/**
 * @covers \MediaWiki\Revision\MutableRevisionRecord
 * @covers \MediaWiki\Revision\RevisionRecord
 */
class MutableRevisionRecordTest extends MediaWikiUnitTestCase {
	use RevisionRecordTests;

	/**
	 * @param array $rowOverrides
	 *
	 * @return MutableRevisionRecord
	 */
	protected function newRevision( array $rowOverrides = [] ) {
		$user = new UserIdentityValue( 11, 'Tester' );
		$comment = CommentStoreComment::newUnsavedComment( 'Hello World' );
		$wikiId = $rowOverrides['wikiId'] ?? RevisionRecord::LOCAL;

		$record = new MutableRevisionRecord(
			new PageIdentityValue( 17, NS_MAIN, 'Dummy', $wikiId ),
			$wikiId
		);

		if ( isset( $rowOverrides['rev_deleted'] ) ) {
			$record->setVisibility( $rowOverrides['rev_deleted'] );
		}

		if ( isset( $rowOverrides['rev_id'] ) ) {
			$record->setId( $rowOverrides['rev_id'] );
		}

		if ( isset( $rowOverrides['rev_page_id'] ) ) {
			$record->setPageId( $rowOverrides['rev_page_id'] );
		}

		if ( isset( $rowOverrides['rev_parent_id'] ) ) {
			$record->setParentId( $rowOverrides['rev_parent_id'] );
		}

		$record->setContent( SlotRecord::MAIN, new DummyContentForTesting( 'Lorem Ipsum' ) );
		$record->setComment( $comment );
		$record->setUser( $user );
		$record->setTimestamp( '20101010000000' );

		return $record;
	}

	public function provideConstructorFailure() {
		yield 'not a wiki id' => [
			new PageIdentityValue( 17, NS_MAIN, 'Dummy', PageIdentity::LOCAL ),
			InvalidArgumentException::class,
			null,
		];
		yield 'wiki id mismatch' => [
			new PageIdentityValue( 17, NS_MAIN, 'Dummy', 'acme' ),
			PreconditionException::class,
			'blawiki',
		];
	}

	/**
	 * @dataProvider provideConstructorFailure
	 */
	public function testConstructorFailure(
		PageIdentity $page,
		string $expectedException,
		$wikiId = false
	) {
		$this->expectException( $expectedException );
		new MutableRevisionRecord( $page, $wikiId );
	}

	public function testSetGetId() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertNull( $record->getId() );
		$record->setId( 888 );
		$this->assertSame( 888, $record->getId() );
	}

	public function testSetGetUser() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$user = new UserIdentityValue( 0, 'Bla' );
		$this->assertNull( $record->getUser() );
		$record->setUser( $user );
		$this->assertSame( $user, $record->getUser() );
	}

	public function testSetGetPageId() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 0, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertSame( 0, $record->getPageId() );
		$record->setPageId( 999 );
		$this->assertSame( 999, $record->getPageId() );
	}

	public function testSetGetParentId() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertNull( $record->getParentId() );
		$record->setParentId( 100 );
		$this->assertSame( 100, $record->getParentId() );
	}

	public function testGetMainContentWhenEmpty() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->expectException( RevisionAccessException::class );
		$this->assertNull( $record->getContent( SlotRecord::MAIN ) );
	}

	public function testSetGetMainContent() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$content = new DummyContentForTesting( 'Badger' );
		$record->setContent( SlotRecord::MAIN, $content );
		$this->assertSame( $content, $record->getContent( SlotRecord::MAIN ) );
	}

	public function testGetSlotWhenEmpty() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertFalse( $record->hasSlot( SlotRecord::MAIN ) );

		$this->expectException( RevisionAccessException::class );
		$record->getSlot( SlotRecord::MAIN );
	}

	public function testSetGetSlot() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$slot = SlotRecord::newUnsaved(
			SlotRecord::MAIN,
			new DummyContentForTesting( 'x' )
		);
		$record->setSlot( $slot );
		$this->assertTrue( $record->hasSlot( SlotRecord::MAIN ) );
		$this->assertSame( $slot, $record->getSlot( SlotRecord::MAIN ) );
	}

	public function testSetGetMinor() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertFalse( $record->isMinor() );
		$record->setMinorEdit( true );
		$this->assertSame( true, $record->isMinor() );
	}

	public function testSetGetTimestamp() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertNull( $record->getTimestamp() );
		$record->setTimestamp( '20180101010101' );
		$this->assertSame( '20180101010101', $record->getTimestamp() );
	}

	public function testSetGetVisibility() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertSame( 0, $record->getVisibility() );
		$record->setVisibility( RevisionRecord::DELETED_USER );
		$this->assertSame( RevisionRecord::DELETED_USER, $record->getVisibility() );
	}

	public function testSetGetSha1() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertSame( 'phoiac9h4m842xq45sp7s6u21eteeq1', $record->getSha1() );
		$record->setSha1( 'someHash' );
		$this->assertSame( 'someHash', $record->getSha1() );
	}

	public function testResetSha1() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);

		$record->setContent( SlotRecord::MAIN, new DummyContentForTesting( 'foo' ) );
		$fooHash = $record->getSha1();

		// setting the content directly updates the hash
		$record->setContent( SlotRecord::MAIN, new DummyContentForTesting( 'barx' ) );
		$barxHash = $record->getSha1();
		$this->assertNotSame( $fooHash, $barxHash );

		// setting the content indirectly also updates the hash
		$record->getSlots()->setContent( 'aux', new DummyContentForTesting( 'frump' ) );
		$frumpHash = $record->getSha1();
		$this->assertNotSame( $barxHash, $frumpHash );
	}

	public function testGetSlots() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertInstanceOf( MutableRevisionSlots::class, $record->getSlots() );
	}

	public function testSetGetSize() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertSame( 0, $record->getSize() );
		$record->setSize( 775 );
		$this->assertSame( 775, $record->getSize() );
	}

	public function testResetSize() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);

		$record->setContent( SlotRecord::MAIN, new DummyContentForTesting( 'foo' ) );
		$fooSize = $record->getSize();

		// setting the content directly updates the hash
		$record->setContent( SlotRecord::MAIN, new DummyContentForTesting( 'barx' ) );
		$barxSize = $record->getSize();
		$this->assertNotSame( $fooSize, $barxSize );

		// setting the content indirectly also updates the hash
		$record->getSlots()->setContent( 'aux', new DummyContentForTesting( 'frump' ) );
		$frumpSize = $record->getSize();
		$this->assertNotSame( $barxSize, $frumpSize );
	}

	public function testSetGetComment() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$comment = new CommentStoreComment( 1, 'foo' );
		$this->assertNull( $record->getComment() );
		$record->setComment( $comment );
		$this->assertSame( $comment, $record->getComment() );
	}

	public function testSimpleGetOriginalAndInheritedSlots() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$mainSlot = new SlotRecord(
			(object)[
				'slot_id' => 1,
				'slot_revision_id' => null, // unsaved
				'slot_content_id' => 1,
				'content_address' => null, // touched
				'model_name' => 'x',
				'role_name' => 'main',
				'slot_origin' => null // touched
			],
			new DummyContentForTesting( 'main' )
		);
		$auxSlot = new SlotRecord(
			(object)[
				'slot_id' => 2,
				'slot_revision_id' => null, // unsaved
				'slot_content_id' => 1,
				'content_address' => 'foo', // inherited
				'model_name' => 'x',
				'role_name' => 'aux',
				'slot_origin' => 1 // inherited
			],
			new DummyContentForTesting( 'aux' )
		);

		$record->setSlot( $mainSlot );
		$record->setSlot( $auxSlot );

		$this->assertSame( [ 'main' ], $record->getOriginalSlots()->getSlotRoles() );
		$this->assertSame( $mainSlot, $record->getOriginalSlots()->getSlot( SlotRecord::MAIN ) );

		$this->assertSame( [ 'aux' ], $record->getInheritedSlots()->getSlotRoles() );
		$this->assertSame( $auxSlot, $record->getInheritedSlots()->getSlot( 'aux' ) );
	}

	public function testSimpleremoveSlot() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);

		$a = new DummyContentForTesting( 'a' );
		$b = new DummyContentForTesting( 'b' );

		$record->inheritSlot( SlotRecord::newSaved( 7, 3, 'a', SlotRecord::newUnsaved( 'a', $a ) ) );
		$record->inheritSlot( SlotRecord::newSaved( 7, 4, 'b', SlotRecord::newUnsaved( 'b', $b ) ) );

		$record->removeSlot( 'b' );

		$this->assertTrue( $record->hasSlot( 'a' ) );
		$this->assertFalse( $record->hasSlot( 'b' ) );
	}

	public function testApplyUpdate() {
		$update = new RevisionSlotsUpdate();

		$a = new DummyContentForTesting( 'a' );
		$b = new DummyContentForTesting( 'b' );
		$c = new DummyContentForTesting( 'c' );
		$x = new DummyContentForTesting( 'x' );

		$update->modifyContent( 'b', $x );
		$update->modifyContent( 'c', $x );
		$update->removeSlot( 'c' );
		$update->removeSlot( 'd' );

		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$record->inheritSlot( SlotRecord::newSaved( 7, 3, 'a', SlotRecord::newUnsaved( 'a', $a ) ) );
		$record->inheritSlot( SlotRecord::newSaved( 7, 4, 'b', SlotRecord::newUnsaved( 'b', $b ) ) );
		$record->inheritSlot( SlotRecord::newSaved( 7, 5, 'c', SlotRecord::newUnsaved( 'c', $c ) ) );

		$record->applyUpdate( $update );

		$this->assertEquals( [ 'b' ], array_keys( $record->getOriginalSlots()->getSlots() ) );
		$this->assertEquals( $a, $record->getSlot( 'a' )->getContent() );
		$this->assertEquals( $x, $record->getSlot( 'b' )->getContent() );
		$this->assertFalse( $record->hasSlot( 'c' ) );
	}

	public function provideNotReadyForInsertion() {
		$title = $this->makeMockTitle( 'Dummy' );
		$user = new UserIdentityValue( 42, 'Test' );

		/** @var CommentStoreComment $comment */
		$comment = $this->createMock( CommentStoreComment::class );

		$content = new DummyContentForTesting( 'Test' );

		$rev = new MutableRevisionRecord( $title );
		yield 'empty' => [ $rev ];

		$rev = new MutableRevisionRecord( $title );
		$rev->setContent( SlotRecord::MAIN, $content );
		$rev->setUser( $user );
		$rev->setComment( $comment );
		yield 'no timestamp' => [ $rev ];

		$rev = new MutableRevisionRecord( $title );
		$rev->setUser( $user );
		$rev->setComment( $comment );
		$rev->setTimestamp( '20101010000000' );
		yield 'no content' => [ $rev ];

		$rev = new MutableRevisionRecord( $title );
		$rev->setContent( SlotRecord::MAIN, $content );
		$rev->setComment( $comment );
		$rev->setTimestamp( '20101010000000' );
		yield 'no user' => [ $rev ];

		$rev = new MutableRevisionRecord( $title );
		$rev->setUser( $user );
		$rev->setContent( SlotRecord::MAIN, $content );
		$rev->setTimestamp( '20101010000000' );
		yield 'no comment' => [ $rev ];
	}

	/**
	 * @dataProvider provideNotReadyForInsertion
	 */
	public function testNotReadyForInsertion( $rev ) {
		$this->assertFalse( $rev->isReadyForInsertion() );
	}

	/**
	 * @covers \MediaWiki\Revision\RevisionRecord::isCurrent
	 */
	public function testIsCurrent() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertFalse( $record->isCurrent(),
			MutableRevisionRecord::class . ' cannot be stored current revision' );
	}

	/**
	 * @covers \MediaWiki\Revision\RevisionRecord::hasSameContent
	 */
	public function testHasSameContent() {
		$rev1 = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->assertTrue( $rev1->hasSameContent( $rev1 ) );

		$rev2 = new MutableRevisionRecord(
			new PageIdentityValue( 2, NS_MAIN, 'Bar', PageIdentity::LOCAL )
		);
		$rev1->setSize( 1 );
		$rev2->setSize( 2 );
		$this->assertFalse( $rev1->hasSameContent( $rev2 ) );
	}

	/**
	 * @covers \MediaWiki\Revision\RevisionRecord::audienceCan
	 */
	public function testAudienceCan() {
		$record = new MutableRevisionRecord(
			new PageIdentityValue( 1, NS_MAIN, 'Foo', PageIdentity::LOCAL )
		);
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'An Authority object must be given when checking FOR_THIS_USER audience.'
		);
		$record->audienceCan( RevisionRecord::DELETED_TEXT, RevisionRecord::FOR_THIS_USER );
	}
}
