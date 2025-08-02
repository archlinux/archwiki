<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\HookHandler\GlobalBlockingHandler;
use MediaWiki\CheckUser\Jobs\UpdateUserCentralIndexJob;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\GlobalBlockingHandler
 * @group Database
 * @group CheckUser
 */
class GlobalBlockingHandlerWithDatabaseRowsTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;

	private static User $user;

	protected function setUp(): void {
		parent::setUp();
		ConvertibleTimestamp::setFakeTime( '20230506070809' );
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

	private function getCheckUserPrivateEventsHandler(): CheckUserPrivateEventsHandler {
		return new CheckUserPrivateEventsHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getReadOnlyMode(),
			$this->getServiceContainer()->get( 'UserAgentClientHintsManager' ),
			$this->getServiceContainer()->getJobQueueGroup(),
			$this->getServiceContainer()->getConnectionProvider()
		);
	}

	private function getMockGlobalBlockInstance(): GlobalBlock {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetName' )
			->willReturn( self::$user->getName() );
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

	public function testRetroactiveAutoblockWhenLocalUserNotAttached() {
		// Mock that the CentralIdLookup service never considers any users attached
		$mockCentralIdLookup = $this->createMock( CentralIdLookup::class );
		$mockCentralIdLookup->method( 'centralIdFromName' )
			->with( self::$user->getName(), CentralIdLookup::AUDIENCE_RAW )
			->willReturn( self::$user->getId() );
		$mockCentralIdLookup->method( 'isAttached' )
			->willReturnCallback( function ( $user, $wikiID ) {
				$this->assertSame( WikiMap::getCurrentWikiId(), $wikiID );
				$this->assertTrue( self::$user->equals( $user ) );
				return false;
			} );
		$this->setService( 'CentralIdLookup', $mockCentralIdLookup );
		// Call the method under test
		$globalBlock = $this->getMockGlobalBlockInstance();
		$this->commonTestRetroactiveAutoblockWhenNoIpsFound( $globalBlock );
	}

	/** @dataProvider provideRetroactiveAutoblockWhenIpFound */
	public function testRetroactiveAutoblockWhenIpFound( $limit, $expectedIps ) {
		$ips = [];
		$globalBlock = $this->getMockGlobalBlockInstance();
		$returnValue = $this->getObjectUnderTest()->onGlobalBlockingGetRetroactiveAutoblockIPs(
			$globalBlock, $limit, $ips
		);
		$this->assertArrayEquals( $expectedIps, $ips );
		$this->assertFalse( $returnValue );
	}

	public static function provideRetroactiveAutoblockWhenIpFound() {
		return [
			'Limit of 1' => [ 1, [ '1.2.3.9' ] ],
			'Limit of 2' => [ 2, [ '1.2.3.9', '1.2.3.2' ] ],
			'Limit of 3' => [ 3, [ '1.2.3.9', '1.2.3.2', '1.2.3.5' ] ],
			'Limit of 4' => [ 4, [ '1.2.3.9', '1.2.3.2', '1.2.3.5', '1.2.3.4' ] ],
			'Limit of 5' => [ 5, [ '1.2.3.9', '1.2.3.2', '1.2.3.5', '1.2.3.4' ] ],
		];
	}

	public function addDBDataOnce() {
		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		$user = $this->getTestUser()->getUser();
		// Add an autocreate action to the DB
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$privateEventHandler = $this->getCheckUserPrivateEventsHandler();
		$privateEventHandler->onLocalUserCreated( $user, true );
		// Add an edit to the DB by the $user
		ConvertibleTimestamp::setFakeTime( '20230405060709' );
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.5' );
		$this->editPage( $this->getExistingTestPage(), 'testing1234', '', NS_MAIN, $user );
		// Add a log event to the DB by the $user
		ConvertibleTimestamp::setFakeTime( '20230405060710' );
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.2' );
		$logEntry = new ManualLogEntry( 'phpunit', 'test' );
		$logEntry->setPerformer( $this->getTestUser()->getUserIdentity() );
		$logEntry->setTarget( $this->getExistingTestPage()->getTitle() );
		$logEntry->setComment( 'A very good reason' );
		$logEntry->publish( $logEntry->insert() );
		// Add another private event to the DB for the $user using the same IP as the log event
		ConvertibleTimestamp::setFakeTime( '20230405060711' );
		$privateEventHandler->onUser__mailPasswordInternal( $user, '1.2.3.2', $user );
		// Add another private event to the DB for the $user with a different IP
		ConvertibleTimestamp::setFakeTime( '20230405060712' );
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.9' );
		$inject_html = '';
		$privateEventHandler->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.9' ), $inject_html, $user->getName()
		);
		// Create a private event which is not related to the $user
		ConvertibleTimestamp::setFakeTime( '20230407060708' );
		RequestContext::getMain()->getRequest()->setIP( '8.7.6.5' );
		$privateEventHandler->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '8.7.6.5' ), $inject_html,
			$this->getMutableTestUser()->getUserIdentity()->getName()
		);
		ConvertibleTimestamp::setFakeTime( '20230506070809' );
		// Run jobs to cause inserts to the cuci_user and cuci_wiki_map tables
		DeferredUpdates::doUpdates();
		$this->runJobs( [], [ 'type' => UpdateUserCentralIndexJob::TYPE ] );
		self::$user = $user;
		ConvertibleTimestamp::setFakeTime( false );
	}
}
