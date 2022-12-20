<?php

use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Rdbms\IDatabase;

class DatabaseLogEntryTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		// These services cache their joins
		$this->getServiceContainer()->resetServiceForTesting( 'CommentStore' );
		$this->getServiceContainer()->resetServiceForTesting( 'ActorMigration' );
	}

	protected function tearDown(): void {
		$this->getServiceContainer()->resetServiceForTesting( 'CommentStore' );
		$this->getServiceContainer()->resetServiceForTesting( 'ActorMigration' );
		parent::tearDown();
	}

	/**
	 * @covers DatabaseLogEntry::newFromId
	 * @covers DatabaseLogEntry::getSelectQueryData
	 *
	 * @dataProvider provideNewFromId
	 *
	 * @param int $id
	 * @param array $selectFields
	 * @param string[]|null $row
	 * @param string[]|null $expectedFields
	 */
	public function testNewFromId( $id,
		array $selectFields,
		array $row = null,
		array $expectedFields = null
	) {
		$row = $row ? (object)$row : null;
		$db = $this->createMock( IDatabase::class );
		$db->expects( self::once() )
			->method( 'selectRow' )
			->with( $selectFields['tables'],
				$selectFields['fields'],
				$selectFields['conds'],
				'DatabaseLogEntry::newFromId',
				$selectFields['options'],
				$selectFields['join_conds']
			)
			->will( self::returnValue( $row ) );

		/** @var IDatabase $db */
		$logEntry = DatabaseLogEntry::newFromId( $id, $db );

		if ( !$expectedFields ) {
			self::assertNull( $logEntry, "Expected no log entry returned for id=$id" );
		} else {
			self::assertEquals( $id, $logEntry->getId() );
			self::assertEquals( $expectedFields['type'], $logEntry->getType() );
			self::assertEquals( $expectedFields['comment'], $logEntry->getComment() );
		}
	}

	public function provideNewFromId() {
		$newTables = [
			'tables' => [
				'logging',
				'user',
				'comment_log_comment' => 'comment',
				'logging_actor' => 'actor'
			],
			'fields' => [
				'log_id',
				'log_type',
				'log_action',
				'log_timestamp',
				'log_namespace',
				'log_title',
				'log_params',
				'log_deleted',
				'user_id',
				'user_name',
				'log_comment_text' => 'comment_log_comment.comment_text',
				'log_comment_data' => 'comment_log_comment.comment_data',
				'log_comment_cid' => 'comment_log_comment.comment_id',
				'log_user' => 'logging_actor.actor_user',
				'log_user_text' => 'logging_actor.actor_name',
				'log_actor',
			],
			'options' => [],
			'join_conds' => [
				'user' => [ 'LEFT JOIN', 'user_id=logging_actor.actor_user' ],
				'comment_log_comment' => [ 'JOIN', 'comment_log_comment.comment_id = log_comment_id' ],
				'logging_actor' => [ 'JOIN', 'actor_id=log_actor' ],
			],
		];
		return [
			[
				0,
				$newTables + [ 'conds' => [ 'log_id' => 0 ] ],
				null,
				null
			],
			[
				123,
				$newTables + [ 'conds' => [ 'log_id' => 123 ] ],
				[
					'log_id' => 123,
					'log_type' => 'foobarize',
					'log_comment_text' => 'test!',
					'log_comment_data' => null,
				],
				[ 'type' => 'foobarize', 'comment' => 'test!' ]
			],
			[
				567,
				$newTables + [ 'conds' => [ 'log_id' => 567 ] ],
				[
					'log_id' => 567,
					'log_type' => 'foobarize',
					'log_comment_text' => 'test!',
					'log_comment_data' => null,
				],
				[ 'type' => 'foobarize', 'comment' => 'test!' ]
			],
		];
	}

	public function provideGetPerformerIdentity() {
		yield 'registered actor' => [
			'actor_row_fields' => [
				'user_id' => 42,
				'log_user_text' => 'Testing',
				'log_actor' => 24,
			],
			UserIdentityValue::newRegistered( 42, 'Testing' ),
		];
		yield 'anon actor' => [
			'actor_row_fields' => [
				'log_user_text' => '127.0.0.1',
				'log_actor' => 24,
			],
			UserIdentityValue::newAnonymous( '127.0.0.1' ),
		];
		yield 'unknown actor' => [
			'actor_row_fields' => [],
			new UserIdentityValue( 0, ActorStore::UNKNOWN_USER_NAME ),
		];
	}

	/**
	 * @dataProvider provideGetPerformerIdentity
	 * @covers DatabaseLogEntry::getPerformerIdentity
	 */
	public function testGetPerformer( array $actorRowFields, UserIdentity $expected ) {
		$logEntry = DatabaseLogEntry::newFromRow( [
			'log_id' => 1,
		] + $actorRowFields );
		$performer = $logEntry->getPerformerIdentity();
		$this->assertTrue( $expected->equals( $performer ) );
	}
}
