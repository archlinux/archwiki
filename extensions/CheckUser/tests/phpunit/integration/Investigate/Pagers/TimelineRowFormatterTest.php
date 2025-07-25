<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatter;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\Logging\LogFormatter;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatter
 * @group CheckUser
 * @group Database
 */
class TimelineRowFormatterTest extends MediaWikiIntegrationTestCase {

	private function getObjectUnderTest( ?User $user = null ): TimelineRowFormatter {
		// Generate a testing user if no user was defined
		$user ??= $this->getTestUser()->getUser();
		return $this->getServiceContainer()->get( 'CheckUserTimelineRowFormatterFactory' )
			->createRowFormatter(
				$user,
				// Use qqx language to make testing easier.
				$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' )
			);
	}

	/** @dataProvider provideGetFormattedRowItems */
	public function testGetFormattedRowItems( $row, $expectedArraySubmap ) {
		RequestContext::getMain()->setUser( $this->getTestUser( [ 'checkuser' ] )->getUser() );
		// Tests a subset of the items in the array returned by ::getFormattedRowItems
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge( $this->getDefaultsForTimelineRow(), $row );
		$this->assertArraySubmapSame(
			$expectedArraySubmap,
			$objectUnderTest->getFormattedRowItems( (object)$row ),
			'Array returned by ::getFormattedRowItems was not as expected.'
		);
	}

	public static function provideGetFormattedRowItems() {
		return [
			'Edit performed by IPv4' => [
				[ 'ip' => '127.0.0.1', 'agent' => 'Test' ],
				[
					'links' => [
						// No flags should be displayed if the action didn't create a page and wasn't marked as minor.
						'minorFlag' => '', 'newPageFlag' => '',
						// No log links if action is an edit. diffLinks, historyLinks etc. will be tested separately.
						'logsLink' => '', 'logLink' => '',
					],
					'info' => [
						'userAgent' => 'Test',
						'ipInfo' => '127.0.0.1',
						// No action text if the action is an edit.
						'actionText' => '',
					],
				],
			],
			'Log performed by IPv6' => [
				[
					'ip' => '2001:DB8::1', 'log_action' => 'migrated-cu_changes-log-event',
					'log_type' => 'checkuser-private-event',
					'log_params' => LogEntryBase::makeParamBlob( [ '4::actiontext' => 'test action text' ] ),
					'log_deleted' => 0,
					'agent' => 'Test', 'type' => RC_LOG,
				],
				[
					// All edit-specific links / flags should be empty for a log action.
					'links' => [ 'minorFlag' => '', 'newPageFlag' => '', 'historyLink' => '', 'diffLink' => '', ],
					'info' => [
						'userAgent' => 'Test',
						// The IP address should be in lower-case with shortened form.
						'ipInfo' => '2001:db8::1',
						'actionText' => 'test action text',
						// Title will not be defined for log events, because it is already used for the logs link.
						'title' => '',
					],
				],
			],
			'Edit with invalid title' => [
				// title should be a string, but using 0 is a way to test invalid title.
				[ 'title' => 0, 'namespace' => 0 ],
				[ 'links' => [ 'historyLink' => '', 'diffLink' => '' ], 'info' => [ 'title' => '' ] ],
			],
			'Log with invalid title' => [
				[ 'title' => 0, 'namespace' => 0, 'type' => RC_LOG ], [ 'links' => [ 'logsLink' => '' ] ],
			],
			'Log with log ID of 0' => [ [ 'log_id' => 0, 'type' => RC_LOG ], [ 'links' => [ 'logLink' => '' ] ] ],
			'Edit marked as a minor edit and created a page' => [
				[ 'minor' => 1, 'type' => RC_NEW ],
				[ 'links' => [
					'minorFlag' => '<span class="minor">(minoreditletter)</span>',
					'newPageFlag' => '<span class="newpage">(newpageletter)</span>',
				] ],
			],
		];
	}

	public function testGetTime() {
		$testUser = $this->getTestUser()->getUser();
		$objectUnderTest = $this->getObjectUnderTest();
		$row = $this->getDefaultsForTimelineRow();
		$expectedTime = $this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' )
			->userTime( '20210405060708', $testUser );
		$this->assertArraySubmapSame(
			[ 'info' => [ 'time' => $expectedTime ] ],
			$objectUnderTest->getFormattedRowItems( (object)$row ),
			'Array returned by ::getFormattedRowItems was not as expected.'
		);
	}

	/** @dataProvider provideCucTypeValues */
	public function testWhenTitleDefined( $rowType ) {
		// Get a test page
		$testPage = $this->getExistingTestPage()->getTitle();
		// Get the object under test and the row.
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[
				'namespace' => $testPage->getNamespace(), 'title' => $testPage->getText(),
				'page_id' => $testPage->getArticleID(), 'this_oldid' => $testPage->getLatestRevID(),
				'last_oldid' => 0, 'type' => $rowType,
			]
		);
		// Assert that the userLinks contain the rev-deleted-user message and not the username,
		// as the user is blocked with 'hideuser' and the current authority cannot see hidden users.
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		// Assertions that are specific to RC_EDIT types.
		if ( $rowType === RC_EDIT ) {
			$this->assertStringContainsString(
				$testPage->getLatestRevID(),
				$actualTimelineFormattedRowItems['links']['diffLink'],
				'The diffLink is not as expected in the links array.'
			);
			$this->assertStringContainsString(
				$testPage->getArticleID(),
				$actualTimelineFormattedRowItems['links']['historyLink'],
				'The historyLink is not as expected in the links array.'
			);
		}
		if ( $rowType !== RC_LOG ) {
			// Assertions that apply to all types except RC_LOG.
			$this->assertStringContainsString(
				'ext-checkuser-investigate-timeline-row-title',
				$actualTimelineFormattedRowItems['info']['title'],
				'The title is not as expected in the info array.'
			);
			$this->assertStringContainsString(
				$testPage->getText(),
				$actualTimelineFormattedRowItems['info']['title'],
				'The title is not as expected in the info array.'
			);
		} else {
			// Assertions that apply to RC_LOG types.
			$this->assertStringContainsString(
				wfUrlencode( $testPage->getText() ),
				$actualTimelineFormattedRowItems['links']['logsLink'],
				'The logsLink is not as expected in the links array.'
			);
		}
	}

	/** @dataProvider provideCucTypeValues */
	public function testTitleAsHiddenUser( $rowType ) {
		// Create a test user which is blocked with 'hideuser' enabled.
		$hiddenUser = $this->getTestUser()->getUser();
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$hiddenUser,
				$this->getTestUser( [ 'suppress', 'sysop' ] )->getAuthority(),
				'infinity', 'block to hide the test user', [ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		// ::testGetFormattedRowItems uses a test user which cannot see users which are hidden.
		$this->testGetFormattedRowItems(
			[ 'title' => $hiddenUser->getName(), 'namespace' => NS_USER, 'type' => $rowType ],
			[ 'links' => [ 'historyLink' => '', 'diffLink' => '', 'logsLink' => '' ], 'info' => [ 'title' => '' ] ]
		);
	}

	public static function provideCucTypeValues() {
		return [
			'Edit' => [ RC_EDIT ],
			'Page creation' => [ RC_NEW ],
			'Log' => [ RC_LOG ],
		];
	}

	public function testHiddenUserAsPerformer() {
		// Create a test user which is blocked with 'hideuser' enabled.
		$hiddenUser = $this->getTestUser()->getUser();
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$hiddenUser,
				$this->getTestUser( [ 'suppress', 'sysop' ] )->getAuthority(),
				'infinity', 'block to hide the test user', [ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		// Get the object under test and the row.
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[
				'user_text' => $hiddenUser->getName(),
				'user' => $hiddenUser->getId(),
				'actor' => $hiddenUser->getActorId(),
				'type' => RC_EDIT
			]
		);
		// Assert that the userLinks contain the rev-deleted-user message and not the username,
		// as the user is blocked with 'hideuser' and the current authority cannot see hidden users.
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		$this->assertStringContainsString(
			'rev-deleted-user',
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should not display the username and instead show the rev-deleted-user message.'
		);
		$this->assertStringNotContainsString(
			$hiddenUser->getName(),
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should not display the username if it is hidden from the current user.'
		);
	}

	public function testHiddenPerformerAndCommentForEdit() {
		$testUser = $this->getTestUser()->getUser();
		// Make an edit using this $testUser and then hide the performer on the edit by deleting the page.
		$testPage = $this->getNonexistingTestPage();
		$pageEditStatus = $this->editPage( $testPage, 'Testing1233', 'Test1233', NS_MAIN, $testUser );
		$this->assertTrue( $pageEditStatus->wasRevisionCreated() );
		$revId = $pageEditStatus->getNewRevision()->getId();
		// Set the rev_deleted field to hide the user and comment for the revision that was just created.
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'revision' )
			->set( [ 'rev_deleted' => RevisionRecord::DELETED_USER | RevisionRecord::DELETED_COMMENT ] )
			->where( [ 'rev_id' => $revId ] )
			->execute();
		// Get the object under test
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[
				'user_text' => $testUser->getName(),
				'user' => $testUser->getId(),
				'actor' => $testUser->getActorId(),
				'type' => RC_EDIT,
				'this_oldid' => $revId
			]
		);
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		$this->assertStringContainsString(
			'rev-deleted-user',
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should not display the username and instead show the rev-deleted-user message.'
		);
		$this->assertStringNotContainsString(
			$testUser->getName(),
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should not display the username if the performer is hidden for the edit.'
		);
		$this->assertStringContainsString(
			'rev-deleted-comment',
			$actualTimelineFormattedRowItems['info']['comment'],
			'The comment should be hidden and instead be the rev-deleted-message if the comment.'
		);
	}

	public function testGetUserLinksForUser() {
		$testUser = $this->getTestUser()->getUser();
		// Get the object under test
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[ 'user_text' => $testUser->getName(), 'user' => $testUser->getId(), 'type' => RC_EDIT ]
		);
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		$this->assertStringContainsString(
			$testUser->getName(),
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should display the username in the userLinks.'
		);
	}

	public function testGetUserLinksForIP() {
		// Get the object under test
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[ 'user_text' => '127.0.0.1', 'type' => RC_EDIT ]
		);
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		$this->assertStringContainsString(
			'127.0.0.1',
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should display the username in the userLinks.'
		);
	}

	public function testGetUserLinksForIPOnNullActor() {
		// Get the object under test
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[ 'user_text' => null, 'user' => null, 'actor' => null, 'ip' => '1.2.3.4', 'type' => RC_EDIT ]
		);
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		$this->assertStringContainsString(
			'1.2.3.4', $actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should display the IP as the performer in the userLinks if the actor ID was null.'
		);
	}

	public function testGetLogLink() {
		// Get the object under test
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[ 'log_id' => 123, 'type' => RC_LOG ]
		);
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		$actualLogLink = $actualTimelineFormattedRowItems['links']['logLink'];
		$this->assertStringContainsString( '123', $actualLogLink, 'The log ID link should include the log ID' );
		$this->assertStringContainsString(
			'(checkuser-log-link-text', $actualLogLink, 'The link text was not as expected'
		);
	}

	public function testLogEntryHidden() {
		$deleteLogEntry = new ManualLogEntry( 'delete', 'delete' );
		$deleteLogEntry->setPerformer( UserIdentityValue::newAnonymous( '127.0.0.1' ) );
		$deleteLogEntry->setTarget( Title::newFromText( 'Testing page' ) );
		// Use the maximum level of log_deleted so that we can test the hiding code all at once.
		$deleteLogEntry->setDeleted(
			LogPage::DELETED_USER | LogPage::DELETED_COMMENT | LogPage::DELETED_ACTION | LogPage::DELETED_RESTRICTED
		);
		$logFormatter = $this->getServiceContainer()->getLogFormatterFactory()->newFromEntry( $deleteLogEntry );
		$logFormatter->setAudience( LogFormatter::FOR_THIS_USER );
		// Get the object under test
		$objectUnderTest = $this->getObjectUnderTest();
		$row = array_merge(
			$this->getDefaultsForTimelineRow(),
			[
				'log_deleted' => $deleteLogEntry->getDeleted(),
				'log_type' => $deleteLogEntry->getType(),
				'log_action' => $deleteLogEntry->getSubtype(),
				'type' => RC_LOG,
				'user_text' => $deleteLogEntry->getPerformerIdentity()->getName(),
				'user' => $deleteLogEntry->getPerformerIdentity()->getId(),
				'log_id' => 123,
			]
		);
		$actualTimelineFormattedRowItems = $objectUnderTest->getFormattedRowItems( (object)$row );
		// Test that the comment is hidden.
		$this->assertStringContainsString(
			'(rev-deleted-comment',
			$actualTimelineFormattedRowItems['info']['comment'],
			'The comment should be hidden, as the the authority cannot see it.'
		);
		// Test that the action text matches
		$this->assertSame(
			$logFormatter->getActionText(),
			$actualTimelineFormattedRowItems['info']['actionText'],
			'The action text was not as expected.'
		);
		// Test that no 'logs' link is added (as it the URL includes the title which is hidden)
		$this->assertSame(
			'',
			$actualTimelineFormattedRowItems['links']['logsLink'],
			'The logs link should not be displayed if the target page for the log is hidden.'
		);
		// Test that the user is hidden.
		$this->assertStringContainsString(
			'(rev-deleted-user',
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The userLinks should not display the performer of the log if it is hidden.'
		);
		$this->assertStringContainsString(
			'history-deleted mw-history-suppressed',
			$actualTimelineFormattedRowItems['info']['userLinks'],
			'The expected CSS class for the userLinks was not added.'
		);
		// Test that the 'log' link is still added, as the log ID contains no hidden information and can therefore
		// still be displayed
		$actualLogLink = $actualTimelineFormattedRowItems['links']['logLink'];
		$this->assertStringContainsString( '123', $actualLogLink );
		$this->assertStringContainsString(
			'(checkuser-log-link-text', $actualLogLink, 'The link text was not as expected'
		);
	}

	private function getDefaultsForTimelineRow() {
		return [
			'namespace' => 0, 'title' => 'Test', 'actiontext' => '', 'timestamp' => '20210405060708',
			'minor' => 0, 'page_id' => 0, 'type' => RC_EDIT, 'this_oldid' => 0, 'last_oldid' => 0,
			'ip' => '127.0.0.1', 'xff' => '', 'agent' => '', 'id' => 0, 'user' => 0,
			'user_text' => '', 'comment_text' => '', 'comment_data' => null, 'actor' => null, 'log_type' => null,
			'log_action' => null, 'log_params' => null, 'log_deleted' => null, 'log_id' => null,
		];
	}
}
