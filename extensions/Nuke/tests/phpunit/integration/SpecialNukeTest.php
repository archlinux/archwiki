<?php

namespace MediaWiki\Extension\Nuke\Test\Integration;

use ErrorPageError;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Nuke\SpecialNuke;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use PermissionsError;
use SpecialPageTestBase;
use UploadFromFile;
use UserBlockedError;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Nuke\SpecialNuke
 */
class SpecialNukeTest extends SpecialPageTestBase {

	use TempUserTestTrait;

	protected function newSpecialPage(): SpecialNuke {
		$services = $this->getServiceContainer();

		return new SpecialNuke(
			$services->getJobQueueGroup(),
			$services->getDBLoadBalancerFactory(),
			$services->getPermissionManager(),
			$services->getRepoGroup(),
			$services->getUserFactory(),
			$services->getUserOptionsLookup(),
			$services->getUserNamePrefixSearch(),
			$services->getUserNameUtils(),
			$services->getNamespaceInfo(),
			$services->getContentLanguage(),
			$services->getService( 'NukeIPLookup' )
		);
	}

	/**
	 * Ensure that the prompt prevents a user blocked from deleting
	 * pages from accessing the form.
	 *
	 * @return void
	 */
	public function testBlocked() {
		$user = $this->getTestSysop()->getUser();
		$performer = new UltimateAuthority( $user );

		// Self-blocks should still prevent the form from being shown
		$this->getServiceContainer()
			->getBlockUserFactory()
			->newBlockUser( $user, $performer, 'infinity', 'SpecialNukeTest::testBlocked' )
			->placeBlockUnsafe();

		$this->expectException( UserBlockedError::class );
		$this->executeSpecialPage( '', null, 'qqx', $performer );

		$this->getServiceContainer()
			->getUnblockUserFactory()
			->newUnblockUser( $user, $performer, 'SpecialNukeTest::testBlocked' )
			->unblockUnsafe();
	}

	public function testProtectedPage() {
		$pages = [];
		$pages[] = $this->insertPage( 'Page123', 'Test', NS_MAIN )[ 'title' ];
		$pages[] = $this->insertPage( 'Page456', 'Test', NS_MAIN )[ 'title' ];
		$pages[] = $this->insertPage( 'Page789', 'Test', NS_MAIN )[ 'title' ];

		$this->overrideConfigValues( [
			"GroupPermissions" => [
				"testgroup" => [
					"nuke" => true,
					"delete" => true
				]
			]
		] );

		$services = $this->getServiceContainer();
		$page = $services->getWikiPageFactory()
			->newFromTitle( $pages[2] );
		$restrictions = [];
		foreach ( $services->getRestrictionStore()->listApplicableRestrictionTypes( $page ) as $type ) {
			$restrictions[$type] = "sysop";
		}
		$cascade = false;
		$page->doUpdateRestrictions(
			$restrictions,
			[],
			$cascade,
			"test",
			$this->getTestSysop()->getUser()
		);

		$testUser = $this->getTestUser( [ "testgroup" ] );

		$request = new FauxRequest( [
			'action' => 'delete',
			'wpDeleteReasonList' => 'other',
			'wpReason' => 'Reason',
			'pages' => $pages,
			'wpFormIdentifier' => 'nukelist',
			'wpEditToken' => $testUser->getUser()->getEditToken(),
		], true );

		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( '', $request, 'qqx', $testUser->getAuthority() );

		foreach ( $pages as $checkedPage ) {
			$this->assertTrue( $checkedPage->exists() );
		}
	}

	public function testPrompt() {
		$admin = $this->getTestSysop()->getUser();
		$this->disableAutoCreateTempUser();
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', null, 'qqx', $performer );

		$this->assertStringContainsString( '(nuke-summary)', $html );
		$this->assertStringContainsString( '(nuke-tools)', $html );
	}

	/**
	 * Ensure that the prompt prevents a nuke user without the checkuser-temporary-account permission
	 * from performing CheckUser IP lookups
	 *
	 * @return void
	 */
	public function testPromptCheckUserNoPermission() {
		$this->expectException( PermissionsError::class );

		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$this->enableAutoCreateTempUser();
		$ip = '1.2.3.4';

		$adminUser = $this->getTestSysop()->getUser();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		$permissions = $permissionManager->getUserPermissions( $adminUser );
		$permissions = array_diff( $permissions, [ 'checkuser-temporary-account' ] );
		$permissionManager->overrideUserRightsForTesting( $adminUser,
			$permissions
		);
		$performer = new UltimateAuthority( $adminUser );
		$request = new FauxRequest( [
			'target' => $ip,
			'action' => 'submit',
		], true );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
	}

	/**
	 * Ensure that the prompt prevents a nuke user who hasn't accepted the agreement
	 * from performing CheckUser IP lookups
	 *
	 * @return void
	 */
	public function testPromptCheckUserNoPreference() {
		$this->expectException( ErrorPageError::class );
		$this->expectExceptionMessage(
			'To view temporary account contributions for an IP, please accept' .
			' the agreement in [[Special:Preferences|your preferences]].'
		);
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$this->enableAutoCreateTempUser();
		$ip = '1.2.3.4';
		$this->overrideConfigValues( [
			'GroupPermissions' => [
				'testgroup' => [
					'nuke' => true,
					'checkuser-temporary-account' => true
				]
			]
		] );

		$adminUser = $this->getTestUser( [ 'testgroup' ] )->getUser();
		$request = new FauxRequest( [
			'target' => $ip,
			'action' => 'submit',
		], true );
		$adminPerformer = new UltimateAuthority( $adminUser );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
	}

	/**
	 * Ensure that the prompt displays the correct messages when
	 * temp accounts and CheckUser are enabled
	 *
	 * @return void
	 */
	public function testPromptWithCheckUser() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$admin = $this->getTestSysop()->getUser();
		$this->enableAutoCreateTempUser();
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', null, 'qqx', $performer );

		$this->assertStringContainsString( '(nuke-summary)', $html );
		$this->assertStringContainsString( '(nuke-tools-tempaccount)', $html );
	}

	public function testPromptTarget() {
		$testUser = $this->getTestUser();
		$performer = $testUser->getAuthority();

		$this->editPage( 'Target1', 'test', "", NS_MAIN, $performer );
		$this->editPage( 'Target2', 'test', "", NS_MAIN, $performer );

		$adminUser = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'target' => $testUser->getUser()->getName()
		] );
		$adminPerformer = new UltimateAuthority( $adminUser );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );

		$this->assertStringContainsString( 'Target1', $html );
		$this->assertStringContainsString( 'Target2', $html );
	}

	/**
	 * Ensure that the prompt works with anon IP searches when
	 * temp accounts are disabled
	 *
	 * @return void
	 */
	public function testPromptTargetAnonUser() {
		$this->disableAutoCreateTempUser( [ 'known' => false ] );
		$ip = '127.0.0.1';
		$testUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( $ip );
		$performer = new UltimateAuthority( $testUser );

		$this->editPage( 'Target1', 'test', "", NS_MAIN, $performer );
		$this->editPage( 'Target2', 'test', "", NS_MAIN, $performer );

		$adminUser = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'target' => $testUser->getUser()->getName()
		] );
		$adminPerformer = new UltimateAuthority( $adminUser );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );

		$this->assertStringContainsString( '(nuke-list:', $html );
		$this->assertStringContainsString( 'Target1', $html );
		$this->assertStringContainsString( 'Target2', $html );

		$usernameCount = substr_count( $html, $ip );
		$this->assertStringContainsString( 5, $usernameCount );
	}

	/**
	 * Ensure that the prompt returns temp accounts from IP lookups when
	 * temp accounts and CheckUser are enabled
	 *
	 * @return void
	 */
	public function testPromptTargetCheckUser() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$this->enableAutoCreateTempUser();
		$ip = '1.2.3.4';
		RequestContext::getMain()->getRequest()->setIP( $ip );
		$testUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )->getUser();
		$this->editPage( 'Target1', 'test', "", NS_MAIN, $testUser );
		$this->editPage( 'Target2', 'test', "", NS_MAIN, $testUser );

		$adminUser = $this->getTestSysop()->getUser();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		$permissionManager->overrideUserRightsForTesting( $adminUser,
			array_merge(
				$permissionManager->getUserPermissions( $adminUser ),
				[ 'checkuser-temporary-account-no-preference' ]
			) );
		$request = new FauxRequest( [
			'target' => $ip,
			'action' => 'submit',
		], true );
		$adminPerformer = new UltimateAuthority( $adminUser );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );

		$usernameCount = substr_count( $html, $ip );
		$this->assertStringContainsString( 1, $usernameCount );

		$this->assertStringContainsString( '(nuke-list-tempaccount:', $html );
		$this->assertStringContainsString( 'Target1', $html );
		$this->assertStringContainsString( 'Target2', $html );
	}

	/**
	 * Ensure that the prompt returns temp accounts and IP accounts from IP lookups when
	 * temp accounts and CheckUser are enabled and Anonymous IP accounts exist
	 *
	 * @return void
	 */
	public function testPromptTargetCheckUserMixed() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$this->disableAutoCreateTempUser( [ 'known' => false ] );
		$ip = '1.2.3.4';
		$testUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( $ip );
		$performer = new UltimateAuthority( $testUser );

		// create a page as an anonymous IP user
		$this->editPage( 'Target1', 'test', "", NS_MAIN, $performer );

		$this->enableAutoCreateTempUser();
		RequestContext::getMain()->getRequest()->setIP( $ip );
		$testUser = $this->getServiceContainer()->getTempUserCreator()
										  ->create( null, new FauxRequest() )->getUser();
		$performer = new UltimateAuthority( $testUser );

		// create a page as a temp user
		$this->editPage( 'Target2', 'test', "", NS_MAIN, $performer );

		$adminUser = $this->getTestSysop()->getUser();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		$permissionManager->overrideUserRightsForTesting( $adminUser,
			array_merge(
				$permissionManager->getUserPermissions( $adminUser ),
				[ 'checkuser-temporary-account-no-preference' ]
			) );
		$request = new FauxRequest( [
			'target' => $ip,
			'action' => 'submit',
		], true );
		$adminPerformer = new UltimateAuthority( $adminUser );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );

		$usernameCount = substr_count( $html, $ip );
		$this->assertStringContainsString( 1, $usernameCount );

		// They should all show up together
		$this->assertStringContainsString( '(nuke-list-tempaccount:', $html );
		$this->assertStringContainsString( 'Target1', $html );
		$this->assertStringContainsString( 'Target2', $html );
	}

	public function testListNoPagesGlobal() {
		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'submit',
			'pattern' => 'ThisPageShouldNotExist-' . rand()
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertStringContainsString( '(nuke-nopages-global)', $html );
		$this->assertStringNotContainsString( '(nuke-nopages)', $html );
	}

	public function testListNoPagesUser() {
		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'submit',
			'target' => 'ThisPageShouldNotExist-' . rand()
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertStringNotContainsString( '(nuke-nopages-global)', $html );
		$this->assertStringContainsString( 'nuke-nopages', $html );
	}

	public function testListNamespace() {
		$this->editPage( 'NukeUserPageTarget', 'test', '', NS_USER );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'submit',
			'pattern' => 'NukeUserPageTarget',
			'namespace' => NS_USER
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$expectedTitle = Title::makeTitle( NS_USER, 'NukeUserPageTarget' )
			->getPrefixedText();
		$this->assertStringContainsString( $expectedTitle, $html );
	}

	public function testListTalk() {
		$this->editPage( 'NukeTalkPageTarget', 'test', '', NS_TALK );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'submit',
			'pattern' => 'NukeTalkPageTarget'
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$expectedTitle = Title::makeTitle( NS_TALK, 'NukeTalkPageTarget' )
			->getPrefixedText();
		$this->assertStringContainsString( $expectedTitle, $html );
	}

	public function testListCapitalizedNamespace() {
		$this->overrideConfigValues( [
			'CapitalLinks' => false,
			'CapitalLinkOverrides' => []
		] );
		$this->editPage( 'uncapsTarget', 'test' );
		$this->editPage( 'UncapsTarget', 'test' );

		$admin = $this->getTestSysop()->getUser();

		$expectedTitle = Title::makeTitle( NS_MAIN, 'uncapsTarget' )
			->getPrefixedText();

		$shouldMatch = [
			"%ncapsTarget",
			"u%",
			"uncapsTarget"
		];
		$shouldNotMatch = [
			"UncapsTarget"
		];

		foreach ( $shouldMatch as $match ) {
			$request = new FauxRequest( [
				'action' => 'submit',
				'pattern' => 'uncapsTarget'
			], true );
			$performer = new UltimateAuthority( $admin );
			[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

			$this->assertStringContainsString( $expectedTitle, $html, "match: $match" );
			foreach ( $shouldNotMatch as $noMatch ) {
				$this->assertStringNotContainsString( $noMatch, $html );
			}
		}
	}

	public function testListCapitalizedNamespaceOverrides() {
		$overriddenNamespaces = [
			NS_PROJECT => true,
			NS_MEDIAWIKI => true,
			NS_USER => true,
		];
		$this->overrideConfigValues( [
			'CapitalLinks' => false,
			'CapitalLinkOverrides' => $overriddenNamespaces
		] );
		$overriddenNamespaces[ NS_MAIN ] = false;
		foreach ( $overriddenNamespaces as $ns => $override ) {
			$this->editPage( "UncapsTarget" . $ns, 'test', '', $ns );
			// If capital links for this ns is `true`, then the existing page should be edited.
			$this->editPage( "uncapsTarget" . $ns, 'test2', '', $ns );
		}

		$admin = $this->getTestSysop()->getUser();

		$request = new FauxRequest( [
			'action' => 'submit',
			'pattern' => 'u%'
		], true );
		$performer = new UltimateAuthority( $admin );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertStringContainsString( "uncapsTarget" . NS_MAIN, $html );
		// Check that the overridden namespaces are included in the search
		$this->assertStringContainsString( "UncapsTarget" . NS_PROJECT, $html );
		$this->assertStringContainsString( "UncapsTarget" . NS_MEDIAWIKI, $html );
		$this->assertStringContainsString( "UncapsTarget" . NS_USER, $html );

		$this->assertStringNotContainsString( "UncapsTarget" . NS_MAIN, $html );
		// Check that the non-overridden namespaces are not included in the search
		$this->assertStringNotContainsString( "uncapsTarget" . NS_PROJECT, $html );
		$this->assertStringNotContainsString( "uncapsTarget" . NS_MEDIAWIKI, $html );
		$this->assertStringNotContainsString( "uncapsTarget" . NS_USER, $html );
	}

	/**
	 * Check if Nuke still works with languages with complicated capitalization
	 * or no capitalization.
	 *
	 * Since the first letter of each title should be capitalized, searching for the
	 * lowercase version should still yield the uppercase version, or the same character
	 * if casing does not exist for the language.
	 *
	 * @return void
	 */
	public function testListCapitalized() {
		/** @noinspection SpellCheckingInspection */
		$testedLanguages = [
			"ee" => [
				"Ülemiste",
				"ülemiste"
			],
			"zh" => "你好",
			"ja" => "にほんご",
		];

		foreach ( $testedLanguages as $lang => $testData ) {
			[ $created, $wanted ] = is_array( $testData ) ?
				$testData : [ $testData, $testData ];

			$this->overrideConfigValue( 'LanguageCode', $lang );
			$this->editPage( $created, 'test' );

			$admin = $this->getTestSysop()->getUser();
			$request = new FauxRequest( [
				'action' => 'submit',
				'pattern' => $wanted
			], true );
			$performer = new UltimateAuthority( $admin );

			[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

			$this->assertStringContainsString( $created, $html );
		}
	}

	public function testListLimit() {
		$this->editPage( 'Page1', 'test' );
		$this->editPage( 'Page2', 'test' );
		$this->editPage( 'Page3', 'test' );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'submit',
			'pattern' => 'Page%',
			'limit' => 2
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertEquals( 2, substr_count( $html, '<li>' ) );
	}

	public function testListLimitWithHooks() {
		$this->editPage( 'Page1', 'test' );
		$this->editPage( 'Page2', 'test' );
		$this->editPage( 'Page3', 'test' );
		$this->setTemporaryHook( "NukeGetNewPages", static function ( $_1, $_2, $_3, $_4, &$pages ) {
			$pages[] = "Page3";

			return true;
		} );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'submit',
			'pattern' => 'Page%',
			'limit' => 2
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertEquals( 2, substr_count( $html, '<li>' ) );
	}

	public function testExecutePattern() {
		// Test that matching wildcards works, and that escaping wildcards works as documented
		// at https://www.mediawiki.org/wiki/Help:Extension:Nuke
		$this->editPage( '%PositiveNukeTest123', 'test' );
		$this->editPage( 'NegativeNukeTest123', 'test' );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'submit',
			'pattern' => '\\%PositiveNukeTest%',
			'wpFormIdentifier' => 'massdelete',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertStringContainsString( 'PositiveNukeTest123', $html );
		$this->assertStringNotContainsString( 'NegativeNukeTest123', $html );
	}

	public function testListFiles() {
		$this->uploadTestFile();

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'submit',
			'namespace' => NS_FILE
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$expectedTitle = Title::makeTitle( NS_FILE, 'Example.png' );

		// The title should be in the list
		$this->assertStringContainsString( $expectedTitle->getPrefixedText(), $html );
		// There should also be an image preview
		$this->assertStringContainsString( "<img src", $html );
	}

	public function testUserPages() {
		$user = $this->getTestUser()->getUser();
		$this->insertPage( 'Page123', 'Test', NS_MAIN, $user );
		$this->insertPage( 'Paging456', 'Test', NS_MAIN, $user );
		$this->insertPage( 'Should not show', 'No show' );

		$admin = $this->getTestSysop()->getUser();

		$request = new FauxRequest( [
			'action' => 'submit',
			'target' => $user->getName(),
			'wpFormIdentifier' => 'massdelete',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertStringContainsString( 'Page123', $html );
		$this->assertStringContainsString( 'Paging456', $html );
		$this->assertStringNotContainsString( 'Should not show', $html );
	}

	public function testNamespaces() {
		$this->insertPage( 'Page123', 'Test', NS_MAIN );
		$this->insertPage( 'Paging456', 'Test', NS_MAIN );
		$this->insertPage( 'Should not show', 'No show', NS_TALK );

		$admin = $this->getTestSysop()->getUser();

		$request = new FauxRequest( [
			'action' => 'submit',
			'namespace' => NS_MAIN,
			'wpFormIdentifier' => 'massdelete',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertStringContainsString( 'Page123', $html );
		$this->assertStringContainsString( 'Paging456', $html );
		$this->assertStringNotContainsString( 'Should not show', $html );
	}

	public function testDelete() {
		$pages = [];
		$pages[] = $this->insertPage( 'Page123', 'Test', NS_MAIN )[ 'title' ];
		$pages[] = $this->insertPage( 'Paging456', 'Test', NS_MAIN )[ 'title' ];

		$admin = $this->getTestSysop()->getUser();

		$fauxReason = "Reason " . wfRandomString();
		$request = new FauxRequest( [
			'action' => 'delete',
			'wpDeleteReasonList' => 'Vandalism',
			'wpReason' => $fauxReason,
			'pages' => $pages,
			'wpFormIdentifier' => 'nukelist',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertStringContainsString( '(nuke-deletion-queued: Page123)', $html );
		$this->assertStringContainsString( '(nuke-deletion-queued: Paging456)', $html );
		$this->assertStringNotContainsString( 'Nuke', $this->getDeleteLogHtml() );
		// Ensure all jobs are run
		$this->runJobs();

		$this->assertStringContainsString( 'Vandalism', $this->getDeleteLogHtml() );
		$this->assertStringContainsString( 'Nuke', $this->getDeleteLogHtml() );
		$this->assertStringContainsString( $fauxReason, $this->getDeleteLogHtml() );
	}

	public function testDeleteDropdownReason() {
		$pages = [];
		$pages[] = $this->insertPage( 'Page123', 'Test', NS_MAIN )[ 'title' ];
		$pages[] = $this->insertPage( 'Paging456', 'Test', NS_MAIN )[ 'title' ];

		$admin = $this->getTestSysop()->getUser();

		$request = new FauxRequest( [
			'action' => 'delete',
			'wpDeleteReasonList' => 'Vandalism',
			'wpReason' => "",
			'pages' => $pages,
			'wpFormIdentifier' => 'nukelist',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		$this->executeSpecialPage( '', $request, 'qqx', $performer );

		// Ensure all jobs are run
		$this->runJobs();

		// Check logs
		$this->assertStringContainsString( "Vandalism", $this->getDeleteLogHtml() );
	}

	public function testDeleteCustomReason() {
		$pages = [];
		$pages[] = $this->insertPage( 'Page123', 'Test', NS_MAIN )[ 'title' ];
		$pages[] = $this->insertPage( 'Paging456', 'Test', NS_MAIN )[ 'title' ];

		$admin = $this->getTestSysop()->getUser();

		$fauxReason = "Reason " . wfRandomString();
		$request = new FauxRequest( [
			'action' => 'delete',
			'wpDeleteReasonList' => 'other',
			'wpReason' => $fauxReason,
			'pages' => $pages,
			'wpFormIdentifier' => 'nukelist',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		$this->executeSpecialPage( '', $request, 'qqx', $performer );

		// Ensure all jobs are run
		$this->runJobs();

		// Check logs
		$this->assertStringContainsString( $fauxReason, $this->getDeleteLogHtml() );
	}

	public function testDeleteFiles() {
		$this->uploadTestFile();

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'delete',
			'wpDeleteReasonList' => 'Reason',
			'wpReason' => 'Reason',
			'pages' => [ 'File:Example.png' ],
			'wpFormIdentifier' => 'nukelist',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$expectedTitle = Title::makeTitle( NS_FILE, 'Example.png' );

		// Files are deleted instantly
		$this->assertStringContainsString( "nuke-deleted", $html );
		$this->assertStringContainsString( $expectedTitle->getPrefixedText(), $html );
	}

	public function testDeleteHook() {
		$wasCalled = false;
		$pages = [];
		$pages[] = $this->insertPage( 'Bad article', 'test', NS_MAIN )['title'];
		$pages[] = $this->insertPage( 'DO NOT DELETE', 'test', NS_MAIN )['title'];
		$pages[] = $this->insertPage( 'Bad article 2', 'test', NS_MAIN )['title'];
		$this->setTemporaryHook(
			"NukeDeletePage",
			static function ( Title $title, $reason, &$deletionResult ) use ( &$wasCalled ) {
				$wasCalled = true;
				$deletionResult = $title->getPrefixedText() !== "DO NOT DELETE";
				return $title->getPrefixedText() === "Bad article 2";
			}
		);

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'delete',
			'wpDeleteReasonList' => 'Reason',
			'wpReason' => 'Reason',
			'pages' => $pages,
			'wpFormIdentifier' => 'nukelist',
			'wpEditToken' => $admin->getRequest()->getSession()->getToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertTrue( $wasCalled );
		$this->assertStringContainsString( '(nuke-deleted: Bad article)', $html );
		$this->assertStringContainsString( '(nuke-not-deleted: DO NOT DELETE)', $html );
		$this->assertStringContainsString( '(nuke-deletion-queued: Bad article 2)', $html );
	}

	public function testSearchSubpages() {
		$user = $this->getTestUser()->getUser();
		$username = $user->getName();
		$truncatedUsername = substr( $username, 0, 2 );

		$searchResults1 = $this->newSpecialPage()
			->prefixSearchSubpages( $truncatedUsername, 10, 0 );

		$this->assertArrayContains( [ $username ], $searchResults1 );

		$searchResults2 = $this->newSpecialPage()
			->prefixSearchSubpages( "", 10, 0 );

		$this->assertCount( 0, $searchResults2 );
	}

	private function uploadTestFile() {
		$exampleFilePath = realpath( __DIR__ . "/../../assets/Example.png" );
		$tempFilePath = $this->getNewTempFile();
		copy( $exampleFilePath, $tempFilePath );

		$request = new FauxRequest( [], true );
		$request->setUpload( 'wpUploadFile', [
			'name' => 'Example.png',
			'type' => 'image/png',
			'tmp_name' => $tempFilePath,
			'size' => filesize( $tempFilePath ),
			'error' => UPLOAD_ERR_OK
		] );
		$upload = UploadFromFile::createFromRequest( $request );
		$upload->performUpload(
			"test",
			false,
			false,
			$this->getTestUser( "user" )->getUser()
		);
	}

	private function getDeleteLogHtml(): string {
		$services = $this->getServiceContainer();
		$specialLog = $services->getSpecialPageFactory()->getPage( 'Log' );
		$specialLog->execute( "delete" );
		return $specialLog->getOutput()->getHTML();
	}

}
