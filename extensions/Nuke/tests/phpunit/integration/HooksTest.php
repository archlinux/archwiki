<?php

namespace MediaWiki\Extension\Nuke\Test\Integration;

use MediaWiki\Extension\Nuke\Hooks;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Specials\SpecialContributions;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Nuke\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	private function newSpecialContribsPage(): SpecialContributions {
		$services = $this->getServiceContainer();

		return new SpecialContributions(
			$services->getLinkBatchFactory(),
			$services->getPermissionManager(),
			$services->getDBLoadBalancerFactory(),
			$services->getRevisionStore(),
			$services->getNamespaceInfo(),
			$services->getUserNameUtils(),
			$services->getUserNamePrefixSearch(),
			$services->getUserOptionsLookup(),
			$services->getCommentFormatter(),
			$services->getUserFactory(),
			$services->getUserIdentityLookup(),
			$services->getDatabaseBlockStore(),
			$services->getTempUserConfig()
		);
	}

	public function testContributionsNormal() {
		$sysop = $this->getTestSysop()->getUser();
		$performer = new UltimateAuthority( $sysop );

		$specialPage = $this->newSpecialContribsPage();
		$context = $specialPage->getContext();
		$context->setAuthority( $performer );

		$this->setUserLang( "qqx" );
		$tools = [];
		( new Hooks() )->onContributionsToolLinks(
			$sysop->getId(),
			$sysop->getUserPage(),
			$tools,
			$specialPage
		);

		$this->assertArrayHasKey( 'nuke', $tools );
	}

	public function testContributionsNoPermission() {
		$this->overrideConfigValues( [
			"GroupPermissions" => [
				"testgroup" => [
					"nuke" => false,
					"delete" => true
				]
			]
		] );

		$testUser = $this->getTestUser( [ "testgroup" ] );

		$specialPage = $this->newSpecialContribsPage();
		$context = $specialPage->getContext();
		$context->setAuthority( $testUser->getAuthority() );

		$this->setUserLang( "qqx" );
		$tools = [];
		( new Hooks() )->onContributionsToolLinks(
			$testUser->getUser()->getId(),
			$testUser->getUser()->getUserPage(),
			$tools,
			$specialPage
		);

		$this->assertArrayNotHasKey( 'nuke', $tools );
	}

	public function testContributionsIPRange() {
		$sysop = $this->getTestSysop()->getUser();
		$performer = new UltimateAuthority( $sysop );

		$specialPage = $this->newSpecialContribsPage();
		$context = $specialPage->getContext();
		$context->setAuthority( $performer );

		$this->setUserLang( "qqx" );
		$tools = [];
		( new Hooks() )->onContributionsToolLinks(
			0,
			Title::makeTitle( NS_USER, '127.0.0.1/24' ),
			$tools,
			$specialPage
		);

		$this->assertArrayNotHasKey( 'nuke', $tools );
	}

	public function testTags() {
		$arr = [];
		Hooks::onRegisterTags( $arr );

		// Only one tag should be added
		$this->assertCount( 1, $arr );
		// All tags should be lowercase
		foreach ( $arr as $tag ) {
			$this->assertEquals( strtolower( $tag ), $tag );
		}
		// There should be a name and description defined for each tag
		foreach ( $arr as $tag ) {
			$this->setUserLang( 'en' );
			$this->assertTrue( wfMessage( 'tag-' . $tag )->exists() );
			$this->assertTrue( wfMessage( 'tag-' . $tag . '-description' )->exists() );
			$this->setUserLang( 'qqq' );
			$this->assertTrue( wfMessage( 'tag-' . $tag )->exists() );
			$this->assertTrue( wfMessage( 'tag-' . $tag . '-description' )->exists() );
		}
	}

}
