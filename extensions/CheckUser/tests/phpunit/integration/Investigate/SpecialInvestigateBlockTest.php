<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate;

use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Tests\SpecialPage\FormSpecialPageTestCase;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\Investigate\SpecialInvestigateBlock
 * @group CheckUser
 * @group Database
 */
class SpecialInvestigateBlockTest extends FormSpecialPageTestCase {

	use MockAuthorityTrait;

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'InvestigateBlock' );
	}

	/** @dataProvider provideUserRightsForFailure */
	public function testViewSpecialPageWhenMissingNecessaryRights( $rights ) {
		// Expect that a PermissionsError is thrown, which indicates that the special page correctly identified that
		// the authority viewing the page does not have the necessary rights to do so.
		$this->expectException( PermissionsError::class );
		// Execute the special page.
		$this->executeSpecialPage(
			'', new FauxRequest(), null,
			$this->mockRegisteredAuthorityWithPermissions( $rights )
		);
	}

	public static function provideUserRightsForFailure() {
		return [
			'Only the checkuser right' => [ [ 'checkuser' ] ],
			'Only the block right' => [ [ 'block' ] ],
		];
	}

	/** @dataProvider provideUserRightsForFailure */
	public function testUserCanExecute( $rights ) {
		$specialPage = $this->newSpecialPage();
		$userIdentity = $this->getServiceContainer()->getUserFactory()->newFromUserIdentity(
			$this->mockRegisteredAuthorityWithPermissions( $rights )->getUser()
		);
		$this->assertFalse(
			$specialPage->userCanExecute( $userIdentity ),
			'User should not be able to execute the special page if they are missing checkuser and block rights.'
		);
	}

	private function getUserForSuccess() {
		return $this->getMutableTestUser( [ 'checkuser', 'sysop' ] )->getUser();
	}

	/**
	 * Provide both values of the temporary feature flag $wgEnableMultiBlocks
	 */
	public static function provideMultiBlocksOption() {
		return [
			'single blocks' => [ false ],
			'multiblocks' => [ true ]
		];
	}

	/**
	 * @dataProvider provideMultiBlocksOption
	 */
	public function testViewSpecialPageWithNoDataEntered( $multiBlocks ) {
		// Define wgBlockAllowsUTEdit and wgEnableUserEmail as true to get all the fields that can be in the form.
		$this->overrideConfigValue( MainConfigNames::BlockAllowsUTEdit, true );
		$this->overrideConfigValue( MainConfigNames::EnableUserEmail, true );
		$this->overrideConfigValue( MainConfigNames::EnableMultiBlocks, $multiBlocks );
		// Execute the special page.
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $this->getUserForSuccess() );
		// Verify that the title is shown
		$this->assertStringContainsString( '(checkuser-investigateblock', $html );
		// Verify that the targets multiselect is shown
		$this->assertStringContainsString( '(checkuser-investigateblock-target', $html );
		// Verify that the 'Actions to block' section is shown
		$this->assertStringContainsString( '(checkuser-investigateblock-actions', $html );
		$this->assertStringContainsString( '(checkuser-investigateblock-email-label', $html );
		$this->assertStringContainsString( '(checkuser-investigateblock-usertalk-label', $html );
		$reblock = $multiBlocks ? 'newblock' : 'reblock';
		$this->assertStringContainsString( "(checkuser-investigateblock-$reblock-label", $html );
		// Verify that the 'Reason' section is shown
		$this->assertStringContainsString( '(checkuser-investigateblock-reason', $html );
		// Verify that the 'Options' section is shown
		$this->assertStringContainsString( '(checkuser-investigateblock-options', $html );
		if ( $this->isSocialProfileExtensionInstalled() ) {
			$this->assertStringNotContainsString( '(checkuser-investigateblock-notice-user-page-label', $html );
		} else {
			$this->assertStringContainsString( '(checkuser-investigateblock-notice-user-page-label', $html );

		}
		$this->assertStringContainsString( '(checkuser-investigateblock-notice-talk-page-label', $html );
		// Assert that the 'Confirm blocks' checkbox is not shown (this should only be shown after the form is submitted
		// and a warning is to be shown).
		$this->assertStringNotContainsString( '(checkuser-investigateblock-confirm-blocks-label', $html );
	}

	public function testLoadWithTooManyPrefilledUsers() {
		// Set the max blocks limit to a small number for testing, which we can reasonably exceed in the test.
		$this->overrideConfigValue( 'CheckUserMaxBlocks', 3 );
		// Set up a valid request that does not submit the form but pre-fills the form with too many users.
		$fauxRequest = new FauxRequest( [ 'wpTargets' => "Test1\nTest2\nTest3\nTest4" ] );
		// Assign the fake valid request to the main request context and the test user as the session user.
		RequestContext::getMain()->setRequest( $fauxRequest );
		// Execute the special page and get the HTML output.
		[ $html ] = $this->executeSpecialPage( '', $fauxRequest, null, $this->getUserForSuccess() );
		// Verify that a warning is shown indicating that the users list has been truncated.
		$this->assertStringContainsString( '(checkuser-investigateblock-warning-users-truncated', $html );
	}

	/**
	 * Using the wAvatar class existence check as a proxy because as of
	 * early April 2025 SocialProfile lacks an extension.json entry point, which
	 * thus prevents using ExtensionRegistry to check if SP is installed.
	 *
	 * @return bool
	 */
	private function isSocialProfileExtensionInstalled(): bool {
		return class_exists( 'wAvatar' );
	}

	/**
	 * SocialProfile user pages do not use wikitext and therefore block attempts to edit them using the API,
	 * so Special:InvestigateBlock does not work when SocialProfile is installed. We need to skip the tests
	 * to avoid failures in CI.
	 *
	 * @return void
	 */
	private function markTestSkippedIfSocialProfileExtensionInstalled() {
		if ( $this->isSocialProfileExtensionInstalled() ) {
			$this->markTestSkipped( "Extension SocialProfile cannot be installed when running this test (T390590)" );
		}
	}

	public function testOnSubmitOneUserTargetWithNotices() {
		$this->markTestSkippedIfSocialProfileExtensionInstalled();
		// Set-up the valid request and get a test user which has the necessary rights.
		$testPerformer = $this->getUserForSuccess();
		RequestContext::getMain()->setUser( $testPerformer );
		$testTargetUser = $this->getTestUser()->getUser();
		$fauxRequest = new FauxRequest(
			[
				// Test with a single target user, with both notices being added.
				'wpTargets' => $testTargetUser->getName(), 'wpUserPageNotice' => 1, 'wpTalkPageNotice' => 1,
				'wpUserPageNoticeText' => 'Test user page text', 'wpTalkPageNoticeText' => 'Test talk page text',
				'wpReason' => 'other', 'wpReason-other' => 'Test reason',
				'wpEditToken' => $testPerformer->getEditToken(),
			],
			true,
			RequestContext::getMain()->getRequest()->getSession()
		);
		// Assign the fake valid request to the main request context, as well as updating the session user
		// so that the CSRF token is a valid token for the request user.
		RequestContext::getMain()->setRequest( $fauxRequest );
		RequestContext::getMain()->getRequest()->getSession()->setUser( $testPerformer );

		// Execute the special page and get the HTML output.
		[ $html ] = $this->executeSpecialPage( '', $fauxRequest, null, $testPerformer );
		// Assert that the success message is shown.
		$this->assertStringContainsString( '(checkuser-investigateblock-success', $html );
		$this->assertStringNotContainsString( '(checkuser-investigateblock-notices-failed', $html );

		// Assert that the user is blocked
		$block = $this->getServiceContainer()->getDatabaseBlockStore()->newFromTarget( $testTargetUser );
		$this->assertNotNull( $block, 'The target user was not blocked by Special:InvestigateBlock' );
		// Assert that the block parameters are as expected
		$this->assertSame( 'Test reason', $block->getReasonComment()->text, 'The reason was not as expected' );
		$this->assertSame( $testPerformer->getId(), $block->getBy(), 'The blocking user was not as expected' );
		$this->assertTrue( wfIsInfinity( $block->getExpiry() ), 'The block should be indefinite' );
		$this->assertTrue( $block->isCreateAccountBlocked(), 'The block should prevent account creation' );
		$this->assertTrue( $block->isSitewide(), 'The block should be sitewide' );
		$this->assertTrue( $block->isAutoblocking(), 'The block should be autoblocking' );

		// Assert that the user page and talk page notices are as expected
		$this->assertSame(
			'Test user page text',
			$this->getServiceContainer()->getWikiPageFactory()
				->newFromTitle( $testTargetUser->getUserPage() )
				->getRevisionRecord()
				->getContentOrThrow( SlotRecord::MAIN )
				->getWikitextForTransclusion(),
			'The user page notice was not as expected'
		);
		$this->assertSame(
			'Test talk page text',
			$this->getServiceContainer()->getWikiPageFactory()
				->newFromTitle( $testTargetUser->getTalkPage() )
				->getRevisionRecord()
				->getContentOrThrow( SlotRecord::MAIN )
				->getWikitextForTransclusion(),
			'The user talk page notice was not as expected'
		);
	}

	public function testOnSubmitForIPTargetsWithFailedNotices() {
		$this->markTestSkippedIfSocialProfileExtensionInstalled();
		ConvertibleTimestamp::setFakeTime( '20210405060708' );
		$testPerformer = $this->getUserForSuccess();
		RequestContext::getMain()->setUser( $testPerformer );
		// Simulate that the user does not have the necessary rights to create the user page and talk page notices by
		// removing the 'edit' right from the user submitting the form (and all other rights than needed to execute
		// the special page).
		$this->overrideUserPermissions( $testPerformer, [ 'block', 'checkuser' ] );
		// Set-up the valid request and get a test user which has the necessary rights.
		$testTargetUser = $this->getTestUser()->getUser();
		$fauxRequest = new FauxRequest(
			[
				// Test with with a single non-existent target user, with both notices being added.
				// The notices should not be added if the block fails to be applied.
				'wpTargets' => "127.0.0.2\n1.2.3.4/24", 'wpUserPageNotice' => 1, 'wpTalkPageNotice' => 1,
				'wpUserPageNoticeText' => 'Test user page text', 'wpTalkPageNoticeText' => 'Test talk page text',
				'wpReason' => 'other', 'wpReason-other' => 'Test reason',
				'wpEditToken' => $testPerformer->getEditToken(),
			],
			true,
			RequestContext::getMain()->getRequest()->getSession()
		);
		// Assign the fake valid request to the main request context, as well as updating the session user
		// so that the CSRF token is a valid token for the request user.
		RequestContext::getMain()->setRequest( $fauxRequest );
		RequestContext::getMain()->getRequest()->getSession()->setUser( $testPerformer );

		// Execute the special page and get the HTML output.
		[ $html ] = $this->executeSpecialPage( '', $fauxRequest, null, $testPerformer );
		// Assert that the notices failed message is shown.
		$this->assertStringContainsString( '(checkuser-investigateblock-notices-failed', $html );
		// Assert that the 'Confirm blocks' checkbox is not shown (this should only be shown after the form is submitted
		// and a warning is shown). In this case an error was shown instead of a warning.
		$this->assertStringNotContainsString( '(checkuser-investigateblock-confirm-blocks-label', $html );

		// Assert that both targets are blocked
		// First check the IP address target
		$block = $this->getServiceContainer()->getDatabaseBlockStore()->newFromTarget( '127.0.0.2' );
		$this->assertNotNull( $block, 'The IP address target was not blocked by Special:InvestigateBlock' );
		// Assert that the block parameters are as expected
		$this->assertSame( '20210412060708', $block->getExpiry(), 'The block should be indefinite' );

		// Secondly check the IP range target
		$block = $this->getServiceContainer()->getDatabaseBlockStore()->newFromTarget( '1.2.3.0/24' );
		$this->assertNotNull( $block, 'The IP range target was not blocked by Special:InvestigateBlock' );
		// Assert that the block parameters are as expected
		$this->assertSame( '20210412060708', $block->getExpiry(), 'The block should be indefinite' );

		// Assert that the user page and talk page for the non-existent user are not created, because the user was
		// prevented from creating the notices
		$this->assertFalse(
			$this->getServiceContainer()->getWikiPageFactory()
				->newFromTitle( $testTargetUser->getUserPage() )
				->exists(),
			'The user page notice should not have been added as the user cannot create the page.'
		);
		$this->assertFalse(
			$this->getServiceContainer()->getWikiPageFactory()
				->newFromTitle( $testTargetUser->getTalkPage() )
				->exists(),
			'The user talk page notice should not have been added as the user cannot create the page.'
		);
	}

	public function testOnSubmitForIPTargetWithMissingReason() {
		$testPerformer = $this->getUserForSuccess();
		RequestContext::getMain()->setUser( $testPerformer );
		$fauxRequest = new FauxRequest(
			[
				'wpTargets' => "127.0.0.2\n1.2.3.4/24",
				// wpReason as 'other' is no text and leave the other field empty. This simulates no provided reason.
				'wpReason' => 'other', 'wpReason-other' => '',
				'wpEditToken' => $testPerformer->getEditToken(),
			],
			true,
			RequestContext::getMain()->getRequest()->getSession()
		);
		// Assign the fake valid request to the main request context, as well as updating the session user
		// so that the CSRF token is a valid token for the request user.
		RequestContext::getMain()->setRequest( $fauxRequest );
		RequestContext::getMain()->getRequest()->getSession()->setUser( $testPerformer );

		// Execute the special page and get the HTML output.
		[ $html ] = $this->executeSpecialPage( '', $fauxRequest, null, $testPerformer );
		// Assert that the required field error is displayed on the page.
		$this->assertStringContainsString( '(htmlform-required', $html );
	}

	public function testOnSubmitForIPsAndUsersAsTargets() {
		// Set-up the valid request and get a test user which has the necessary rights.
		$testPerformer = $this->getUserForSuccess();
		RequestContext::getMain()->setUser( $testPerformer );
		$testTargetUser = $this->getTestUser()->getUser();
		$fauxRequest = new FauxRequest(
			[
				// Test using two targets, one being a user and the other an IP.
				'wpTargets' => $testTargetUser->getName() . "\n1.2.3.4",
				'wpReason' => 'other', 'wpReason-other' => 'Test reason',
				'wpEditToken' => $testPerformer->getEditToken(),
			],
			true,
			RequestContext::getMain()->getRequest()->getSession()
		);
		// Assign the fake valid request to the main request context, as well as updating the session user
		// so that the CSRF token is a valid token for the request user.
		RequestContext::getMain()->setRequest( $fauxRequest );
		RequestContext::getMain()->getRequest()->getSession()->setUser( $testPerformer );

		// Execute the special page and get the HTML output.
		[ $html ] = $this->executeSpecialPage( '', $fauxRequest, null, $testPerformer );
		// Assert that the warning message is shown for blocking accounts and IPs in the same usage of the form.
		$this->assertStringContainsString( '(checkuser-investigateblock-warning-ips-and-users-in-targets', $html );
		$this->assertStringContainsString( '(checkuser-investigateblock-warning-confirmaction', $html );
		// Assert that the 'Confirm blocks' checkbox is shown
		$this->assertStringContainsString( '(checkuser-investigateblock-confirm-blocks-label', $html );

		// Assert that no target got blocked
		$block = $this->getServiceContainer()->getDatabaseBlockStore()->newFromTarget( $testTargetUser );
		$this->assertNull(
			$block,
			'The target user was blocked by Special:InvestigateBlock when the form failed to submit.'
		);
		$block = $this->getServiceContainer()->getDatabaseBlockStore()->newFromTarget( '1.2.3.4' );
		$this->assertNull(
			$block,
			'The target user was blocked by Special:InvestigateBlock when the form failed to submit.'
		);
	}

	public function testOnSubmitForIPsAndUsersAsTargetsWithConfirmBlocksChecked() {
		// Define wgBlockAllowsUTEdit and wgEnableUserEmail as true so we can test that the disable talk and
		// disable email options work.
		$this->overrideConfigValue( MainConfigNames::BlockAllowsUTEdit, true );
		$this->overrideConfigValue( MainConfigNames::EnableUserEmail, true );
		// Set a fake time so that we can validate the block expiry time for the IP target.
		ConvertibleTimestamp::setFakeTime( '20210405060708' );
		// Set-up the valid request and get a test user which has the necessary rights.
		$testPerformer = $this->getUserForSuccess();
		RequestContext::getMain()->setUser( $testPerformer );
		$testTargetUser = $this->getTestUser()->getUser();
		$fauxRequest = new FauxRequest(
			[
				// Test using two targets, one being a user and the other an IP. Also have the 'Confirm block' checkbox
				// checked to ignore the warning.
				'wpTargets' => $testTargetUser->getName() . "\n1.2.3.4", 'wpConfirm' => 1,
				'wpReason' => 'other', 'wpReason-other' => 'Test reason',
				'wpEditToken' => $testPerformer->getEditToken(),
				// Also test disabling the talk page and email as part of the blocks.
				'wpDisableUTEdit' => 1, 'wpDisableEmail' => 1,
			],
			true,
			RequestContext::getMain()->getRequest()->getSession()
		);
		// Assign the fake valid request to the main request context, as well as updating the session user
		// so that the CSRF token is a valid token for the request user.
		RequestContext::getMain()->setRequest( $fauxRequest );
		RequestContext::getMain()->getRequest()->getSession()->setUser( $testPerformer );

		// Execute the special page and get the HTML output.
		[ $html ] = $this->executeSpecialPage( '', $fauxRequest, null, $testPerformer );
		// Assert that the success message is shown.
		$this->assertStringContainsString( '(checkuser-investigateblock-success', $html );

		// Assert that the user is blocked. Skipping testing the parameters for this block as they are tested in
		// ::testOnSubmitOneUserTargetWithNotices.
		$accountBlock = $this->getServiceContainer()->getDatabaseBlockStore()->newFromTarget( $testTargetUser );
		$this->assertNotNull( $accountBlock, 'The target user was not blocked by Special:InvestigateBlock' );

		// Assert that the IP is blocked, including checking the block parameters.
		$ipBlock = $this->getServiceContainer()->getDatabaseBlockStore()->newFromTarget( '1.2.3.4' );
		$this->assertNotNull( $ipBlock, 'The target IP was not blocked by Special:InvestigateBlock' );
		// Assert that the block parameters are as expected
		$this->assertSame( 'Test reason', $ipBlock->getReasonComment()->text, 'The reason was not as expected' );
		$this->assertSame( $testPerformer->getId(), $ipBlock->getBy(), 'The blocking user was not as expected' );
		$this->assertSame( '20210412060708', $ipBlock->getExpiry(), 'The block should have an expiry of 1 week' );
		$this->assertTrue( $ipBlock->isCreateAccountBlocked(), 'The block should prevent account creation' );
		$this->assertTrue( $ipBlock->isSitewide(), 'The block should be sitewide' );
		$this->assertTrue( $ipBlock->isEmailBlocked(), 'The block should prevent email sending' );
		$this->assertTrue(
			$ipBlock->appliesToUsertalk(),
			'The block should prevent edits to their own user talk page'
		);
	}

	/**
	 * Submit the form when the user is already blocked, without the reblock option
	 *
	 * @dataProvider provideMultiBlocksOption
	 */
	public function testOnSubmitBlockFailure( $multiBlocks ) {
		$this->overrideConfigValue( MainConfigNames::EnableMultiBlocks, $multiBlocks );
		$testPerformer = $this->getUserForSuccess();
		$targetUser = $this->getTestUser()->getUser();
		$this->getServiceContainer()->getDatabaseBlockStore()
			->insertBlockWithParams( [
				'targetUser' => $targetUser,
				'by' => $testPerformer
			] );
		$fauxRequest = new FauxRequest(
			[
				'wpTargets' => $targetUser->getName(),
				'wpConfirm' => 1,
				'wpReason' => 'other',
				'wpReason-other' => 'Test reason',
				'wpEditToken' => $testPerformer->getEditToken(),
			],
			true,
			RequestContext::getMain()->getRequest()->getSession()
		);
		RequestContext::getMain()->setRequest( $fauxRequest );
		RequestContext::getMain()->getRequest()->getSession()->setUser( $testPerformer );
		[ $html ] = $this->executeSpecialPage( '', $fauxRequest, null, $testPerformer );
		$expect = 'checkuser-investigateblock-failure';
		if ( $multiBlocks ) {
			$expect .= '-multi';
		}
		$this->assertStringContainsString( $expect, $html );
	}
}
