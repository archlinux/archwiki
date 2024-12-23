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

	public function testNormal() {
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

	public function testNoPermission() {
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

	public function testIPRange() {
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

}
