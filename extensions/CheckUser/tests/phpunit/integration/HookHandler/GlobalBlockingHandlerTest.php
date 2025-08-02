<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\GlobalBlockingHandler;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\GlobalBlockingHandler
 * @group Database
 * @group CheckUser
 */
class GlobalBlockingHandlerTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;

	protected function setUp(): void {
		parent::setUp();
		// We need GlobalBlocking installed for these tests to work as expected
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );
		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	private function getObjectUnderTest(): GlobalBlockingHandler {
		return new GlobalBlockingHandler(
			$this->getServiceContainer()->getConnectionProvider(),
			$this->getServiceContainer()->getCentralIdLookup(),
			$this->getServiceContainer()->getActorStoreFactory()
		);
	}

	private function getMockGlobalBlockInstance( $target ): GlobalBlock {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetName' )
			->willReturn( $target );
		return $globalBlock;
	}

	private function commonTestRetroactiveAutoblockWhenNoIpsFound( GlobalBlock $globalBlock ) {
		$ips = [];
		$returnValue = $this->getObjectUnderTest()->onGlobalBlockingGetRetroactiveAutoblockIPs(
			$globalBlock, 100, $ips
		);
		$this->assertCount( 0, $ips );
		$this->assertTrue( $returnValue );
	}

	public function testRetroactiveAutoblockWhenNoDefinedTarget() {
		$globalBlock = $this->getMockGlobalBlockInstance( '' );
		$this->commonTestRetroactiveAutoblockWhenNoIpsFound( $globalBlock );
	}

	public function testRetroactiveAutoblockWhenNoCentralIdExists() {
		$globalBlock = $this->getMockGlobalBlockInstance( 'UnknownUser1' );
		$this->commonTestRetroactiveAutoblockWhenNoIpsFound( $globalBlock );
	}

	public function testRetroactiveAutoblockWhenNoActionsInCentralIndexes() {
		$globalBlock = $this->getMockGlobalBlockInstance( $this->getTestUser()->getUserIdentity()->getName() );
		$this->commonTestRetroactiveAutoblockWhenNoIpsFound( $globalBlock );
	}
}
