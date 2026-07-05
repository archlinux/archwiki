<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\HookHandler\Preferences;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Output\OutputPage;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\UserLinkRendererUserLinkPostRenderHandler
 */
class UserLinkRendererUserLinkPostRenderHandlerTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	public function testRenderWithoutFeatureEnabled() {
		$user = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$user,
			Preferences::ENABLE_USER_INFO_CARD,
			false
		);
		$userOptionsManager->saveOptions( $user );
		$output = $this->createMock( OutputPage::class );
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getUser' )->willReturn( $user );
		$context->method( 'getOutput' )->willReturn( $output );
		$this->assertStringStartsWith( '<a href=', $this->getServiceContainer()->getUserLinkRenderer()->userLink(
			$user,
			$context
		) );
	}

	public function testRenderWithFeatureEnabled() {
		$user = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$user,
			Preferences::ENABLE_USER_INFO_CARD,
			true
		);
		$userOptionsManager->saveOptions( $user );
		$context = RequestContext::getMain();
		$context->setUser( $user );
		$expected = "<span class=\"cdx-button__icon";
		$html = $this->getServiceContainer()->getUserLinkRenderer()->userLink(
			$user,
			$context
		);
		$this->assertStringContainsString( $expected, $html, 'Output does not contain Codex button' );
		$expected = "class=\"ext-checkuser-userinfocard-button";
		$this->assertStringContainsString( $expected, $html, 'Output does not contain expected CSS classes' );
	}

	public function testDontRenderForAnonUser() {
		$this->disableAutoCreateTempUser();
		$user = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$user,
			Preferences::ENABLE_USER_INFO_CARD,
			true
		);
		$userOptionsManager->saveOptions( $user );
		$context = RequestContext::getMain();
		$context->setUser( $user );
		$expected = "<span class=\"cdx-button__icon";
		$html = $this->getServiceContainer()->getUserLinkRenderer()->userLink(
			new UserIdentityValue( 0, 'Anonymous' ),
			$context
		);
		$this->assertStringNotContainsString( $expected, $html, 'Output does not contain Codex button' );
		$expected = "class=\"ext-checkuser-userinfocard-button";
		$this->assertStringNotContainsString( $expected, $html, 'Output does not contain expected CSS classes' );
	}

	public function testRenderBlockedUserShowsBlockedIcon() {
		$targetUser = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$targetUser,
			Preferences::ENABLE_USER_INFO_CARD,
			true
		);
		$userOptionsManager->saveOptions( $targetUser );

		$mockCache = $this->createMock( UserInfoCardBlockStatusCache::class );
		$mockCache->method( 'isIndefinitelyBlockedOrLocked' )->willReturn( true );
		$this->setService( 'CheckUserUserInfoCardBlockStatusCache', $mockCache );

		$context = RequestContext::getMain();
		$context->setUser( $targetUser );
		$html = $this->getServiceContainer()->getUserLinkRenderer()->userLink(
			$targetUser,
			$context
		);
		$this->assertStringContainsString(
			'ext-checkuser-userinfocard-button__icon--userBlocked',
			$html,
			'Output does not contain blocked icon class'
		);
	}

	public function testRenderUnblockedUserShowsAvatarIcon() {
		$targetUser = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$targetUser,
			Preferences::ENABLE_USER_INFO_CARD,
			true
		);
		$userOptionsManager->saveOptions( $targetUser );

		$mockCache = $this->createMock( UserInfoCardBlockStatusCache::class );
		$mockCache->method( 'isIndefinitelyBlockedOrLocked' )->willReturn( false );
		$this->setService( 'CheckUserUserInfoCardBlockStatusCache', $mockCache );

		$context = RequestContext::getMain();
		$context->setUser( $targetUser );
		$html = $this->getServiceContainer()->getUserLinkRenderer()->userLink(
			$targetUser,
			$context
		);
		$this->assertStringContainsString(
			'ext-checkuser-userinfocard-button__icon--userAvatar',
			$html,
			'Output does not contain avatar icon class'
		);
	}

	public function testRenderTempUserShowsTempIcon() {
		$this->enableAutoCreateTempUser();
		$targetUser = new UserIdentityValue( 100, '~2025-1' );
		$viewer = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$viewer,
			Preferences::ENABLE_USER_INFO_CARD,
			true
		);
		$userOptionsManager->saveOptions( $viewer );

		$mockCache = $this->createMock( UserInfoCardBlockStatusCache::class );
		$mockCache->method( 'isIndefinitelyBlockedOrLocked' )->willReturn( false );
		$this->setService( 'CheckUserUserInfoCardBlockStatusCache', $mockCache );

		$context = RequestContext::getMain();
		$context->setUser( $viewer );
		$html = $this->getServiceContainer()->getLinkRenderer()->makeUserLink(
			$targetUser,
			$context
		);
		$this->assertStringContainsString(
			'ext-checkuser-userinfocard-button__icon--userTemporary',
			$html,
			'Output does not contain temporary user icon class'
		);
	}

	public function testRepeatedUserLinkRendersConsistently() {
		$targetUser = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$targetUser,
			Preferences::ENABLE_USER_INFO_CARD,
			true
		);
		$userOptionsManager->saveOptions( $targetUser );

		$mockCache = $this->createMock( UserInfoCardBlockStatusCache::class );
		$mockCache->method( 'isIndefinitelyBlockedOrLocked' )->willReturn( true );
		$this->setService( 'CheckUserUserInfoCardBlockStatusCache', $mockCache );

		$context = RequestContext::getMain();
		$context->setUser( $targetUser );
		$userLinkRenderer = $this->getServiceContainer()->getUserLinkRenderer();
		$html1 = $userLinkRenderer->userLink( $targetUser, $context );
		$html2 = $userLinkRenderer->userLink( $targetUser, $context );
		$this->assertStringContainsString(
			'ext-checkuser-userinfocard-button__icon--userBlocked',
			$html1,
			'First call renders blocked icon'
		);
		$this->assertSame( $html1, $html2, 'Repeated calls produce identical output' );
	}
}
