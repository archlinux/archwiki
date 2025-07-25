<?php

namespace MediaWiki\Extension\Nuke\Test\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\Nuke\SpecialNuke;
use MediaWiki\Extension\Nuke\Test\NukeIntegrationTest;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use SpecialPageTestBase;
use Wikimedia\IPUtils;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Nuke\SpecialNuke
 * @covers \MediaWiki\Extension\Nuke\NukeQueryBuilder
 * @covers \MediaWiki\Extension\Nuke\Form\SpecialNukeUIRenderer
 * @covers \MediaWiki\Extension\Nuke\Form\SpecialNukeHTMLFormUIRenderer
 * @covers \MediaWiki\Extension\Nuke\Form\HTMLForm\NukeDateTimeField
 * @covers \MediaWiki\Extension\Nuke\NukeContext
 */
class SpecialNukeHTMLFormTest extends SpecialPageTestBase {

	use NukeIntegrationTest;
	use TempUserTestTrait;

	protected function newSpecialPage(): SpecialNuke {
		$services = $this->getServiceContainer();

		return new SpecialNuke(
			$services->getJobQueueGroup(),
			$services->getDBLoadBalancerFactory(),
			$services->getPermissionManager(),
			$services->getRepoGroup(),
			$services->getUserOptionsLookup(),
			$services->getUserNamePrefixSearch(),
			$services->getUserNameUtils(),
			$services->getNamespaceInfo(),
			$services->getContentLanguage(),
			$services->getRedirectLookup(),
			$services->getService( 'NukeIPLookup' ),
		);
	}

	/**
	 * Ensure that the prompt appears as expected for users with nuke permission.
	 *
	 * @return void
	 */
	public function testPrompt() {
		$admin = $this->getTestSysop()->getUser();
		$this->disableAutoCreateTempUser();
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', null, 'qqx', $performer );

		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( '(nuke-summary)', $html );
		$this->assertStringContainsString( '(nuke-tools)', $html );
		$this->assertStringContainsString( '(nuke-tools-prompt)', $html );
		$this->assertStringNotContainsString( '(nuke-tools-prompt-restricted)', $html );
		$this->assertStringContainsString( 'nuke-submit-list', $html );
		$this->assertStringNotContainsString( 'nuke-submit-continue', $html );
	}

	/**
	 * Ensure that the prompt appears as expected for users without nuke permission.
	 *
	 * @return void
	 */
	public function testPromptNoPermission() {
		$user = $this->getTestUser()->getUser();
		$this->disableAutoCreateTempUser();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		$permissions = $permissionManager->getUserPermissions( $user );
		$permissions = array_diff( $permissions, [ 'nuke' ] );
		$permissionManager->overrideUserRightsForTesting( $user,
			$permissions
		);
		$performer = new UltimateAuthority( $user );

		[ $html ] = $this->executeSpecialPage( '', null, 'qqx', $performer );

		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( '(nuke-summary)', $html );
		$this->assertStringContainsString( '(nuke-tools)', $html );
		$this->assertStringNotContainsString( '(nuke-tools-prompt)', $html );
		$this->assertStringContainsString( '(nuke-tools-prompt-restricted)', $html );
		$this->assertStringNotContainsString( '(nuke-tools-notice-blocked)', $html );
		$this->assertStringContainsString( '(nuke-tools-notice-noperm)', $html );
		$this->assertStringContainsString( 'nuke-submit-list', $html );
		$this->assertStringNotContainsString( 'nuke-submit-continue', $html );
		$this->assertStringContainsString( '(nuke-minsize)', $html );
		$this->assertStringContainsString( '(nuke-maxsize)', $html );
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

		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( '(nuke-summary)', $html );
		$this->assertStringContainsString( '(nuke-tools-tempaccount)', $html );
	}

	/**
	 * Ensure that the prompt appears as expected for a blocked sysop
	 *
	 * @return void
	 */
	public function testPromptBlocked() {
		$user = $this->getTestSysop()->getUser();
		$performer = new UltimateAuthority( $user );

		// Self-blocks should still prevent the form from being shown
		$this->getServiceContainer()
			->getBlockUserFactory()
			->newBlockUser( $user, $performer, 'infinity', 'SpecialNukeTest::testBlocked' )
			->placeBlockUnsafe();

		[ $html ] = $this->executeSpecialPage( '', null, 'qqx', $performer );

		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( '(nuke-summary)', $html );
		$this->assertStringNotContainsString( '(nuke-tools-prompt)', $html );
		$this->assertStringContainsString( '(nuke-tools-prompt-restricted)', $html );
		$this->assertStringContainsString( '(nuke-tools-notice-blocked)', $html );
		$this->assertStringNotContainsString( '(nuke-tools-notice-noperm)', $html );
		$this->assertStringContainsString( 'nuke-submit-list', $html );
		$this->assertStringNotContainsString( 'nuke-submit-continue', $html );

		// regression check: now list the pages and confirm the same message is shown at the top
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user->getName()
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertStringContainsString( '(nuke-summary)', $html );
		$this->assertStringNotContainsString( '(nuke-tools-prompt)', $html );
		$this->assertStringContainsString( '(nuke-tools-prompt-restricted)', $html );
		$this->assertStringContainsString( '(nuke-tools-notice-blocked)', $html );
		$this->assertStringNotContainsString( '(nuke-tools-notice-noperm)', $html );
		$this->assertStringContainsString( 'nuke-submit-list', $html );
		$this->assertStringNotContainsString( 'nuke-submit-continue', $html );

		$this->getServiceContainer()
			->getUnblockUserFactory()
			->newUnblockUser( $user, $performer, 'SpecialNukeTest::testBlocked' )
			->unblockUnsafe();
	}

	/**
	 * Ensure that the prompt prevents a nuke user without the checkuser-temporary-account permission
	 * from performing CheckUser IP lookups
	 *
	 * @return void
	 */
	public function testListCheckUserNoPermission() {
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
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $ip,
		], true );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
	}

	/**
	 * Ensure that the prompt prevents a nuke user who hasn't accepted the agreement
	 * from performing CheckUser IP lookups
	 *
	 * @return void
	 */
	public function testListCheckUserNoAgreementAccepted() {
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
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $ip,
		], true );
		$adminPerformer = new UltimateAuthority( $adminUser );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );
	}

	/**
	 * Ensure that pages created by a specific user are shown accordingly
	 *
	 * @return void
	 */
	public function testListTarget() {
		$user = $this->getTestUser()->getUser();

		$this->insertPage( 'Target1', 'test', NS_MAIN, $user );
		$this->insertPage( 'Target2', 'test', NS_MAIN, $user );
		$this->insertPage( 'Should not show', 'No show' );

		$adminUser = $this->getTestSysop()->getUser();
		$adminPerformer = new UltimateAuthority( $adminUser );
		$request1 = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user->getName()
		] );

		[ $html1 ] = $this->executeSpecialPage( '', $request1, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html1 );
		$this->assertStringContainsString( 'nuke-submit-list', $html1 );
		$this->assertStringContainsString( 'nuke-submit-continue', $html1 );

		$this->assertStringContainsString( 'Target1', $html1 );
		$this->assertStringContainsString( 'Target2', $html1 );
		$this->assertStringNotContainsString( 'Should not show', $html1 );

		// Not supplying an action should imply 'list'.
		$request2 = new FauxRequest( [
			'target' => $user->getName()
		] );

		[ $html2 ] = $this->executeSpecialPage( '', $request2, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html2 );
		$this->assertStringContainsString( 'nuke-submit-list', $html2 );
		$this->assertStringContainsString( 'nuke-submit-continue', $html2 );

		$this->assertStringContainsString( 'Target1', $html2 );
		$this->assertStringContainsString( 'Target2', $html2 );
		$this->assertStringNotContainsString( 'Should not show', $html2 );
	}

	/**
	 * Ensure that the prompt works with anon IP searches when temp accounts are disabled
	 *
	 * @return void
	 */
	public function testListTargetAnonUser() {
		$this->disableAutoCreateTempUser( [ 'known' => false ] );
		$ip = '127.0.0.1';
		$testUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( $ip );
		$performer = new UltimateAuthority( $testUser );

		$this->editPage( 'Target1', 'test', "", NS_MAIN, $performer );
		$this->editPage( 'Target2', 'test', "", NS_MAIN, $performer );

		$adminUser = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName()
		] );
		$adminPerformer = new UltimateAuthority( $adminUser );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		$this->assertStringContainsString( 'Target1', $html );
		$this->assertStringContainsString( 'Target2', $html );

		$this->assertEquals( 2, substr_count( $html, '(nuke-editby: 127.0.0.1)' ) );
	}

	public static function provideListTargetNormalizeUser() {
		yield 'normalize IPv4' => [ '001.002.003.004', '1.2.3.4' ];

		$ip = '2001:0db8::ff00:0042:8329';
		yield 'normalize IPv6' => [ $ip, IPUtils::sanitizeIP( $ip ) ];
	}

	/**
	 * Ensure that the prompt works with searches for non-canonical user names, like lowercase IPv6
	 * @dataProvider provideListTargetNormalizeUser
	 */
	public function testListTargetNormalizeUser( $target, $normalized ) {
		$this->disableAutoCreateTempUser( [ 'known' => false ] );
		$testUser = $this->getServiceContainer()->getUserFactory()->newAnonymous( $normalized );
		$performer = new UltimateAuthority( $testUser );

		$this->editPage( 'Target1', 'test', "", NS_MAIN, $performer );
		$this->editPage( 'Target2', 'test', "", NS_MAIN, $performer );

		$adminUser = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $target
		] );
		$adminPerformer = new UltimateAuthority( $adminUser );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		$this->assertStringContainsString( 'Target1', $html );
		$this->assertStringContainsString( 'Target2', $html );

		$this->assertEquals( 2, substr_count( $html, "(nuke-editby: $normalized)" ) );
	}

	/**
	 * Ensure that the prompt returns temp accounts from IP lookups when temp accounts and
	 * CheckUser are enabled
	 *
	 * @return void
	 */
	public function testListTargetCheckUser() {
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
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $ip,
		], true );
		$adminPerformer = new UltimateAuthority( $adminUser );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		$year = gmdate( 'Y' );
		$this->assertEquals( 2, substr_count( $html, "(nuke-editby: ~$year-1)" ) );

		$this->assertStringContainsString( 'Target1', $html );
		$this->assertStringContainsString( 'Target2', $html );
	}

	/**
	 * Ensure that the prompt returns temp accounts and IP accounts from IP lookups when
	 * temp accounts and CheckUser are enabled and Anonymous IP accounts exist
	 *
	 * @return void
	 */
	public function testListTargetCheckUserMixed() {
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
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $ip,
		], true );
		$adminPerformer = new UltimateAuthority( $adminUser );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		$year = gmdate( 'Y' );
		$this->assertSame( 1, substr_count( $html, "(nuke-editby: ~$year-1)" ) );
		$this->assertSame( 1, substr_count( $html, ' (nuke-editby: 1.2.3.4)' ) );

		// They should all show up together
		$this->assertStringContainsString( 'Target1', $html );
		$this->assertStringContainsString( 'Target2', $html );
	}

	/**
	 * Ensure that matching wildcards works, and that escaping wildcards works as documented at
	 * https://www.mediawiki.org/wiki/Help:Extension:Nuke
	 *
	 * @return void
	 */
	public function testListPattern() {
		$this->editPage( '%PositiveNukeTest123', 'test' );
		$this->editPage( 'NegativeNukeTest123', 'test' );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => '\\%PositiveNukeTest%'
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		$this->assertStringContainsString( 'PositiveNukeTest123', $html );
		$this->assertStringNotContainsString( 'NegativeNukeTest123', $html );
	}

	public function testListNamespaces() {
		$this->insertPage( 'Page123', 'Test', NS_MAIN );
		$this->insertPage( 'Paging456', 'Test', NS_MAIN );
		$this->insertPage( 'Should not show', 'No show', NS_TALK );

		$admin = $this->getTestSysop()->getUser();

		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'namespace' => NS_MAIN,
			'wpFormIdentifier' => 'massdelete'
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		$this->assertStringContainsString( 'Page123', $html );
		$this->assertStringContainsString( 'Paging456', $html );
		$this->assertStringNotContainsString( 'Should not show', $html );
	}

	public function testListNoPagesGlobal() {
		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'ThisPageShouldNotExist-' . rand()
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html, [ 'nuke-nopages-global' ] );
		$this->assertStringContainsString( 'nuke-submit-list', $html );
		$this->assertStringNotContainsString( 'nuke-submit-continue', $html );
	}

	public function testListNoPagesUser() {
		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => 'ThisPageShouldNotExist-' . rand()
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html, [ 'nuke-nopages-global' ] );
		$this->assertStringContainsString( 'nuke-submit-list', $html );
		$this->assertStringNotContainsString( 'nuke-submit-continue', $html );
	}

	public function testListNamespace() {
		$this->editPage( 'NukeUserPageTarget', 'test', '', NS_USER );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'NukeUserPageTarget',
			'namespace' => NS_USER
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		$expectedTitle = Title::makeTitle( NS_USER, 'NukeUserPageTarget' )
			->getPrefixedText();
		$this->assertStringContainsString( $expectedTitle, $html );
	}

	public function testListTalk() {
		$this->editPage( 'NukeTalkPageTarget', 'test', '', NS_TALK );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'NukeTalkPageTarget'
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		$expectedTitle = Title::makeTitle( NS_TALK, 'NukeTalkPageTarget' )
			->getPrefixedText();
		$this->assertStringContainsString( $expectedTitle, $html );
	}

	public function testListNamespaceMultiple() {
		$this->editPage( 'NukeMainPageTarget', 'test' );
		$this->editPage( 'NukeProjectPageTarget', 'test', '', NS_PROJECT );
		$this->editPage( 'NukeUserPageTarget', 'test', '', NS_USER );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'Nuke%PageTarget',
			'namespace' => implode( "\n", [ NS_MAIN, NS_USER ] )
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		$this->assertStringContainsString(
			Title::makeTitle( NS_MAIN, 'NukeMainPageTarget' )->getPrefixedText(),
			$html
		);
		$this->assertStringContainsString(
			Title::makeTitle( NS_USER, 'NukeUserPageTarget' )->getPrefixedText(),
			$html
		);
		$this->assertStringNotContainsString(
			Title::makeTitle( NS_PROJECT, 'NukNukeProjectPageTarget' )->getPrefixedText(),
			$html
		);
	}

	public function testListNamespaceMultipleEdge() {
		$page1 = $this->insertPage( 'NukeMainPageTarget', 'test' )['title'];
		$page2 = $this->insertPage( 'NukeProjectPageTarget', 'test', NS_PROJECT )['title'];
		$page3 = $this->insertPage( 'NukeUserPageTarget', 'test', NS_USER )['title'];

		$admin = $this->getTestSysop()->getUser();
		$performer = new UltimateAuthority( $admin );

		// Input includes empty line
		$request1 = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'Nuke%PageTarget',
			'namespace' => NS_PROJECT . "\n"
		], true );
		[ $html1 ] = $this->executeSpecialPage( '', $request1, 'qqx', $performer );
		$this->checkForValidationMessages( $html1 );
		$this->assertStringNotContainsString( $page1, $html1 );
		$this->assertStringContainsString( $page2, $html1 );
		$this->assertStringNotContainsString( $page3, $html1 );

		// Input includes invalid namespace ID
		$request1 = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'Nuke%PageTarget',
			'namespace' => NS_PROJECT . "\n99999999"
		], true );
		[ $html1 ] = $this->executeSpecialPage( '', $request1, 'qqx', $performer );
		$this->checkForValidationMessages( $html1 );
		$this->assertStringNotContainsString( $page1, $html1 );
		$this->assertStringContainsString( $page2, $html1 );
		$this->assertStringNotContainsString( $page3, $html1 );

		// Input includes a string
		$request1 = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'Nuke%PageTarget',
			'namespace' => NS_PROJECT . "\nUser"
		], true );
		[ $html1 ] = $this->executeSpecialPage( '', $request1, 'qqx', $performer );
		$this->checkForValidationMessages( $html1 );
		$this->assertStringNotContainsString( $page1, $html1 );
		$this->assertStringContainsString( $page2, $html1 );
		$this->assertStringNotContainsString( $page3, $html1 );

		// Input is entirely invalid
		$request1 = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'Nuke%PageTarget',
			'namespace' => "Project\nUser"
		], true );
		[ $html1 ] = $this->executeSpecialPage( '', $request1, 'qqx', $performer );
		$this->checkForValidationMessages( $html1 );
		// Pages from all namespaces will be returned
		$this->assertStringContainsString( $page1, $html1 );
		$this->assertStringContainsString( $page2, $html1 );
		$this->assertStringContainsString( $page3, $html1 );
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
				'action' => SpecialNuke::ACTION_LIST,
				'pattern' => 'uncapsTarget'
			], true );
			$performer = new UltimateAuthority( $admin );
			[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
			$this->checkForValidationMessages( $html );

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
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'u%'
		], true );
		$performer = new UltimateAuthority( $admin );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

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

	public function testListCapitalizedNamespaceMultiple() {
		// The goal of this test is to:
		// - Ensure that for case-sensitive namespaces, the search remains case-sensitive.
		// - Ensure that for non-case-sensitive namespaces, the search capitalizes the condition accordingly.
		// Here, `NS_MAIN` remains case-insensitive, but other namespaces will be case-sensitive.
		$this->overrideConfigValues( [
			'CapitalLinks' => false,
			'CapitalLinkOverrides' => [
				NS_MAIN => true
			]
		] );

		$this->editPage( 'UncapsTargetMain', 'test' );
		$this->editPage( 'UncapsTarget', 'test', '', NS_PROJECT );
		$this->editPage( 'uncapsTarget', 'test', '', NS_PROJECT );
		$this->editPage( 'UncapsTarget', 'test', '', NS_HELP );
		$this->editPage( 'uncapsTarget', 'test', '', NS_HELP );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'uncapsTarget%',
			'namespace' => implode( "\n", [ NS_MAIN, NS_HELP ] )
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		$this->assertStringContainsString(
			Title::makeTitle( NS_MAIN, 'UncapsTargetMain' )->getPrefixedText(),
			$html
		);
		$this->assertStringContainsString(
			Title::makeTitle( NS_HELP, 'uncapsTarget' )->getPrefixedText(),
			$html
		);

		$this->assertStringNotContainsString(
			Title::makeTitle( NS_PROJECT, 'UncapsTarget' )->getPrefixedText(),
			$html
		);

		$this->assertStringNotContainsString(
			Title::makeTitle( NS_PROJECT, 'uncapsTarget' )->getPrefixedText(),
			$html
		);

		$this->assertStringNotContainsString(
			Title::makeTitle( NS_HELP, 'UncapsTarget' )->getPrefixedText(),
			$html
		);
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
				'action' => SpecialNuke::ACTION_LIST,
				'pattern' => $wanted
			], true );
			$performer = new UltimateAuthority( $admin );

			[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
			$this->checkForValidationMessages( $html );

			$this->assertStringContainsString( $created, $html );
		}
	}

	public function testListLimit() {
		$this->editPage( 'Page1', 'test' );
		$this->editPage( 'Page2', 'test' );
		$this->editPage( 'Page3', 'test' );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'Page%',
			'limit' => 2
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		// NOTE: No 'nuke-associated-limited' message box will be shown here, because the limit is
		// enforced on the initial 'pages' query and no associated pages are selected.
		$this->checkForValidationMessages( $html );

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
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'Page%',
			'limit' => 2
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		$this->assertEquals( 2, substr_count( $html, '<li>' ) );
	}

	public function testListMaxAge() {
		$time = time();

		$creator = $this->getTestUser()->getAuthority();

		// A page that's always older than $wgNukeMaxAge and $wgRCMaxAge.
		// This should never show up; if it does, the max age isn't being applied at all.
		$this->editPageAtTime(
			'Page1',
			'test',
			'',
			$time - ( 86400 * 7 ), NS_MAIN,
			$creator
		);
		// A page that's four days old.
		$this->editPageAtTime(
			'Page2',
			'test',
			'',
			$time - ( 86400 * 4 ),
			NS_MAIN,
			$creator
		);
		// A page that's two days old.
		$this->editPageAtTime(
			'Page3',
			'test',
			'',
			$time - ( 86400 * 2 ),
			NS_MAIN,
			$creator
		);
		// A page that was just made.
		$this->editPage( 'Page4', 'test', '', NS_MAIN, $creator );

		$this->overrideConfigValues( [
			'NukeMaxAge' => 86400 * 5,
			'RCMaxAge' => 86400 * 3
		] );
		$this->rebuildRecentChanges( $time - ( 86400 * 3 ) - 60, $time + 60 );

		$admin = $this->getTestSysop()->getUser();
		$performer = new UltimateAuthority( $admin );

		// == Pattern-only (recentchanges) searches ==

		// Request with a pattern but no actor.
		// This should use the recentchanges table exclusively.
		// Page3 and Page4 should be present.
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'Page%'
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( 'nuke-daterange-helper-text-max-age-different', $html );
		$this->assertStringNotContainsString( 'nuke-daterange-helper-text-max-age-same', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );
		$this->assertStringContainsString( 'Page4', $html );

		// Changing NukeMaxAge to be way larger than RCMaxAge shouldn't affect that query.
		$this->overrideConfigValues( [
			'NukeMaxAge' => 86400 * 6,
			'RCMaxAge' => 86400
		] );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( 'nuke-daterange-helper-text-max-age-different', $html );
		$this->assertStringNotContainsString( 'nuke-daterange-helper-text-max-age-same', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );
		$this->assertStringContainsString( 'Page4', $html );

		// Changing NukeMaxAge to be smaller than RCMaxAge should limit to just those pages.
		// We don't want to provide pages larger than the scope of NukeMaxAge just because
		// we're using the recentchanges table.
		$this->overrideConfigValues( [
			'NukeMaxAge' => 600,
			'RCMaxAge' => 86400
		] );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( 'nuke-daterange-helper-text-max-age-different', $html );
		$this->assertStringNotContainsString( 'nuke-daterange-helper-text-max-age-same', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringNotContainsString( 'Page3', $html );
		$this->assertStringContainsString( 'Page4', $html );

		// Changing NukeMaxAge to 0 should make it fall back to RCMaxAge.
		$this->overrideConfigValues( [
			'NukeMaxAge' => 0,
			'RCMaxAge' => 86400 * 3
		] );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( 'nuke-daterange-helper-text-max-age-different', $html );
		$this->assertStringNotContainsString( 'nuke-daterange-helper-text-max-age-same', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );
		$this->assertStringContainsString( 'Page4', $html );

		// == With-actor (revision) searches ==
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $creator->getUser()->getName(),
			'pattern' => 'Page%'
		], true );

		// NukeMaxAge falls back to RCMaxAge.
		$this->overrideConfigValues( [
			'NukeMaxAge' => 0,
			'RCMaxAge' => 86400 * 3
		] );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( 'nuke-daterange-helper-text-max-age-different', $html );
		$this->assertStringNotContainsString( 'nuke-daterange-helper-text-max-age-same', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );
		$this->assertStringContainsString( 'Page4', $html );

		// NukeMaxAge matches RCMaxAge. Should have no difference from above.
		$this->overrideConfigValues( [
			'NukeMaxAge' => 86400 * 3,
			'RCMaxAge' => 86400 * 3
		] );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringNotContainsString( 'nuke-daterange-helper-text-max-age-different', $html );
		$this->assertStringContainsString( 'nuke-daterange-helper-text-max-age-same', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );
		$this->assertStringContainsString( 'Page4', $html );

		// NukeMaxAge is greater than RCMaxAge. It should show older pages.
		$this->overrideConfigValues( [
			'NukeMaxAge' => 86400 * 5,
			'RCMaxAge' => 86400
		] );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( 'nuke-daterange-helper-text-max-age-different', $html );
		$this->assertStringNotContainsString( 'nuke-daterange-helper-text-max-age-same', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );
		$this->assertStringContainsString( 'Page4', $html );

		// NukeMaxAge is lesser than RCMaxAge. It should show newer pages.
		$this->overrideConfigValues( [
			'NukeMaxAge' => 86400 * 3,
			'RCMaxAge' => 86400 * 5
		] );
		// Rebuild recent changes to make Page2 reappear on the recentchanges table.
		// We still don't want to match it.
		$this->rebuildRecentChanges( $time - ( 86400 * 8 ) - 60, $time + 60 );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringContainsString( 'nuke-daterange-helper-text-max-age-different', $html );
		$this->assertStringNotContainsString( 'nuke-daterange-helper-text-max-age-same', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );
		$this->assertStringContainsString( 'Page4', $html );
	}

	public function testListDateFrom() {
		$time = time();

		// 7 days
		$maxAge = 86400 * 7;
		$this->overrideConfigValues( [ 'NukeMaxAge' => $maxAge ] );

		$testUser = $this->getTestUser();
		// Will never show up. If it does, the max age isn't being applied at all.
		// We're still checking this here on the off-chance that something in Special:Nuke logic
		// causes us to completely forget about our max age configuration.
		$this->editPageAtTime(
			'Page1',
			'test',
			'',
			$time - ( $maxAge * 2 ),
			NS_MAIN,
			$testUser->getAuthority()
		);
		// Will show up conditionally (see below).
		$this->editPageAtTime(
			'Page2',
			'test',
			'',
			$time - ( 86400 * 2 ),
			NS_MAIN,
			$testUser->getAuthority()
		);
		// Will always show up.
		$this->editPage(
			'Page3',
			'test',
			'',
			NS_MAIN,
			$testUser->getAuthority()
		);

		$admin = $this->getTestSysop()->getUser();

		// Test setting from to include Page3 only
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'limit' => 2,
			'wpdateFrom' => date( 'Y-m-d', $time ),
			// Required for field validation to run
			'wpFormIdentifier' => 'massdelete'
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );

		// Now include Page2 by including things which are 2 days old
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'limit' => 2,
			'wpdateFrom' => date( 'Y-m-d', $time - ( 86400 * 2 ) ),
			// Required for field validation to run
			'wpFormIdentifier' => 'massdelete'
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );

		// Now go beyond our max age and ensure we get an error
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'limit' => 2,
			'wpdateFrom' => date( 'Y-m-d', $time - ( $maxAge * 2 + 60 ) ),
			// Required for field validation to run
			'wpFormIdentifier' => 'massdelete'
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html, [ 'nuke-date-limited' ] );
		$this->assertStringContainsString( "(days: 7)", $html );
		// The search should not happen at all.
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringNotContainsString( 'Page3', $html );

		// Now go beyond the current time and ensure we get no pages
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'limit' => 2,
			'wpdateFrom' => date( 'Y-m-d', $time + 86400 ),
			// Required for field validation to run
			'wpFormIdentifier' => 'massdelete'
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		// There should be no listed pages.
		$this->checkForValidationMessages( $html, [ 'nuke-nopages-global' ] );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringNotContainsString( 'Page3', $html );

		// Test invalid date filter
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'limit' => 2,
			'wpdateFrom' => 'i am an invalid date filter!!!',
			// Required for field validation to run
			'wpFormIdentifier' => 'massdelete'
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		// The search should not happen.
		$this->checkForValidationMessages( $html, [ 'htmlform-date-invalid' ] );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringNotContainsString( 'Page3', $html );
	}

	public function testListDateTo() {
		// 7 days
		$maxAge = 86400 * 7;
		$this->overrideConfigValues( [ 'NukeMaxAge' => $maxAge ] );

		$testUser = $this->getTestUser();
		// Will never show up. If it does, the max age isn't being applied at all.
		// We're still checking this here on the off-chance that something in Special:Nuke logic
		// causes us to completely forget about our max age configuration.
		$this->editPageAtTime(
			'Page1',
			'test',
			'',
			time() - ( $maxAge * 2 ),
			NS_MAIN,
			$testUser->getAuthority()
		);
		// Will always show up.
		$this->editPageAtTime(
			'Page2',
			'test',
			'',
			time() - ( 86400 * 4 ),
			NS_MAIN,
			$testUser->getAuthority()
		);
		// Will show up conditionally (see below).
		$this->editPage(
			'Page3',
			'test',
			'',
			NS_MAIN,
			$testUser->getAuthority()
		);

		$admin = $this->getTestSysop()->getUser();

		// Include everything except Page1
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'limit' => 2,
			'wpdateTo' => date( 'Y-m-d', time() ),
			// Required for field validation to run
			'wpFormIdentifier' => 'massdelete'
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );

		// Now exclude Page3 by shortening the date range
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'limit' => 2,
			'wpdateTo' => date( 'Y-m-d', time() - ( 86400 * 2 ) ),
			// Required for field validation to run
			'wpFormIdentifier' => 'massdelete'
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringContainsString( 'Page2', $html );
		$this->assertStringNotContainsString( 'Page3', $html );

		// Now go beyond our max age and ensure we get an error
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'limit' => 2,
			'wpdateTo' => date( 'Y-m-d', time() - ( $maxAge * 2 + 60 ) ),
			// Required for field validation to run
			'wpFormIdentifier' => 'massdelete'
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html, [ 'nuke-date-limited' ] );
		$this->assertStringContainsString( "(days: 7)", $html );
		// The search should not happen at all.
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringNotContainsString( 'Page3', $html );

		// Now go beyond the current time and ensure we still get pages
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'limit' => 2,
			'wpdateTo' => date( 'Y-m-d', time() + 86400 ),
			// Required for field validation to run
			'wpFormIdentifier' => 'massdelete'
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );

		// Test invalid date filter
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'limit' => 2,
			'wpdateTo' => 'i am an invalid date filter!!!',
			// Required for field validation to run
			'wpFormIdentifier' => 'massdelete'
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		// The search should not happen.
		$this->checkForValidationMessages( $html, [ 'htmlform-date-invalid' ] );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringNotContainsString( 'Page3', $html );
	}

	/**
	 * Runs tests which use both "from date" and "to date" filters.
	 *
	 * See also:
	 *  - {@link testListDateFrom}
	 *  - {@link testListDateTo}
	 *
	 * @return void
	 */
	public function testListDateFilters() {
		// 7 days
		$maxAge = 86400 * 7;
		$this->overrideConfigValues( [ 'NukeMaxAge' => $maxAge ] );

		$testUser = $this->getTestUser();
		for ( $i = 0; $i <= 8; $i++ ) {
			// Creates Page0, Page1, Page2... with creation date of `now`, `now - 1 day`, `now -
			// 2 days`, etc.
			$this->editPageAtTime(
				"Page$i",
				"$i",
				'',
				time() - ( 86400 * $i ),
				NS_MAIN,
				$testUser->getAuthority()
			);
		}

		$admin = $this->getTestSysop()->getUser();

		// Standard search
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'wpdateFrom' => date( 'Y-m-d', time() - ( 86400 * 4 ) ),
			'wpdateTo' => date( 'Y-m-d', time() - ( 86400 * 2 ) )
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		$this->assertStringNotContainsString( 'Page0', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		// A page created on the same day as "to date" should still be included.
		$this->assertStringContainsString( 'Page2', $html );
		$this->assertStringContainsString( 'Page3', $html );
		$this->assertStringContainsString( 'Page4', $html );
		$this->assertStringNotContainsString( 'Page5', $html );
		$this->assertStringNotContainsString( 'Page6', $html );
		$this->assertStringNotContainsString( 'Page7', $html );
		$this->assertStringNotContainsString( 'Page8', $html );

		// Impossible search ("to date" is before "from date")
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'wpdateFrom' => date( 'Y-m-d', time() - ( 86400 * 2 ) ),
			'wpdateTo' => date( 'Y-m-d', time() - ( 86400 * 4 ) )
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html, [ 'nuke-nopages-global' ] );

		$this->assertStringNotContainsString( 'Page0', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringNotContainsString( 'Page3', $html );
		$this->assertStringNotContainsString( 'Page4', $html );
		$this->assertStringNotContainsString( 'Page5', $html );
		$this->assertStringNotContainsString( 'Page6', $html );
		$this->assertStringNotContainsString( 'Page7', $html );
		$this->assertStringNotContainsString( 'Page8', $html );

		// Single date search ("to date" is equal to "from date")
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $testUser->getUser()->getName(),
			'pattern' => 'Page%',
			'wpdateFrom' => date( 'Y-m-d', time() - ( 86400 * 4 ) ),
			'wpdateTo' => date( 'Y-m-d', time() - ( 86400 * 4 ) )
		], true );
		$adminPerformer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		$this->assertStringNotContainsString( 'Page0', $html );
		$this->assertStringNotContainsString( 'Page1', $html );
		$this->assertStringNotContainsString( 'Page2', $html );
		$this->assertStringNotContainsString( 'Page3', $html );
		$this->assertStringContainsString( 'Page4', $html );
		$this->assertStringNotContainsString( 'Page5', $html );
		$this->assertStringNotContainsString( 'Page6', $html );
		$this->assertStringNotContainsString( 'Page7', $html );
		$this->assertStringNotContainsString( 'Page8', $html );
	}

	public function testListIncludeTalkPages() {
		$user1 = $this->getMutableTestUser();
		$user2 = $this->getMutableTestUser();

		// Create a page and its talk page
		$this->insertPage( 'Page1', 'test', NS_MAIN, $user1->getUser() );
		$this->insertPage( 'Talk:Page1', 'test', NS_TALK, $user2->getUser() );

		$admin = $this->getTestSysop()->getUser();
		$performer = new UltimateAuthority( $admin );

		// Include talk pages
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user1->getUser()->getName(),
			'includeTalkPages' => true
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		// HTML syntax here is used to ensure that it doesn't appear inside a tag.
		$this->assertStringContainsString( 'Page1</a>', $html );
		$this->assertStringContainsString( 'Talk:Page1</a>', $html );
		$this->assertStringContainsString( $user2->getUser()->getName(), $html );

		// Exclude talk pages
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user1->getUser()->getName(),
			'includeTalkPages' => false
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		// HTML syntax here is used to ensure that it doesn't appear inside a tag.
		$this->assertStringContainsString( 'Page1</a>', $html );
		$this->assertStringNotContainsString( 'Talk:Page1</a>', $html );
		$this->assertStringNotContainsString( $user2->getUser()->getName(), $html );
	}

	public function testListIncludeRedirectPages() {
		$user1 = $this->getMutableTestUser();
		$user2 = $this->getMutableTestUser();

		// Create a page and its talk page
		$this->insertPage( 'Page 1', 'test', NS_MAIN, $user1->getUser() );
		$this->insertPage(
			'Redirect 1',
			'#REDIRECT [[Page 1]]',
			NS_MAIN,
			$user2->getUser()
		)['title'];
		$this->runJobs();

		$admin = $this->getTestSysop()->getUser();
		$performer = new UltimateAuthority( $admin );

		// Include talk pages
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user1->getUser()->getName(),
			'includeRedirects' => true
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		// HTML syntax here is used to ensure that it doesn't appear inside a tag.
		$this->assertStringContainsString( 'Page 1</a>', $html );
		$this->assertStringContainsString( 'Redirect 1</a>', $html );
		$this->assertStringContainsString( $user2->getUser()->getName(), $html );

		// Exclude talk pages
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user1->getUser()->getName(),
			'includeRedirects' => false
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		// HTML syntax here is used to ensure that it doesn't appear inside a tag.
		$this->assertStringContainsString( 'Page 1</a>', $html );
		$this->assertStringNotContainsString( 'Redirect 1</a>', $html );
		$this->assertStringNotContainsString( $user2->getUser()->getName(), $html );
	}

	/**
	 * Ensure that pages don't appear twice on the list when a page was created by a user and is
	 * also an associated page of another page.
	 *
	 * @return void
	 */
	public function testListAssociatedPagesDeduplication() {
		$user = $this->getTestUser();
		$this->insertPage( 'Page 1', 'test', NS_MAIN, $user->getUser() );
		$this->insertPage( 'Redirect 1', '#REDIRECT [[Page 1]]', NS_MAIN, $user->getUser() );
		$this->insertPage( 'Page 1', 'test', NS_TALK, $user->getUser() );

		$admin = $this->getTestSysop()->getUser();
		$performer = new UltimateAuthority( $admin );

		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user->getUser()->getName(),
			'includeTalkPages' => true,
			'includeRedirects' => true
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		// HTML syntax here is used to ensure that it doesn't appear inside a tag.
		$this->assertSame(
			1,
			substr_count( $html, '>Page 1</a>' ),
			"Failed asserting that \"$html\" contains '>>Page 1</a>' only once."
		);
		$this->assertSame(
			1,
			substr_count( $html, '>Redirect 1</a>' ),
			"Failed asserting that \"$html\" contains '>Redirect 1</a>' only once."
		);
		$this->assertSame(
			1,
			substr_count( $html, '>Talk:Page 1</a>' ),
			"Failed asserting that \"$html\" contains '>Talk:Page 1</a>' only once."
		);
	}

	public function testListLimitAssociatedPages() {
		$time = time();
		$authority1 = $this->getMutableTestUser()->getAuthority();
		$authority2 = $this->getMutableTestUser()->getAuthority();

		$page1 =
			$this->editPageAtTime( 'Page 1', 'test', '', $time, NS_MAIN, $authority1 );
		$this->editPageAtTime( 'Page 2', 'test', '', $time - 5, NS_MAIN, $authority1 );
		$this->editPageAtTime( 'Page 3', 'test', '', $time - 10, NS_MAIN, $authority1 );
		$this->editPageAtTime(
			'Redirect 1',
			'#REDIRECT [[Page 2]]',
			'',
			$time - 1,
			NS_MAIN,
			$authority2
		);
		$this->editPageAtTime(
			'Redirect 2',
			'#REDIRECT [[Page 2]]',
			'',
			$time - 20,
			NS_MAIN,
			$authority2
		);
		$this->editPageAtTime(
			'Page 3',
			'test',
			'',
			$time - 25,
			NS_TALK,
			$authority2
		);
		$pages = [ 'Page 1', 'Page 2', 'Page 3', 'Redirect 1', 'Redirect 2', 'Talk:Page 3' ];

		$admin = $this->getTestSysop()->getUser();
		$performer = new UltimateAuthority( $admin );

		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $authority1->getUser()->getName(),
			'includeTalkPages' => true,
			'includeRedirects' => true
		], true );

		// First test no limits
		$request->setVal( 'limit', 7 );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringsFound( $html, $pages, $pages );

		// Now test with limit = 1.
		// Only Page 1 should show up, and there should be no validation error messages.
		$request->setVal( 'limit', 1 );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringsFound( $html, $pages, [ 'Page 1' ] );

		// Now test with limit = 2.
		// It should still only be page 1 here.
		// Page 2 will be skipped because it has 2 associated pages, totalling 3 pages in the group.
		// Page 3 will be skipped because it has a talk page, totalling 2 pages in the group.
		// This will cause a limit warning to show up.
		$request->setVal( 'limit', 2 );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html, [ 'nuke-associated-limited' ] );
		$this->assertStringsFound( $html, $pages, [ 'Page 1' ] );

		// Now test with limit = 3
		// Only the Page 1 and Page 3 should show up.
		// Page 2 will be skipped because it has 2 associated pages, totalling 3 pages in the group.
		// This will cause a limit warning to show up.
		$request->setVal( 'limit', 3 );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html, [ 'nuke-associated-limited' ] );
		$this->assertStringsFound( $html, $pages, [ 'Page 1', 'Page 3', 'Talk:Page 3' ] );

		// Now test with limit = 4
		// Page 1, Page 2, and all its redirects should show up.
		// Page 3 will be excluded since Page 2 was created later.
		// This will cause a limit warning to show up.
		$request->setVal( 'limit', 4 );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html, [ 'nuke-associated-limited' ] );
		$this->assertStringsFound( $html, $pages, [
			'Page 1',
			'Page 2',
			'Redirect 1',
			'Redirect 2'
		] );

		// Now test with limit = 4, but include only talk pages
		// Page 1, Page 2, Page 3, and Talk:Page 3 should show up.
		// There should be no limit warning.
		$request->setVal( 'limit', 4 );
		$request->setVal( 'includeRedirects', false );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );
		$this->assertStringsFound( $html, $pages, [
			'Page 1',
			'Page 2',
			'Page 3',
			'Talk:Page 3'
		] );

		// Now test with limit = 1, but delete Page 1
		// There should be a limit warning and a no pages warning.
		$this->deletePage( $this->getServiceContainer()->getWikiPageFactory()->newFromID(
			$page1->getNewRevision()->getPageId()
		) );
		$request->setVal( 'limit', 1 );
		$request->setVal( 'includeRedirects', true );
		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html, [ 'nuke-nopages-global', 'nuke-associated-limited' ] );
		$this->assertStringsFound( $html, $pages, [] );
	}

	public function testListFiles() {
		$testFileName = $this->uploadTestFile()['title']->getPrefixedText();

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'namespace' => NS_FILE
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html );

		// The title should be in the list
		$this->assertStringContainsString( $testFileName, $html );
		// There should also be an image preview
		$this->assertStringContainsString( "<img src", $html );
	}

	public function testConfirm() {
		$user = $this->getTestUser()->getUser();

		// Set content language to English; needed to get the right "defaultreason".
		$this->overrideConfigValue( 'wgLanguageCode', 'en' );
		$defaultReason = RequestContext::getMain()
			->msg( 'nuke-defaultreason', $user->getName() )
			->inContentLanguage()
			->text();

		/** @var Title $page1 */
		$page1 = $this->insertPage( 'Target1', 'test', NS_MAIN, $user )['title'];
		/** @var Title $page2 */
		$page2 = $this->insertPage( 'Target2', 'test' )['title'];

		$adminUser = $this->getTestSysop()->getUser();
		$adminPerformer = new UltimateAuthority( $adminUser );
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_CONFIRM,
			'target' => $user->getName(),
			'pages' => [
				$page1->getPrefixedDBkey()
			],
			'originalPageList' => implode(
				SpecialNuke::PAGE_LIST_SEPARATOR,
				[ $page1->getPrefixedDBkey(), $page2->getPrefixedDBkey() ]
			),
			// 'confirm' action requires an edit token
			'wpEditToken' => $user->getEditToken()
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		// Default reason should pre-fill the reason field
		$this->assertStringContainsString( $defaultReason, $html );

		// Selected pages will have talk and history links
		$this->assertStringContainsString( 'Target1', $html );
		$this->assertStringContainsString( 'Talk:Target1', $html );
		$this->assertStringContainsString( 'Target1&amp;action=history', $html );

		// Skipped pages will not show up at all, but will be found in the hidden
		// originalPageList field.
		$this->assertStringContainsString( 'Target2', $html );
		$this->assertStringNotContainsString( 'Talk:Target2', $html );
		$this->assertStringNotContainsString( 'Target2&amp;action=history', $html );

		// Ensure the originalPageList field was included
		$this->assertStringContainsString( '"originalPageList"', $html );
		$this->assertStringContainsString( '"Target1|Target2"', $html );
	}

	/**
	 * Tests a malformed confirm request: a request that either
	 *  - was not posted
	 *  - does not have an edit token
	 *
	 * @return void
	 */
	public function testConfirmMalformed() {
		$user = $this->getTestUser()->getUser();

		/** @var Title $page1 */
		$page1 = $this->insertPage( 'Target1', 'test', NS_MAIN, $user )['title'];
		/** @var Title $page2 */
		$page2 = $this->insertPage( 'Target2', 'test' )['title'];

		$adminUser = $this->getTestSysop()->getUser();
		$adminPerformer = new UltimateAuthority( $adminUser );
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_CONFIRM,
			'target' => $user->getName(),
			'pages' => [
				$page1->getPrefixedDBkey()
			],
			'originalPageList' => implode(
				SpecialNuke::PAGE_LIST_SEPARATOR,
				[ $page1->getPrefixedDBkey(), $page2->getPrefixedDBkey() ]
			),
		], false );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		// We should be re-prompting the user, since no edit token was provided.
		$this->assertStringContainsString( "(nuke-tools-prompt)", $html );
		// Correct target should still be on the prompt box
		$this->assertStringContainsString( $user->getName(), $html );
	}

	/**
	 * Ensure that the default deletion reason used correctly changes based on whether or not
	 * temporary accounts are being used or not.
	 *
	 * @return void
	 */
	public function testConfirmAnonUser() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$this->enableAutoCreateTempUser();
		$ip = '1.2.3.4';
		RequestContext::getMain()->getRequest()->setIP( $ip );
		$testUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )->getUser();

		$user = $this->getTestUser()->getUser();

		// Set content language to English; needed to get the right "defaultreason".
		$this->overrideConfigValue( 'wgLanguageCode', 'en' );
		$defaultReason = RequestContext::getMain()
			->msg( 'nuke-defaultreason-tempaccount', $user->getName() )
			->inContentLanguage()
			->text();

		/** @var Title $page1 */
		$page1 = $this->insertPage( 'Target1', 'test', NS_MAIN, $testUser )['title'];

		$adminUser = $this->getTestSysop()->getUser();
		$adminPerformer = new UltimateAuthority( $adminUser );

		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		$permissionManager->overrideUserRightsForTesting( $adminUser,
			array_merge(
				$permissionManager->getUserPermissions( $adminUser ),
				[ 'checkuser-temporary-account-no-preference' ]
			) );

		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_CONFIRM,
			'target' => $ip,
			'pages' => [
				$page1->getPrefixedDBkey()
			],
			'originalPageList' => $page1->getPrefixedDBkey(),
			// 'confirm' action requires an edit token
			'wpEditToken' => $user->getEditToken()
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		$this->assertStringContainsString( $defaultReason, $html );
		$this->assertStringContainsString( 'Target1', $html );
		$this->assertStringContainsString( 'Talk:Target1', $html );
		$this->assertStringContainsString( 'Target1&amp;action=history', $html );
	}

	/**
	 * Tests requests which have pages but no original page list. This is similar to a click on
	 * the "Continue" button without having first clicked on the "List pages" button.
	 *
	 * @return void
	 */
	public function testConfirmWithoutList() {
		$user = $this->getTestUser()->getUser();

		$this->editPage( 'Page1', 'test' );

		$adminUser = $this->getTestSysop()->getUser();
		$adminPerformer = new UltimateAuthority( $adminUser );
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_CONFIRM,
			// 'confirm' action requires an edit token
			'wpEditToken' => $user->getEditToken()
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html, [ 'nuke-nolist' ] );
		// The search should not happen automatically.
		$this->assertStringNotContainsString( 'Page1', $html );
	}

	public function testConfirmWithoutSelection() {
		$user = $this->getTestUser()->getUser();

		/** @var Title $page1 */
		$page1 = $this->insertPage( 'Target1', 'test', NS_MAIN, $user )['title'];
		/** @var Title $page2 */
		$page2 = $this->insertPage( 'Target2', 'test' )['title'];

		$adminUser = $this->getTestSysop()->getUser();
		$adminPerformer = new UltimateAuthority( $adminUser );
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_CONFIRM,
			'target' => $user->getName(),
			'originalPageList' => implode( SpecialNuke::PAGE_LIST_SEPARATOR, [
				$page1->getPrefixedDBkey(),
				$page2->getPrefixedDBkey()
			] ),
			// 'confirm' action requires an edit token
			'wpEditToken' => $user->getEditToken()
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html, [ 'nuke-noselected' ] );
		// The search should still happen.
		$this->assertStringContainsString( 'Target1', $html );
		// Page2 was not created by the user, it shouldn't show up.
		$this->assertStringNotContainsString( 'Target2', $html );
	}

	public function testConfirmAssociatedPages() {
		$user = $this->getTestUser()->getUser();
		$this->insertPage( 'Page 1', 'test', NS_MAIN, $user );
		$this->insertPage( 'Redirect 2', '#REDIRECT [[Page 1]]', NS_MAIN, $user );

		$adminUser = $this->getTestSysop()->getUser();
		$adminPerformer = new UltimateAuthority( $adminUser );
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_CONFIRM,
			'target' => $user->getName(),
			'originalPageList' => implode( SpecialNuke::PAGE_LIST_SEPARATOR, [
				'Page_1', 'Page_2'
			] ),
			'pages' => [ 'Page_1' ],
			'associatedPages' => [ 'Redirect_2' ],
			// 'confirm' action requires an edit token
			'wpEditToken' => $user->getEditToken()
		], true );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );
		// Ensure that the associated pages are also listed on the confirm form
		$this->assertStringContainsString( 'name="associatedPages[]"', $html );
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

		$deleteCount = count( $pages );
		$this->assertStringContainsString( "(nuke-delete-summary: $deleteCount)", $html );
		$this->assertStringContainsString( '(nuke-deletion-queued: Page123)', $html );
		$this->assertStringContainsString( '(nuke-deletion-queued: Paging456)', $html );

		// Pre-check to confirm that the page hasn't been deleted yet
		// "mw-tag-marker-nuke" is the CSS class for the "Nuke" tag's <span> on Special:Log.
		$this->assertStringNotContainsString( 'mw-tag-marker-nuke', $this->getDeleteLogHtml() );

		// Ensure all delete jobs are run
		$this->runJobs();

		$deleteLogHtml = $this->getDeleteLogHtml();
		$this->assertStringContainsString( 'Vandalism', $deleteLogHtml );
		$this->assertStringContainsString( 'mw-tag-marker-nuke', $deleteLogHtml );
		$this->assertStringContainsString( $fauxReason, $deleteLogHtml );
	}

	public function testDeleteTarget() {
		$pages = [];

		$testUser = $this->getTestUser( "user" )->getUser();
		$testUserName = $testUser->getName();

		$pages[] = $this->uploadTestFile( $testUser )[ 'title' ];
		$pages[] = $this->insertPage( 'Page123', 'Test', NS_MAIN, $testUser )[ 'title' ];
		$pages[] = $this->insertPage( 'Paging456', 'Test', NS_MAIN, $testUser )[ 'title' ];

		$admin = $this->getTestSysop()->getUser();

		$fauxReason = "Reason " . wfRandomString();
		$request = new FauxRequest( [
			'action' => 'delete',
			'wpDeleteReasonList' => 'Vandalism',
			'wpReason' => $fauxReason,
			'target' => $testUserName,
			'pages' => $pages,
			'wpFormIdentifier' => 'nukelist',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$deleteCount = count( $pages );
		$this->assertStringContainsString(
			"(nuke-delete-summary-user: $deleteCount, $testUserName)",
			$html
		);
	}

	public function testDeleteTargetAnon() {
		$pages = [];

		$testUser = $this->getTestUser( "user" )->getUser();
		$testUserName = $testUser->getName();

		$pages[] = $this->uploadTestFile( $testUser )[ 'title' ];
		$pages[] = $this->insertPage( 'Page123', 'Test', NS_MAIN, $testUser )[ 'title' ];
		$pages[] = $this->insertPage( 'Paging456', 'Test', NS_MAIN, $testUser )[ 'title' ];

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

		$deleteCount = count( $pages );
		$this->assertStringContainsString(
			"(nuke-delete-summary: $deleteCount)",
			$html
		);
	}

	public function testDeleteSkipped() {
		$pages = [];
		$pages[] = $this->insertPage( 'Page123', 'Test', NS_MAIN )[ 'title' ];
		$pages[] = $this->insertPage( 'Paging 456', 'Test', NS_MAIN )[ 'title' ];
		$skippedPage = $this->insertPage( 'Page 789', 'Test', NS_MAIN )['title'];

		$admin = $this->getTestSysop()->getUser();

		$request = new FauxRequest( [
			'action' => 'delete',
			'wpDeleteReasonList' => 'Vandalism',
			'originalPageList' => implode( '|', $pages ),
			'pages' => [ $skippedPage->getPrefixedDBkey() ],
			'wpFormIdentifier' => 'nukelist',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$skippedCount = count( $pages );
		$this->assertStringContainsString( '(nuke-delete-summary: 1)', $html );
		$this->assertStringContainsString( "(nuke-skipped-summary: $skippedCount)", $html );

		// Ensure all delete jobs are run
		$this->runJobs();

		// Make sure that those pages are not in the deletion log.
		$deleteLogHtml = $this->getDeleteLogHtml();
		foreach ( $pages as $title ) {
			$this->assertStringNotContainsString( $title->getPrefixedText(), $deleteLogHtml );
		}
		$this->assertStringContainsString( $skippedPage->getPrefixedText(), $deleteLogHtml );
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

	public function testDeleteAssociatedReason() {
		$this->insertPage( 'Page 1', 'Test', NS_MAIN );
		$this->insertPage( 'Redirect 1', '#REDIRECT [[Page 1]]', NS_MAIN );

		$admin = $this->getTestSysop()->getUser();

		$fauxReason = "Reason " . wfRandomString();
		$request = new FauxRequest( [
			'action' => 'delete',
			'wpDeleteReasonList' => 'other',
			'wpReason' => $fauxReason,
			'pages' => [ 'Page 1' ],
			'associatedPages' => [ 'Redirect 1' ],
			'wpFormIdentifier' => 'nukelist',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		$this->executeSpecialPage( '', $request, 'qqx', $performer );

		// Ensure all jobs are run
		$this->runJobs();

		// Check logs
		$logHtml = $this->getDeleteLogHtml();
		$this->assertStringContainsString( $fauxReason, $logHtml );
		$this->assertStringContainsString(
			wfMessage( 'delete-talk-summary-prefix', $fauxReason )
				->inContentLanguage()
				->text(),
			$this->getDeleteLogHtml()
		);
	}

	public function testDeleteFiles() {
		$testFileName = $this->uploadTestFile()['title']->getPrefixedText();

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => 'delete',
			'wpDeleteReasonList' => 'Reason',
			'wpReason' => 'Reason',
			'pages' => [ $testFileName ],
			'wpFormIdentifier' => 'nukelist',
			'wpEditToken' => $admin->getEditToken(),
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		// Files are deleted instantly
		$this->assertStringContainsString( "nuke-deleted", $html );
		$this->assertStringContainsString( $testFileName, $html );
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

	public function testOldDeleteProtectedPage() {
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
			'action' => SpecialNuke::ACTION_DELETE,
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

	/**
	 * Test filtering by minimum page size.
	 *
	 * @return void
	 */
	public function testListMinPageSizeFilter() {
		$user = $this->getTestUser()->getUser();

		// 4 bytes content
		$this->insertPage( 'SmallPage', 'test', NS_MAIN, $user );
		// 9 bytes content
		$this->insertPage( 'MediumPage', 'test test', NS_MAIN, $user );
		// 14 bytes content
		$this->insertPage( 'LargePage', 'test test test', NS_MAIN, $user );

		$adminUser = $this->getTestSysop()->getUser();
		$adminPerformer = new UltimateAuthority( $adminUser );
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user->getName(),
			// Filter out pages smaller than 10 bytes
			'minPageSize' => 10
		] );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		$this->assertStringNotContainsString( 'SmallPage', $html );
		$this->assertStringNotContainsString( 'MediumPage', $html );
		$this->assertStringContainsString( 'LargePage', $html );
	}

	/**
	 * Test filtering by maximum page size.
	 *
	 * @return void
	 */
	public function testListMaxPageSizeFilter() {
		$user = $this->getTestUser()->getUser();

		// 4 bytes content
		$this->insertPage( 'SmallPage', 'test', NS_MAIN, $user );
		// 9 bytes content
		$this->insertPage( 'MediumPage', 'test test', NS_MAIN, $user );
		// 14 bytes content
		$this->insertPage( 'LargePage', 'test test test', NS_MAIN, $user );

		$adminUser = $this->getTestSysop()->getUser();
		$adminPerformer = new UltimateAuthority( $adminUser );
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user->getName(),
			// Filter out pages larger than 9 bytes
			'maxPageSize' => 9,
		] );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		$this->assertStringContainsString( 'SmallPage', $html );
		$this->assertStringContainsString( 'MediumPage', $html );
		$this->assertStringNotContainsString( 'LargePage', $html );
	}

	/**
	 * Test filtering by both minimum and maximum page size.
	 *
	 * @return void
	 */
	public function testListMinMaxPageSizeFilter() {
		$user = $this->getTestUser()->getUser();

		// 2 bytes content
		$this->insertPage( 'TinyPage', 'te', NS_MAIN, $user );
		// 4 bytes content
		$this->insertPage( 'SmallPage', 'test', NS_MAIN, $user );
		// 9 bytes content
		$this->insertPage( 'MediumPage', 'test test', NS_MAIN, $user );
		// 14 bytes content
		$this->insertPage( 'LargePage', 'test test test', NS_MAIN, $user );
		// 19 bytes content
		$this->insertPage( 'HugePage', 'test test test test', NS_MAIN, $user );

		$adminUser = $this->getTestSysop()->getUser();
		$adminPerformer = new UltimateAuthority( $adminUser );
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user->getName(),
			// Minimum size is 5 bytes
			'minPageSize' => 5,
			// Maximum size is 15 bytes
			'maxPageSize' => 15
		] );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $adminPerformer );
		$this->checkForValidationMessages( $html );

		$this->assertStringNotContainsString( 'TinyPage', $html );
		// size is 4, less than minSize
		$this->assertStringNotContainsString( 'SmallPage', $html );
		 // size is 9, within range
		$this->assertStringContainsString( 'MediumPage', $html );
		// size is 14, within range
		$this->assertStringContainsString( 'LargePage', $html );
		// size is 19, greater than maxSize
		$this->assertStringNotContainsString( 'HugePage', $html );
	}

	/**
	 * Check if a validation warning/error message can be found in the search, and ensure that no
	 * other error messages appear.
	 *
	 * @param string $html The HTML to check
	 * @param string[] $messages The i18n keys of the messages that should be found.
	 * @return void
	 */
	private function checkForValidationMessages( string $html, ?array $messages = [] ) {
		$errorMessages = [
			"htmlform-user-not-valid",
			"nuke-date-limited",
			"nuke-date-ahead",
			"htmlform-date-invalid",
			"nuke-nolist",
			"nuke-nopages-global",
			"nuke-noselected",
			"nuke-associated-limited",
			"nuke-searchnotice-minmorethanmax",
			"nuke-searchnotice-negmin",
			"nuke-searchnotice-negmax"
		];

		$shouldBeFound = $messages;
		$shouldBeMissing = array_diff( $errorMessages, $messages );

		foreach ( $shouldBeFound as $validationMessage ) {
			$this->assertStringContainsString( $validationMessage, $html );
		}
		foreach ( $shouldBeMissing as $validationMessage ) {
			$this->assertStringNotContainsString( $validationMessage, $html );
		}
	}

	/**
	 * Test search notices are displayed when no pages are found and there are search notices.
	 *
	 * @return void
	 */
	public function testListNoPagesGlobalWithSearchNotices() {
		$admin = $this->getTestSysop()->getUser();

		// Cause a search notice by maxing the min more than the max
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'pattern' => 'ThisPageShouldNotExist-' . rand(),
			'minPageSize' => 2000,
			'maxPageSize' => 1000,
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html, [ 'nuke-nopages-global', 'nuke-searchnotice-minmorethanmax' ] );
		$this->assertStringContainsString( 'nuke-submit-list', $html );
		$this->assertStringNotContainsString( 'nuke-submit-continue', $html );
	}

	/**
	 * Test search notices are displayed even when pages are found.
	 *
	 * @return void
	 */
	public function testListWithSearchNotices() {
		$user = $this->getTestUser()->getUser();
		$this->insertPage( 'SomePage', 'test', NS_MAIN, $user );

		$admin = $this->getTestSysop()->getUser();
		$request = new FauxRequest( [
			'action' => SpecialNuke::ACTION_LIST,
			'target' => $user->getName(),
			'minPageSize' => -1,
			'maxPageSize' => 1000,
		], true );
		$performer = new UltimateAuthority( $admin );

		[ $html ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );
		$this->checkForValidationMessages( $html, [ 'nuke-searchnotice-negmin' ] );
		$this->assertStringContainsString( 'nuke-submit-list', $html );
		$this->assertStringContainsString( 'nuke-submit-continue', $html );

		// Page should still be listed
		$this->assertStringContainsString( 'SomePage', $html );
	}
}
