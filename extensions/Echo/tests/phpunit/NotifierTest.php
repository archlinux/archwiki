<?php
namespace MediaWiki\Extension\Notifications\Test;

use MediaWiki\Block\Block;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\Notifications\Notifier;
use MediaWiki\Mail\IEmailer;
use MediaWiki\Mail\MailAddress;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Notifications\Notifier
 * @group Database
 */
class NotifierTest extends MediaWikiIntegrationTestCase {
	/**
	 * @dataProvider provideShouldNotSendEmailConfirmationReminderNotification
	 *
	 * @param bool $enableEmail The value of $wgEnableEmail
	 * @param bool $hasDisabledEchoEmails Whether the user has disabled all Echo emails via preference
	 * @param bool $hasDisabledEmailConfirmationReminder Whether the user has disabled Echo email
	 * confirmation reminders via preference
	 * @param bool $isEmailConfirmed Whether the user has a confirmed email address
	 * @param bool $isBlocked Whether the user is blocked
	 * @param bool $blockDisablesLogin The value of $wgBlockDisablesLogin
	 * @param string $emailAddress The email address of the user
	 */
	public function testShouldNotSendEmailConfirmationReminderNotification(
		bool $enableEmail,
		bool $hasDisabledEchoEmails,
		bool $hasDisabledEmailConfirmationReminder,
		bool $isEmailConfirmed,
		bool $isBlocked,
		bool $blockDisablesLogin,
		string $emailAddress
	): void {
		$this->overrideConfigValues( [
			'EnableEmail' => $enableEmail,
			'EchoEnableEmailBatch' => false,
			'BlockDisablesLogin' => $blockDisablesLogin,
		] );

		$event = Event::create( [ 'type' => 'verify-email-reminder' ] );

		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )
			->willReturn( true );
		$user->method( 'isEmailConfirmed' )
			->willReturn( $isEmailConfirmed );
		$user->method( 'getName' )
			->willReturn( 'TestUser' );
		$user->method( 'getEmail' )
			->willReturn( $emailAddress );
		$user->method( 'getBlock' )
			->willReturn( $isBlocked ? $this->createMock( Block::class ) : null );

		$userOptionsLookup = new StaticUserOptionsLookup( [
			'TestUser' => [
				'echo-email-frequency' => $hasDisabledEchoEmails ? -1 : 1,
				'echo-subscriptions-email-verify-email-reminder' => $hasDisabledEmailConfirmationReminder ? 0 : 1,
				'language' => 'qqx',
			],
		] );

		$emailer = $this->createNoOpMock( IEmailer::class );

		$this->setService( 'UserOptionsLookup', $userOptionsLookup );
		$this->setService( 'Emailer', $emailer );

		$result = Notifier::notifyWithEmail( $user, $event );

		// If the user has specifically opted out of Echo email confirmation reminders via preference,
		// Notifier::notifyWithEmail is expected to return true but not send an email.
		if ( $hasDisabledEmailConfirmationReminder ) {
			$this->assertTrue(
				$result,
				'Email confirmation reminder should be processed but not sent if opted out specifically'
			);
		} else {
			$this->assertFalse( $result, 'Email confirmation reminder should not be sent' );
		}
	}

	public static function provideShouldNotSendEmailConfirmationReminderNotification(): iterable {
		yield 'emails disabled' => [
			'enableEmail' => false,
			'hasDisabledEchoEmails' => false,
			'hasDisabledEmailConfirmationReminder' => false,
			'isEmailConfirmed' => false,
			'isBlocked' => false,
			'blockDisablesLogin' => false,
			'emailAddress' => 'valid@example.com',
		];

		yield 'user has disabled Echo emails' => [
			'enableEmail' => true,
			'hasDisabledEchoEmails' => true,
			'hasDisabledEmailConfirmationReminder' => false,
			'isEmailConfirmed' => false,
			'isBlocked' => false,
			'blockDisablesLogin' => false,
			'emailAddress' => 'valid@example.com',
		];

		yield 'user has disabled Echo email confirmation reminders' => [
			'enableEmail' => true,
			'hasDisabledEchoEmails' => false,
			'hasDisabledEmailConfirmationReminder' => true,
			'isEmailConfirmed' => false,
			'isBlocked' => false,
			'blockDisablesLogin' => false,
			'emailAddress' => 'valid@example.com',
		];

		yield 'user is blocked and wgBlockDisablesLogin is set' => [
			'enableEmail' => true,
			'hasDisabledEchoEmails' => false,
			'hasDisabledEmailConfirmationReminder' => false,
			'isEmailConfirmed' => false,
			'isBlocked' => true,
			'blockDisablesLogin' => true,
			'emailAddress' => 'valid@example.com',
		];

		yield 'user has no email address' => [
			'enableEmail' => true,
			'hasDisabledEchoEmails' => false,
			'hasDisabledEmailConfirmationReminder' => false,
			'isEmailConfirmed' => false,
			'isBlocked' => false,
			'blockDisablesLogin' => false,
			'emailAddress' => '',
		];

		yield 'user has invalid email address' => [
			'enableEmail' => true,
			'hasDisabledEchoEmails' => false,
			'hasDisabledEmailConfirmationReminder' => false,
			'isEmailConfirmed' => false,
			'isBlocked' => false,
			'blockDisablesLogin' => false,
			'emailAddress' => 'invalid-email',
		];

		yield 'user with already confirmed email' => [
			'enableEmail' => true,
			'hasDisabledEchoEmails' => false,
			'hasDisabledEmailConfirmationReminder' => false,
			'isEmailConfirmed' => true,
			'isBlocked' => false,
			'blockDisablesLogin' => false,
			'emailAddress' => 'valid@example.com',
		];
	}

	/**
	 * @dataProvider provideShouldSendEmailConfirmationReminderNotification
	 *
	 * @param bool $isBlocked Whether the user is blocked
	 * @param bool $blockDisablesLogin The value of $wgBlockDisablesLogin
	 */
	public function testShouldSendEmailConfirmationReminderNotification(
		bool $isBlocked,
		bool $blockDisablesLogin
	): void {
		$this->overrideConfigValues( [
			'EnableEmail' => true,
			'EchoEnableEmailBatch' => false,
			'BlockDisablesLogin' => $blockDisablesLogin,
		] );

		$event = Event::create( [ 'type' => 'verify-email-reminder' ] );

		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )
			->willReturn( true );
		$user->method( 'getName' )
			->willReturn( 'TestUser' );
		$user->method( 'getEmail' )
			->willReturn( 'valid@example.com' );
		$user->method( 'isEmailConfirmed' )
			->willReturn( false );
		$user->method( 'getBlock' )
			->willReturn( $isBlocked ? $this->createMock( Block::class ) : null );

		$userOptionsLookup = new StaticUserOptionsLookup( [
			'TestUser' => [
				// User has not disabled Echo emails
				'echo-email-frequency' => 1,
				'echo-subscriptions-email-verify-email-reminder' => 1,
				'language' => 'qqx',
			],
		] );

		$emailer = $this->createMock( IEmailer::class );
		$emailer->expects( $this->once() )
			->method( 'send' )
			->with(
				MailAddress::newFromUser( $user ),
				$this->isInstanceOf( MailAddress::class ),
				'(notification-subject-email-verify-email-reminder: TestUser)',
				$this->logicalAnd(
					$this->stringContains( '(notification-header-verify-email-reminder: TestUser)' ),
					$this->stringContains( '(notification-verify-email-reminder-link-label)' ),
					$this->stringContains( '(notification-link-text-verify-email-reminder: TestUser)' ),
				)
			);

		$this->setService( 'UserOptionsLookup', $userOptionsLookup );
		$this->setService( 'Emailer', $emailer );

		$result = Notifier::notifyWithEmail( $user, $event );

		$this->assertTrue( $result, 'Email confirmation reminder should be sent' );
	}

	public static function provideShouldSendEmailConfirmationReminderNotification(): iterable {
		yield 'user is blocked and wgBlockDisablesLogin is not set' => [
			'isBlocked' => true,
			'blockDisablesLogin' => false,
			'emailAddress' => 'valid@example.com',
		];

		yield 'user is not blocked and wgBlockDisablesLogin is set' => [
			'isBlocked' => false,
			'blockDisablesLogin' => true,
			'emailAddress' => 'valid@example.com',
		];

		yield 'user is not blocked and wgBlockDisablesLogin is not set' => [
			'isBlocked' => false,
			'blockDisablesLogin' => false,
		];
	}
}
