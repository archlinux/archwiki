<?php

namespace MediaWiki\Extension\Nuke\Test\Integration;

use MediaWiki\Extension\Nuke\NukeConfigNames;
use MediaWiki\Extension\Nuke\SpecialNuke;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Nuke\SpecialNuke
 * @covers \MediaWiki\Extension\Nuke\Form\SpecialNukeCodexUIRenderer
 */
class SpecialNukeCodexTest extends SpecialNukeHTMLFormTest {

	use TempUserTestTrait;

	/**
	 * @before
	 * @return void
	 */
	public function nukeUISetUp() {
		$this->overrideConfigValue( NukeConfigNames::UIType, "codex" );
	}

	/**
	 * Check for validation messages. This also checks for the presence of
	 * Codex specifically for this test class.
	 *
	 * @param string $html
	 * @param array|null $messages
	 * @return void
	 */
	public function checkForValidationMessages( string $html, ?array $messages = [] ) {
		parent::checkForValidationMessages( $html, $messages );

		// Assert that we're using Codex.
		$this->assertStringContainsString( "cdx-", $html );
	}

	/**
	 * Check if Codex is being rendered on the page.
	 *
	 * @return void
	 */
	public function testCodex() {
		$admin = $this->getTestSysop()->getUser();
		$this->disableAutoCreateTempUser();
		$performer = new UltimateAuthority( $admin );
		[ $html ] = $this->executeSpecialPage( '', null, 'qqx', $performer );
		$this->assertStringContainsString( 'cdx-', $html );
	}

	/**
	 * @inheritDoc
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

		$this->assertEquals(
			2,
			substr_count( $html, '(nuke-editby: 127.0.0.1)' ),
			"Failed asserting that the IP address is shown twice in '$html'"
		);
	}

}
