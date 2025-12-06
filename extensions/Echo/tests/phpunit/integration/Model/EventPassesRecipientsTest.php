<?php

namespace MediaWiki\Extension\Notifications\Test\Integration;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Notification\RecipientSet;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\Notifications\Model\Event
 * @group Database
 */
class EventPassesRecipientsTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \MediaWiki\Extension\Notifications\Model\Event::create
	 */
	public function testRecipientsInjected() {
		$user1 = UserIdentityValue::newRegistered( 1, 'User One' );
		$user2 = UserIdentityValue::newRegistered( 2, 'User Two' );

		$this->setTemporaryHook( 'BeforeEchoEventInsert', function ( $event ) use ( $user1, $user2 ) {
			$eventRecipients = $event->getExtraParam( Event::RECIPIENTS_IDX );
			$this->assertCount( 2, $eventRecipients );
			$this->assertArrayContains( [ $user1->getId(), $user2->getId() ], $eventRecipients );
			return false;
		} );

		Event::create(
			[
				'type' => 'welcome',
			], new RecipientSet( [ $user1, $user2 ] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\Notifications\Model\Event::create
	 */
	public function testRecipientsAreIntactWhenNoRecipientSet() {
		$this->setTemporaryHook( 'BeforeEchoEventInsert', function ( $event ) {
			$eventRecipients = $event->getExtraParam( Event::RECIPIENTS_IDX );
			$this->assertCount( 2, $eventRecipients );
			$this->assertArrayContains( [ 10, 42 ], $eventRecipients );
			return false;
		} );
		Event::create(
			[
				'type' => 'welcome',
				'extra' => [
					Event::RECIPIENTS_IDX => [ 10, 42 ],
				],
			]
		);
	}

	/**
	 * @covers \MediaWiki\Extension\Notifications\Model\Event::create
	 */
	public function testRecipientsAreMergedWhenBothExtraAndRecipientSetIsPassed() {
		$user = UserIdentityValue::newRegistered( 1, 'TEST' );

		$this->setTemporaryHook( 'BeforeEchoEventInsert', function ( $event ) use ( $user ) {
			$eventRecipients = $event->getExtraParam( Event::RECIPIENTS_IDX );
			$this->assertCount( 2, $eventRecipients );
			$this->assertArrayContains( [ -42, $user->getId() ], $eventRecipients );
			return false;
		} );

		Event::create(
			[
				'type' => 'welcome',
				'extra' => [
					Event::RECIPIENTS_IDX => [ -42 ],
				],
			], new RecipientSet( [ $user ] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\Notifications\Model\Event::create
	 */
	public function testPassesUniqSetOfRecipients() {
		$user = UserIdentityValue::newRegistered( 2, 'TEST' );

		$this->setTemporaryHook( 'BeforeEchoEventInsert', function ( $event ) use ( $user ) {
			$eventRecipients = $event->getExtraParam( Event::RECIPIENTS_IDX );
			$this->assertCount( 1, $eventRecipients );
			$this->assertArrayContains( [ $user->getId() ], $eventRecipients );
			return false;
		} );

		Event::create(
			[
				'type' => 'welcome',
				'extra' => [
					Event::RECIPIENTS_IDX => [ $user->getId() ],
				],
			], new RecipientSet( [ $user ] )
		);
	}

}
