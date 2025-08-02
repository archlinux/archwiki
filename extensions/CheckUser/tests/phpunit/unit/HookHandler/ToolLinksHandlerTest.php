<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\ToolLinksHandler;
use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\ToolLinksHandler
 */
class ToolLinksHandlerTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;

	public function testOnUserToolinksEditWhenRequestTitleIsNull() {
		$testUser = new UserIdentityValue( 42, 'Foobar' );
		$mainRequest = RequestContext::getMain();
		// Default first parameter of this method is null
		$mainRequest->setTitle();
		$items = [];
		$hookHandler = $this->newServiceInstance( ToolLinksHandler::class, [] );
		$hookHandler->onUserToolLinksEdit( $testUser->getId(), $testUser->getName(), $items );
		$this->assertCount(
			0, $items, 'A tool link should not have been added for a null request title.'
		);
	}

	public function testOnUserToolinksEditWrongNamespace() {
		$testUser = new UserIdentityValue( 42, 'Foobar' );
		$mainRequest = RequestContext::getMain();
		$mockTitle = $this->createMock( Title::class );
		$mockTitle->method( 'isSpecialPage' )
			->willReturn( false );
		$mainRequest->setTitle( $mockTitle );
		$items = [];
		$hookHandler = $this->newServiceInstance( ToolLinksHandler::class, [] );
		$hookHandler->onUserToolLinksEdit( $testUser->getId(), $testUser->getName(), $items );
		$this->assertCount(
			0, $items, 'A tool link should not have been added for a non-Special page'
		);
	}

	/** @dataProvider provideWrongSpecialPageTitles */
	public function testOnUserToolinksEditWrongSpecialPage( $requestTitle, $specialPageResolveAliasResult ) {
		$testUser = new UserIdentityValue( 42, 'Foobar' );
		$mainRequest = RequestContext::getMain();
		$mockTitle = $this->createMock( Title::class );
		$mockTitle->method( 'isSpecialPage' )
			->willReturn( true );
		$mockTitle->method( 'getText' )
			->willReturn( $requestTitle );
		$mainRequest->setTitle( $mockTitle );
		$specialPageFactory = $this->createMock( SpecialPageFactory::class );
		$specialPageFactory->expects( $this->once() )
			->method( 'resolveAlias' )
			->with( $requestTitle )
			->willReturn( $specialPageResolveAliasResult );
		$items = [];
		$hookHandler = $this->newServiceInstance( ToolLinksHandler::class, [
			'specialPageFactory' => $specialPageFactory
		] );
		$hookHandler->onUserToolLinksEdit( $testUser->getId(), $testUser->getName(), $items );
		$this->assertCount(
			0, $items, 'A tool link should not have been added for special pages other than ' .
			'Special:CheckUser and Special:CheckUserLog'
		);
	}

	public static function provideWrongSpecialPageTitles() {
		return [
			'Special:History' => [ 'Special:History', [ 'History' ] ],
			'Special:About' => [ 'Special:About', [ 'About' ] ],
			'Special:Diff with subpage' => [ 'Special:Diff/1234', [ 'Diff', '1234' ] ],
		];
	}

	public function testOnContributionsToolLinksNoRights() {
		// Mock PermissionManager to always say the user does not have the necessary right.
		$mockPermissionManager = $this->createMock( PermissionManager::class );
		$mockPermissionManager->method( 'userHasRight' )
			->willReturn( false );
		$hookHandler = $this->newServiceInstance( ToolLinksHandler::class, [
			'permissionManager' => $mockPermissionManager
		] );
		// Mock arguments to ::onContributionsToolLinks
		$mockUser = $this->createMock( User::class );
		$mockSpecialPage = $this->createMock( SpecialPage::class );
		$mockSpecialPage->method( 'getUser' )
			->willReturn( $mockUser );
		$links = [];
		$hookHandler->onContributionsToolLinks( 1, $this->createMock( Title::class ), $links, $mockSpecialPage );
		$this->assertCount(
			0,
			$links,
			'No links should have been added by ToolLinksHandler::onContributionsToolLinks if the user has no rights.'
		);
	}
}
