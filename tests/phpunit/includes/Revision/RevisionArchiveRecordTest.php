<?php

namespace MediaWiki\Tests\Revision;

use CommentStoreComment;
use InvalidArgumentException;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Revision\RevisionArchiveRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionSlots;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use stdClass;
use TextContent;
use Title;
use TitleValue;
use Wikimedia\Assert\PreconditionException;

/**
 * @covers \MediaWiki\Revision\RevisionArchiveRecord
 * @covers \MediaWiki\Revision\RevisionRecord
 */
class RevisionArchiveRecordTest extends MediaWikiIntegrationTestCase {

	public function provideConstructor() {
		$user = new UserIdentityValue( 11, 'Tester' );
		$comment = CommentStoreComment::newUnsavedComment( 'Hello World' );

		$main = SlotRecord::newUnsaved( SlotRecord::MAIN, new TextContent( 'Lorem Ipsum' ) );
		$aux = SlotRecord::newUnsaved( 'aux', new TextContent( 'Frumious Bandersnatch' ) );
		$slots = new RevisionSlots( [ $main, $aux ] );

		$protoRow = [
			'ar_id' => '5',
			'ar_rev_id' => '7',
			'ar_page_id' => '17',
			'ar_timestamp' => '20200101000000',
			'ar_deleted' => 0,
			'ar_minor_edit' => 0,
			'ar_parent_id' => '5',
			'ar_len' => $slots->computeSize(),
			'ar_sha1' => $slots->computeSha1(),
		];

		$row = $protoRow;
		yield 'all info' => [
			new PageIdentityValue( 17, NS_MAIN, 'Dummy', 'acmewiki' ),
			$user,
			$comment,
			(object)$row,
			$slots,
			'acmewiki',
			PreconditionException::class
		];

		yield 'all info, local' => [
			new PageIdentityValue( 17, NS_MAIN, 'Dummy', PageIdentity::LOCAL ),
			$user,
			$comment,
			(object)$row,
			$slots,
		];

		$title = Title::makeTitle( NS_MAIN, 'Dummy' );
		$title->resetArticleID( 17 );

		yield 'all info, local, with Title' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots,
		];

		$row = $protoRow;
		$row['ar_minor_edit'] = '1';
		$row['ar_deleted'] = strval( RevisionRecord::DELETED_USER );

		yield 'minor deleted' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots
		];

		$row = $protoRow;
		unset( $row['ar_parent'] );

		yield 'no parent' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots
		];

		$row = $protoRow;
		$row['ar_len'] = null;
		$row['ar_sha1'] = '';

		yield 'ar_len is null, ar_sha1 is ""' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots
		];

		$row = $protoRow;
		yield 'no length, no hash' => [
			Title::makeTitle( NS_MAIN, 'DummyDoesNotExist' ),
			$user,
			$comment,
			(object)$row,
			$slots
		];
	}

	/**
	 * @dataProvider provideConstructor
	 *
	 * @param Title $page
	 * @param UserIdentity $user
	 * @param CommentStoreComment $comment
	 * @param stdClass $row
	 * @param RevisionSlots $slots
	 * @param bool $wikiId
	 * @param string|null $expectedException
	 */
	public function testConstructorAndGetters(
		PageIdentity $page,
		UserIdentity $user,
		CommentStoreComment $comment,
		$row,
		RevisionSlots $slots,
		$wikiId = RevisionRecord::LOCAL,
		string $expectedException = null
	) {
		$rec = new RevisionArchiveRecord( $page, $user, $comment, $row, $slots, $wikiId );

		$this->assertTrue( $page->isSamePageAs( $rec->getPage() ), 'getPage' );
		$this->assertSame( $user, $rec->getUser( RevisionRecord::RAW ), 'getUser' );
		$this->assertSame( $comment, $rec->getComment(), 'getComment' );

		$this->assertSame( $slots->getSlotRoles(), $rec->getSlotRoles(), 'getSlotRoles' );
		$this->assertSame( $wikiId, $rec->getWikiId(), 'getWikiId' );

		$this->assertSame( (int)$row->ar_id, $rec->getArchiveId(), 'getArchiveId' );
		$this->assertSame( (int)$row->ar_rev_id, $rec->getId( $wikiId ), 'getId' );
		$this->assertSame( (int)$row->ar_page_id, $rec->getPageId( $wikiId ), 'getId' );
		$this->assertSame( $row->ar_timestamp, $rec->getTimestamp(), 'getTimestamp' );
		$this->assertSame( (int)$row->ar_deleted, $rec->getVisibility(), 'getVisibility' );
		$this->assertSame( (bool)$row->ar_minor_edit, $rec->isMinor(), 'getIsMinor' );

		if ( isset( $row->ar_parent_id ) ) {
			$this->assertSame( (int)$row->ar_parent_id, $rec->getParentId( $wikiId ), 'getParentId' );
		} else {
			$this->assertSame( 0, $rec->getParentId( $wikiId ), 'getParentId' );
		}

		if ( isset( $row->ar_len ) ) {
			$this->assertSame( (int)$row->ar_len, $rec->getSize(), 'getSize' );
		} else {
			$this->assertSame( $slots->computeSize(), $rec->getSize(), 'getSize' );
		}

		if ( !empty( $row->ar_sha1 ) ) {
			$this->assertSame( $row->ar_sha1, $rec->getSha1(), 'getSha1' );
		} else {
			$this->assertSame( $slots->computeSha1(), $rec->getSha1(), 'getSha1' );
		}

		if ( $expectedException ) {
			$this->expectException( $expectedException );
			$rec->getPageAsLinkTarget();
		} else {
			$this->assertTrue(
				TitleValue::newFromPage( $page )->isSameLinkAs( $rec->getPageAsLinkTarget() ),
				'getPageAsLinkTarget'
			);
		}
	}

	public function provideConstructorFailure() {
		$title = Title::makeTitle( NS_MAIN, 'Dummy' );
		$title->resetArticleID( 17 );

		$user = new UserIdentityValue( 11, 'Tester' );

		$comment = CommentStoreComment::newUnsavedComment( 'Hello World' );

		$main = SlotRecord::newUnsaved( SlotRecord::MAIN, new TextContent( 'Lorem Ipsum' ) );
		$aux = SlotRecord::newUnsaved( 'aux', new TextContent( 'Frumious Bandersnatch' ) );
		$slots = new RevisionSlots( [ $main, $aux ] );

		$protoRow = [
			'ar_id' => '5',
			'ar_rev_id' => '7',
			'ar_page_id' => strval( $title->getArticleID() ),
			'ar_timestamp' => '20200101000000',
			'ar_deleted' => 0,
			'ar_minor_edit' => 0,
			'ar_parent_id' => '5',
			'ar_len' => $slots->computeSize(),
			'ar_sha1' => $slots->computeSha1(),
		];

		$row = $protoRow;

		yield 'mismatching wiki ID' => [
			new PageIdentityValue(
				$title->getArticleID(),
				$title->getNamespace(),
				$title->getDBkey(),
				PageIdentity::LOCAL
			),
			$user,
			$comment,
			(object)$row,
			$slots,
			'acmewiki',
			PreconditionException::class
		];

		$row = $protoRow;
		$row['ar_timestamp'] = 'kittens';

		yield 'bad timestamp' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots
		];

		$row = $protoRow;

		yield 'bad wiki' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots,
			12345
		];

		// NOTE: $title->getArticleID does *not* have to match ar_page_id in all cases!
	}

	/**
	 * @dataProvider provideConstructorFailure
	 *
	 * @param PageIdentity $page
	 * @param UserIdentity $user
	 * @param CommentStoreComment $comment
	 * @param stdClass $row
	 * @param RevisionSlots $slots
	 * @param bool $wikiId
	 * @param string|null $expectedException
	 */
	public function testConstructorFailure(
		PageIdentity $page,
		UserIdentity $user,
		CommentStoreComment $comment,
		$row,
		RevisionSlots $slots,
		$wikiId = false,
		string $expectedException = InvalidArgumentException::class
	) {
		$this->expectException( $expectedException );
		new RevisionArchiveRecord( $page, $user, $comment, $row, $slots, $wikiId );
	}
}
