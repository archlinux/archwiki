<?php

use MediaWiki\EditPage\SpamChecker;
use MediaWiki\Permissions\PermissionManager;

/**
 * Integration tests for the various edit constraints, ensuring
 * that they result in failures as expected
 *
 * @covers EditPage::internalAttemptSave
 *
 * @group Editing
 * @group Database
 * @group medium
 */
class EditPageConstraintsTest extends MediaWikiLangTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setContentLang( $this->getServiceContainer()->getContentLanguage() );

		$this->setMwGlobals( [
			'wgExtraNamespaces' => [
				12312 => 'Dummy',
				12313 => 'Dummy_talk',
			],
			'wgNamespaceContentModels' => [ 12312 => 'testing' ],
		] );
		$this->mergeMwGlobalArrayValue(
			'wgContentHandlers',
			[ 'testing' => 'DummyContentHandlerForTesting' ]
		);
	}

	/**
	 * Based on method in EditPageTest
	 * Performs an edit and checks the result matches the expected failure code
	 *
	 * @param string|Title $title The title of the page to edit
	 * @param string|null $baseText Some text to create the page with before attempting the edit.
	 * @param User|null $user The user to perform the edit as.
	 * @param array $edit An array of request parameters used to define the edit to perform.
	 *              Some well known fields are:
	 *              * wpTextbox1: the text to submit
	 *              * wpSummary: the edit summary
	 *              * wpEditToken: the edit token (will be inserted if not provided)
	 *              * wpEdittime: timestamp of the edit's base revision (will be inserted
	 *                if not provided)
	 *              * editRevId: revision ID of the edit's base revision (optional)
	 *              * wpStarttime: timestamp when the edit started (will be inserted if not provided)
	 *              * wpSectionTitle: the section to edit
	 *              * wpMinorEdit: mark as minor edit
	 *              * wpWatchthis: whether to watch the page
	 * @param int $expectedCode The expected result code (EditPage::AS_XXX constants).
	 * @param string $message Message to show along with any error message.
	 *
	 * @return WikiPage The page that was just edited, useful for getting the edit's rev_id, etc.
	 */
	protected function assertEdit(
		$title,
		$baseText,
		?User $user,
		array $edit,
		$expectedCode,
		$message
	) {
		if ( is_string( $title ) ) {
			$ns = $this->getDefaultWikitextNS();
			$title = Title::newFromText( $title, $ns );
		}
		$this->assertNotNull( $title );

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );

		if ( $user == null ) {
			$user = $this->getTestUser()->getUser();
		}

		if ( $baseText !== null ) {
			$content = ContentHandler::makeContent( $baseText, $title );
			$page->doUserEditContent( $content, $user, "base text for test" );

			// Set the latest timestamp back a while
			$dbw = wfGetDB( DB_PRIMARY );
			$dbw->update(
				'revision',
				[ 'rev_timestamp' => $dbw->timestamp( '20120101000000' ) ],
				[ 'rev_id' => $page->getLatest() ]
			);
			$page->clear();

			$content = $page->getContent();
			$this->assertInstanceOf( TextContent::class, $content );
			$currentText = $content->getText();

			# EditPage rtrim() the user input, so we alter our expected text
			# to reflect that.
			$this->assertEquals(
				rtrim( $baseText ),
				rtrim( $currentText ),
				'page should have the text specified'
			);
		}

		if ( !isset( $edit['wpEditToken'] ) ) {
			$edit['wpEditToken'] = $user->getEditToken();
		}

		if ( !isset( $edit['wpEdittime'] ) && !isset( $edit['editRevId'] ) ) {
			$edit['wpEdittime'] = $page->exists() ? $page->getTimestamp() : '';
		}

		if ( !isset( $edit['wpStarttime'] ) ) {
			$edit['wpStarttime'] = wfTimestampNow();
		}

		if ( !isset( $edit['wpUnicodeCheck'] ) ) {
			$edit['wpUnicodeCheck'] = EditPage::UNICODE_CHECK;
		}

		$req = new FauxRequest( $edit, true ); // session ??

		$context = new RequestContext();
		$context->setRequest( $req );
		$context->setTitle( $title );
		$context->setUser( $user );
		$article = new Article( $title );
		$article->setContext( $context );
		$ep = new EditPage( $article );
		$ep->setContextTitle( $title );
		$ep->importFormData( $req );

		$bot = isset( $edit['bot'] ) ? (bool)$edit['bot'] : false;

		// this is where the edit happens!
		// Note: don't want to use EditPage::attemptSave, because it messes with $wgOut
		// and throws exceptions like PermissionsError
		$status = $ep->internalAttemptSave( $result, $bot );

		// check edit code
		$this->assertSame(
			$expectedCode,
			$status->value,
			"Expected result code mismatch. $message"
		);

		return WikiPage::factory( $title );
	}

	/** AccidentalRecreationConstraint integration */
	public function testAccidentalRecreationConstraint() {
		// Make sure it exists
		$this->getExistingTestPage( 'AccidentalRecreationConstraintPage' );

		// And now delete it, so that there is a deletion log
		$page = $this->getNonExistingTestPage( 'AccidentalRecreationConstraintPage' );
		$title = $page->getTitle();

		// Set the time of the deletion to be a specific time, so we can be sure to start the
		// edit before it. Since the constraint will query for the most recent timestamp,
		// update *all* deletion logs for the page to the same timestamp (1 January 2020)
		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->update(
			'logging',
			[ 'log_timestamp' => $dbw->timestamp( '20200101000000' ) ],
			[
				'log_namespace' => $title->getNamespace(),
				'log_title' => $title->getDBKey(),
				'log_type' => 'delete',
				'log_action' => 'delete'
			],
			__METHOD__
		);

		$user = $this->getTestUser()->getUser();

		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		// Needs edit rights to pass EditRightConstraint and reach AccidentalRecreationConstraint
		$permissionManager->overrideUserRightsForTesting( $user, [ 'edit' ] );

		// Started the edit on 1 January 2019, page was deleted on 1 January 2020
		$edit = [
			'wpTextbox1' => 'New content',
			'wpStarttime' => '20190101000000'
		];
		$this->assertEdit(
			$title,
			null,
			$user,
			$edit,
			EditPage::AS_ARTICLE_WAS_DELETED,
			'expected AS_ARTICLE_WAS_DELETED update'
		);
	}

	/** AutoSummaryMissingSummaryConstraint integration */
	public function testAutoSummaryMissingSummaryConstraint() {
		// Require the summary
		$this->mergeMwGlobalArrayValue(
			'wgDefaultUserOptions',
			[ 'forceeditsummary' => 1 ]
		);

		$page = $this->getExistingTestPage( 'AutoSummaryMissingSummaryConstraint page does exist' );
		$title = $page->getTitle();

		$user = $this->getTestUser()->getUser();

		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		// Needs edit rights to pass EditRightConstraint and reach NewSectionMissingSummaryConstraint
		$permissionManager->overrideUserRightsForTesting( $user, [ 'edit' ] );

		$edit = [
			'wpTextbox1' => 'New content, different from base content',
			'wpSummary' => 'SameAsAutoSummary',
			'wpAutoSummary' => md5( 'SameAsAutoSummary' )
		];
		$this->assertEdit(
			$title,
			'Base content, different from new content',
			$user,
			$edit,
			EditPage::AS_SUMMARY_NEEDED,
			'expected AS_SUMMARY_NEEDED update'
		);
	}

	/** ChangeTagsConstraint integration */
	public function testChangeTagsConstraint() {
		// Remove rights
		$this->mergeMwGlobalArrayValue(
			'wgRevokePermissions',
			[ 'user' => [ 'applychangetags' => true ] ]
		);
		$edit = [
			'wpTextbox1' => 'Text',
			'wpChangeTags' => 'tag-name'
		];
		$this->assertEdit(
			'EditPageTest_changeTagsConstraint',
			null,
			null,
			$edit,
			EditPage::AS_CHANGE_TAG_ERROR,
			'expected AS_CHANGE_TAG_ERROR update'
		);
	}

	/** ContentModelChangeConstraint integration */
	public function testContentModelChangeConstraint() {
		$user = $this->getTestUser()->getUser();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		// Needs edit rights to pass EditRightConstraint and reach ContentModelChangeConstraint
		$permissionManager->overrideUserRightsForTesting( $user, [ 'edit' ] );

		$edit = [
			'wpTextbox1' => 'New text goes here',
			'wpSummary' => 'Summary',
			'model' => CONTENT_MODEL_TEXT,
			'format' => CONTENT_FORMAT_TEXT,
		];

		$title = Title::newFromText( 'Example', NS_MAIN );
		$this->assertSame(
			CONTENT_MODEL_WIKITEXT,
			$title->getContentModel(),
			'title should start as wikitext content model'
		);

		$this->assertEdit(
			$title,
			'Base text',
			$user,
			$edit,
			EditPage::AS_NO_CHANGE_CONTENT_MODEL,
			'expected AS_NO_CHANGE_CONTENT_MODEL update'
		);
	}

	/** CreationPermissionConstraint integration */
	public function testCreationPermissionConstraint() {
		$page = $this->getNonexistingTestPage( 'CreationPermissionConstraint page does not exist' );
		$title = $page->getTitle();

		$user = $this->getTestUser()->getUser();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		// Needs edit rights to pass EditRightConstraint and reach CreationPermissionConstraint
		$permissionManager->overrideUserRightsForTesting( $user, [ 'edit' ] );

		$edit = [
			'wpTextbox1' => 'Page content',
			'wpSummary' => 'Summary'
		];
		$this->assertEdit(
			$title,
			null,
			$user,
			$edit,
			EditPage::AS_NO_CREATE_PERMISSION,
			'expected AS_NO_CREATE_PERMISSION creation'
		);
	}

	/** DefaultTextConstraint integration */
	public function testDefaultTextConstraint() {
		$page = $this->getNonexistingTestPage( 'DefaultTextConstraint page does not exist' );
		$title = $page->getTitle();

		$user = $this->getTestUser()->getUser();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		// Needs edit and createpage rights to pass EditRightConstraint and CreationPermissionConstraint
		$permissionManager->overrideUserRightsForTesting( $user, [ 'edit', 'createpage' ] );

		$edit = [
			'wpTextbox1' => '',
			'wpSummary' => 'Summary'
		];
		$this->assertEdit(
			$title,
			null,
			$user,
			$edit,
			EditPage::AS_BLANK_ARTICLE,
			'expected AS_BLANK_ARTICLE creation'
		);
	}

	/**
	 * EditFilterMergedContentHookConstraint integration
	 * @dataProvider provideTestEditFilterMergedContentHookConstraint
	 * @param bool $hookReturn
	 * @param ?int $statusValue
	 * @param bool $statusFatal
	 * @param int $expectedFailure
	 * @param string $expectedFailureStr
	 */
	public function testEditFilterMergedContentHookConstraint(
		bool $hookReturn,
		$statusValue,
		bool $statusFatal,
		int $expectedFailure,
		string $expectedFailureStr
	) {
		$this->setTemporaryHook(
			'EditFilterMergedContent',
			static function ( $context, $content, $status, $summary, $user, $minorEdit )
				use ( $hookReturn, $statusValue, $statusFatal )
			{
				if ( $statusValue !== null ) {
					$status->value = $statusValue;
				}
				if ( $statusFatal ) {
					$status->fatal( 'SomeErrorInTheHook' );
				}
				return $hookReturn;
			}
		);

		$user = $this->getTestUser()->getUser();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		// Needs edit and createpage rights to pass EditRightConstraint and CreationPermissionConstraint
		$permissionManager->overrideUserRightsForTesting( $user, [ 'edit', 'createpage' ] );

		$edit = [
			'wpTextbox1' => 'Text',
			'wpSummary' => 'Summary'
		];
		$this->assertEdit(
			'EditPageTest_testEditFilterMergedContentHookConstraint',
			null,
			$user,
			$edit,
			$expectedFailure,
			"expected $expectedFailureStr creation"
		);
	}

	public function provideTestEditFilterMergedContentHookConstraint() {
		yield 'Hook returns false, status is good, no value set' => [
			false, null, false, EditPage::AS_HOOK_ERROR_EXPECTED, 'AS_HOOK_ERROR_EXPECTED'
		];
		yield 'Hook returns false, status is good, value set' => [
			false, 1234567, false, 1234567, 'custom value 1234567'
		];
		yield 'Hook returns false, status is not good' => [
			false, null, true, EditPage::AS_HOOK_ERROR_EXPECTED, 'AS_HOOK_ERROR_EXPECTED'
		];
		yield 'Hook returns true, status is not ok' => [
			true, null, true, EditPage::AS_HOOK_ERROR_EXPECTED, 'AS_HOOK_ERROR_EXPECTED'
		];
	}

	/**
	 * EditRightConstraint integration
	 * @dataProvider provideTestEditRightConstraint
	 * @param bool $anon
	 * @param int $expectedErrorCode
	 */
	public function testEditRightConstraint( $anon, $expectedErrorCode ) {
		if ( $anon ) {
			$user = $this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' );
		} else {
			$user = $this->getTestUser()->getUser();
		}
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		$permissionManager->overrideUserRightsForTesting( $user, [] );

		$edit = [
			'wpTextbox1' => 'Page content',
			'wpSummary' => 'Summary'
		];
		$this->assertEdit(
			'EditPageTest_noEditRight',
			'base text',
			$user,
			$edit,
			$expectedErrorCode,
			'expected AS_READ_ONLY_PAGE_* update'
		);
	}

	public function provideTestEditRightConstraint() {
		yield 'Anonymous user' => [ true, EditPage::AS_READ_ONLY_PAGE_ANON ];
		yield 'Registered user' => [ false, EditPage::AS_READ_ONLY_PAGE_LOGGED ];
	}

	/**
	 * ImageRedirectConstraint integration
	 * @dataProvider provideTestImageRedirectConstraint
	 * @param bool $anon
	 * @param int $expectedErrorCode
	 */
	public function testImageRedirectConstraint( $anon, $expectedErrorCode ) {
		if ( $anon ) {
			$user = $this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' );
		} else {
			$user = $this->getTestUser()->getUser();
		}

		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		// Needs edit rights to pass EditRightConstraint and reach ImageRedirectConstraint
		$permissionManager->overrideUserRightsForTesting( $user, [ 'edit' ] );

		$edit = [
			'wpTextbox1' => '#REDIRECT [[File:Example other file.jpg]]',
			'wpSummary' => 'Summary'
		];

		$title = Title::newFromText( 'Example.jpg', NS_FILE );
		$this->assertEdit(
			$title,
			null,
			$user,
			$edit,
			$expectedErrorCode,
			'expected AS_IMAGE_REDIRECT_* update'
		);
	}

	public function provideTestImageRedirectConstraint() {
		yield 'Anonymous user' => [ true, EditPage::AS_IMAGE_REDIRECT_ANON ];
		yield 'Registered user' => [ false, EditPage::AS_IMAGE_REDIRECT_LOGGED ];
	}

	/** MissingCommentConstraint integration */
	public function testMissingCommentConstraint() {
		$page = $this->getExistingTestPage( 'MissingCommentConstraint page does exist' );
		$title = $page->getTitle();

		$user = $this->getTestUser()->getUser();

		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		// Needs edit rights to pass EditRightConstraint and reach MissingCommentConstraint
		$permissionManager->overrideUserRightsForTesting( $user, [ 'edit' ] );

		$edit = [
			'wpTextbox1' => '',
			'wpSection' => 'new',
			'wpSummary' => 'Summary'
		];
		$this->assertEdit(
			$title,
			null,
			$user,
			$edit,
			EditPage::AS_TEXTBOX_EMPTY,
			'expected AS_TEXTBOX_EMPTY update'
		);
	}

	/** NewSectionMissingSummaryConstraint integration */
	public function testNewSectionMissingSummaryConstraint() {
		// Require the summary
		$this->mergeMwGlobalArrayValue(
			'wgDefaultUserOptions',
			[ 'forceeditsummary' => 1 ]
		);

		$page = $this->getExistingTestPage( 'NewSectionMissingSummaryConstraint page does exist' );
		$title = $page->getTitle();

		$user = $this->getTestUser()->getUser();

		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		// Needs edit rights to pass EditRightConstraint and reach NewSectionMissingSummaryConstraint
		$permissionManager->overrideUserRightsForTesting( $user, [ 'edit' ] );

		$edit = [
			'wpTextbox1' => 'Comment',
			'wpSection' => 'new',
			'wpSummary' => ''
		];
		$this->assertEdit(
			$title,
			null,
			$user,
			$edit,
			EditPage::AS_SUMMARY_NEEDED,
			'expected AS_SUMMARY_NEEDED update'
		);
	}

	/** PageSizeConstraint integration */
	public function testPageSizeConstraintBeforeMerge() {
		// Max size: 1 kibibyte
		$this->setMwGlobals( [
			'wgMaxArticleSize' => 1
		] );

		$edit = [
			'wpTextbox1' => str_repeat( 'text', 1000 )
		];
		$this->assertEdit(
			'EditPageTest_pageSizeConstraintBeforeMerge',
			null,
			null,
			$edit,
			EditPage::AS_CONTENT_TOO_BIG,
			'expected AS_CONTENT_TOO_BIG update'
		);
	}

	/** PageSizeConstraint integration */
	public function testPageSizeConstraintAfterMerge() {
		// Max size: 1 kibibyte
		$this->setMwGlobals( [
			'wgMaxArticleSize' => 1
		] );

		$edit = [
			'wpSection' => 'new',
			'wpTextbox1' => str_repeat( 'b', 600 )
		];
		$this->assertEdit(
			'EditPageTest_pageSizeConstraintAfterMerge',
			str_repeat( 'a', 600 ),
			null,
			$edit,
			EditPage::AS_MAX_ARTICLE_SIZE_EXCEEDED,
			'expected AS_MAX_ARTICLE_SIZE_EXCEEDED update'
		);
	}

	/** ReadOnlyConstraint integration */
	public function testReadOnlyConstraint() {
		$readOnlyMode = $this->createMock( ReadOnlyMode::class );
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );

		$edit = [
			'wpTextbox1' => 'Text goes here'
		];
		$this->assertEdit(
			'EditPageTest_readOnlyConstraint',
			null,
			null,
			$edit,
			EditPage::AS_READ_ONLY_PAGE,
			'expected AS_READ_ONLY_PAGE update'
		);
	}

	/** SelfRedirectConstraint integration */
	public function testSelfRedirectConstraint() {
		// Use a page that does not exist to be sure that it is not already a self redirect
		$page = $this->getNonexistingTestPage( 'SelfRedirectConstraint page does not exist' );
		$title = $page->getTitle();

		$edit = [
			'wpTextbox1' => '#REDIRECT [[SelfRedirectConstraint page does not exist]]',
			'wpSummary' => 'Redirect to self'
		];
		$this->assertEdit(
			$title,
			'zero',
			null,
			$edit,
			EditPage::AS_SELF_REDIRECT,
			'expected AS_SELF_REDIRECT update'
		);
	}

	/** SimpleAntiSpamConstraint integration */
	public function testSimpleAntiSpamConstraint() {
		$edit = [
			'wpTextbox1' => 'one',
			'wpSummary' => 'first update',
			'wpAntispam' => 'tatata'
		];
		$this->assertEdit(
			'EditPageTest_testUpdatePageSpamError',
			'zero',
			null,
			$edit,
			EditPage::AS_SPAM_ERROR,
			'expected AS_SPAM_ERROR update'
		);
	}

	/** SpamRegexConstraint integration */
	public function testSpamRegexConstraint() {
		$spamChecker = $this->createMock( SpamChecker::class );
		$spamChecker->method( 'checkContent' )
			->will( $this->returnArgument( 0 ) );
		$spamChecker->method( 'checkSummary' )
			->will( $this->returnArgument( 0 ) );
		$this->setService( 'SpamChecker', $spamChecker );

		$edit = [
			'wpTextbox1' => 'two',
			'wpSummary' => 'spam summary'
		];
		$this->assertEdit(
			'EditPageTest_testUpdatePageSpamRegexError',
			'zero',
			null,
			$edit,
			EditPage::AS_SPAM_ERROR,
			'expected AS_SPAM_ERROR update'
		);
	}

	/** UserBlockConstraint integration */
	public function testUserBlockConstraint() {
		$user = $this->createMock( User::class );
		$user->method( 'getName' )->willReturn( 'NameGoesHere' );
		$user->method( 'getId' )->willReturn( 12345 );

		$permissionManager = $this->createMock( PermissionManager::class );
		// Needs edit rights to pass EditRightConstraint and reach UserBlockConstraint
		$permissionManager->method( 'userHasRight' )->willReturn( true );
		$permissionManager->method( 'userCan' )->willReturn( true );

		// Not worried about the specifics of the method call, those are tested in
		// the UserBlockConstraintTest
		$permissionManager->method( 'isBlockedFrom' )->willReturn( true );

		$this->setService( 'PermissionManager', $permissionManager );

		$edit = [
			'wpTextbox1' => 'Page content',
			'wpSummary' => 'Summary'
		];
		$this->assertEdit(
			'EditPageTest_userBlocked',
			'base text',
			null,
			$edit,
			EditPage::AS_BLOCKED_PAGE_FOR_USER,
			'expected AS_BLOCKED_PAGE_FOR_USER update'
		);
	}

	/** UserRateLimitConstraint integration */
	public function testUserRateLimitConstraint() {
		$this->setTemporaryHook(
			'PingLimiter',
			static function ( $user, $action, &$result, $incrBy ) {
				// Always fail
				$result = true;
				return false;
			}
		);

		$edit = [
			'wpTextbox1' => 'Text goes here'
		];
		$this->assertEdit(
			'EditPageTest_userRateLimitConstraint',
			null,
			null,
			$edit,
			EditPage::AS_RATE_LIMITED,
			'expected AS_RATE_LIMITED update'
		);
	}

}
