<?php

namespace MediaWiki\CheckUser\Test\Integration\Logging;

use LogFormatterTestCase;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;

/**
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Logging\CheckUserPrivateEventLogFormatter
 */
class CheckUserPrivateEventLogFormatterTest extends LogFormatterTestCase {

	use MockAuthorityTrait;

	public static function provideLogDatabaseRows(): array {
		return [
			'Successful login' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'login-success',
					'user_text' => 'Sysop',
					'params' => [
						'4::target' => 'UTSysop',
					],
				],
				'extra' => [
					'text' => "Successfully logged in to mediawiki as UTSysop",
					'api' => [
						'target' => 'UTSysop',
					],
				],
			],
			'Failed login' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'login-failure',
					'user_text' => 'Sysop',
					'params' => [
						'4::target' => 'UTSysop',
					],
				],
				'extra' => [
					'text' => "Failed to log in to mediawiki as UTSysop",
					'api' => [
						'target' => 'UTSysop',
					],
				],
			],
			'Failed login with correct password' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'login-failure-with-good-password',
					'user_text' => 'Sysop',
					'params' => [
						'4::target' => 'UTSysop',
					],
				],
				'extra' => [
					'text' => "Failed to log in to mediawiki as UTSysop but had the correct password",
					'api' => [
						'target' => 'UTSysop',
					],
				],
			],
			'User logout' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'user-logout',
					'user_text' => 'Sysop',
					'params' => [],
				],
				'extra' => [
					'text' => 'Successfully logged out using the API or Special:UserLogout',
					'api' => [],
				],
			],
			'Password reset email sent' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'password-reset-email-sent',
					'user_text' => 'Sysop',
					'params' => [
						'4::receiver' => 'UTSysop'
					],
				],
				'extra' => [
					'text' => 'sent a password reset email for user UTSysop',
					'api' => [
						'receiver' => 'UTSysop',
					],
				],
			],
			'Email sent' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'email-sent',
					'user_text' => 'Sysop',
					'params' => [
						'4::hash' => '1234567890abcdef'
					],
				],
				'extra' => [
					'text' => 'sent an email to user "1234567890abcdef"',
					'api' => [
						'hash' => '1234567890abcdef',
					],
				],
			],
			'User autocreated' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'autocreate-account',
					'user_text' => 'Sysop',
					'params' => [],
				],
				'extra' => [
					'text' => 'was automatically created',
					'api' => [],
				],
			],
			'User created' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'create-account',
					'user_text' => 'Sysop',
					'params' => [],
				],
				'extra' => [
					'text' => 'was created',
					'api' => [],
				],
			],
			'Migrated log event from cu_changes with plaintext actiontext' => [
				'row' => [
					'type' => 'checkuser-private-event',
					'action' => 'migrated-cu_changes-log-event',
					'user_text' => 'Sysop',
					'params' => [
						'4::actiontext' => 'Test plaintext action text [[test]]'
					],
				],
				'extra' => [
					// The testcase removes the HTML from the actual actiontext
					// as the message is parsed.
					'text' => 'Test plaintext action text test',
					'api' => [
						// Link is still present for the API, as API responses don't parse wikitext.
						'actiontext' => 'Test plaintext action text [[test]]'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider provideLogDatabaseRows
	 */
	public function testLogDatabaseRows( $row, $extra ) {
		// Override wgSitename for the test to 'mediawiki' as the data provider
		// uses this value for the value of {{SITENAME}}.
		$this->overrideConfigValue( MainConfigNames::Sitename, 'mediawiki' );
		$this->doTestLogFormatter( $row, $extra, [ 'checkuser' ] );
	}

	public static function provideLogDatabaseRowsForHiddenUser() {
		return [
			'User does not have suppress group' => [ false ],
			'User has suppress group' => [ true ]
		];
	}

	/**
	 * @dataProvider provideLogDatabaseRowsForHiddenUser
	 * @param bool $logViewerHasSuppress
	 */
	public function testLogDatabaseRowsForHiddenUser( $logViewerHasSuppress ) {
		$targetUser = $this->getMutableTestUser()->getUser();
		$blockingUser = $this->getMutableTestUser( [ 'sysop', 'suppress' ] )->getUser();
		$logViewGroups = [ 'checkuser' ];
		if ( $logViewerHasSuppress ) {
			$logViewGroups[] = 'suppress';
		}
		$logViewUser = $this->getMutableTestUser( $logViewGroups )->getUser();
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$targetUser,
				$blockingUser,
				'infinity',
				'block to hide the test user',
				[ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		$wikiName = $this->getServiceContainer()->getMainConfig()->get( MainConfigNames::Sitename );

		if ( $logViewerHasSuppress ) {
			$expectedName = $targetUser->getName();
		} else {
			$expectedName = RequestContext::getMain()->msg( 'rev-deleted-user' )->text();
		}

		// Don't use doTestLogFormatter() since it overrides every service that
		// accesses the database and prevents correct loading of the block.
		$row = $this->expandDatabaseRow(
			[
				'type' => 'checkuser-private-event',
				'action' => 'login-success',
				'user_text' => $targetUser->getName(),
				'params' => [
					'4::target' => $targetUser->getName(),
				],
			],
			false
		);
		$formatter = $this->getServiceContainer()->getLogFormatterFactory()->newFromRow( $row );
		$formatter->context->setAuthority( $logViewUser );
		$this->assertEquals(
			"Successfully logged in to $wikiName as $expectedName",
			strip_tags( $formatter->getActionText() ),
			'Action text is equal to expected text'
		);

		$api = $formatter->formatParametersForApi();
		unset( $api['_element'] );
		unset( $api['_type'] );
		$this->assertSame(
			[
				'target' => $expectedName,
			],
			$api,
			'Api log params is equal to expected array'
		);
	}

}
