<?php

namespace MediaWiki\Extension\Nuke\Test\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Nuke\Form\SpecialNukeHTMLFormUIRenderer;
use MediaWiki\Extension\Nuke\NukeContext;
use MediaWiki\Extension\Nuke\SpecialNuke;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 *
 * @covers \MediaWiki\Extension\Nuke\SpecialNuke
 * @covers \MediaWiki\Extension\Nuke\NukeContext
 */
class SpecialNukeTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	/**
	 * Create a new SpecialNuke instance for testing.
	 *
	 * @param bool $withIPLookup
	 * @return SpecialNuke
	 */
	protected function newSpecialPage( bool $withIPLookup = true ): SpecialNuke {
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
			$withIPLookup ? $services->getService( 'NukeIPLookup' ) : null,
		);
	}

	public function testGetTempAccounts() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		$specialPage = TestingAccessWrapper::newFromObject( $this->newSpecialPage() );
		$context = $specialPage->getNukeContextFromRequest(
			new FauxRequest( [], true )
		);
		$adminUser = $this->getTestSysop();
		$permissionManager = $this->getServiceContainer()->getPermissionManager();
		$oldPermissions = $permissionManager->getUserPermissions( $adminUser->getUser() );
		$permissionManager->overrideUserRightsForTesting(
			$adminUser->getUser(),
			array_merge( $oldPermissions, [ 'checkuser-temporary-account-no-preference' ] )
		);

		$this->assertCount( 0, $specialPage->getTempAccounts( $context ) );

		$this->enableAutoCreateTempUser();

		$this->assertCount( 0, $specialPage->getTempAccounts( $context ) );

		$ip = '1.2.3.4';
		RequestContext::getMain()->getRequest()->setIP( $ip );
		RequestContext::getMain()->setUser( $adminUser->getUser() );
		RequestContext::getMain()->setAuthority( $adminUser->getAuthority() );
		$context = $specialPage->getNukeContextFromRequest(
			new FauxRequest( [ 'target' => $ip ], true )
		);
		$testTempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )->getUser();
		$this->editPage( 'Target1', 'test', "", NS_MAIN, $testTempUser );

		$this->assertCount( 1, $specialPage->getTempAccounts( $context ) );

		// Without the service, the list should be completely empty.
		$specialPage = TestingAccessWrapper::newFromObject( $this->newSpecialPage( false ) );
		$this->assertCount( 0, $specialPage->getTempAccounts( $context ) );

		// Without permissions, the list should be completely empty.
		$permissionManager->overrideUserRightsForTesting( $adminUser->getUser(), $oldPermissions );
		$this->assertCount( 0, $specialPage->getTempAccounts( $context ) );
	}

	public function testUIRenderer() {
		$specialPage = TestingAccessWrapper::newFromObject( $this->newSpecialPage() );

		$uiTypes = [
			'htmlform' => SpecialNukeHTMLFormUIRenderer::class,
		];

		foreach ( $uiTypes as $type => $class ) {
			// Check if changing the global variable works
			$this->overrideConfigValue( 'NukeUIType', $type );
			$context = $specialPage->getNukeContextFromRequest(
				new FauxRequest( [], true )
			);
			$this->assertInstanceOf( $class, $specialPage->getUIRenderer( $context ) );

			// Check if changing the request variable works
			$this->overrideConfigValue( 'NukeUIType', null );
			$context = $specialPage->getNukeContextFromRequest(
				new FauxRequest( [ 'nukeUI' => $type ], true )
			);
			$this->assertInstanceOf( $class, $specialPage->getUIRenderer( $context ) );
		}
	}

	/**
	 * Test that the NukeContext is constructed with the proper page size values.
	 *
	 * Note that negative values are now allowed, and the UI will generate search notices.
	 */
	public function testGetNukeContextFromRequestPageSize() {
		$specialPage = TestingAccessWrapper::newFromObject( $this->newSpecialPage() );

		// Test default values.
		$context1 = $specialPage->getNukeContextFromRequest( new FauxRequest( [], true ) );
		$this->assertSame( 0, $context1->getMinPageSize() );
		// The maxPageSize is derived from the configuration (and will be >= 0).
		$this->assertGreaterThanOrEqual( 0, $context1->getMaxPageSize() );

		// Test setting valid minPageSize and maxPageSize.
		$context2 = $specialPage->getNukeContextFromRequest( new FauxRequest( [
			'minPageSize' => '100',
			'maxPageSize' => '1000',
		], true ) );
		$this->assertSame( 100, $context2->getMinPageSize() );
		$this->assertSame( 1000, $context2->getMaxPageSize() );

		// Test with negative values.
		$context3 = $specialPage->getNukeContextFromRequest( new FauxRequest( [
			'minPageSize' => '-1',
			'maxPageSize' => '-1',
		], true ) );
		// In this diff, negative values are allowed (and will cause search notices).
		$this->assertSame( -1, $context3->getMinPageSize() );
		$this->assertSame( -1, $context3->getMaxPageSize() );
	}

	/**
	 * Test the new search notice functionality of NukeContext::calculateSearchNotices().
	 */
	public function testCalculateSearchNotices() {
		// Case 1: Both values positive but min > max.
		$context = new NukeContext( [
			'requestContext'   => RequestContext::getMain(),
			'minPageSize'      => 2000,
			'maxPageSize'      => 1000,
			'originalPages'    => []
		] );
		$notices = $context->calculateSearchNotices();
		// Since neither page size is negative or zero (maxPageSize is positive),
		// the only notice should be about the minimum exceeding the maximum.
		$this->assertContains( 'nuke-searchnotice-minmorethanmax', $notices );
		$this->assertCount( 1, $notices );

		// Case 2: maxPageSize is negative, minPageSize positive.
		$context = new NukeContext( [
			'requestContext'   => RequestContext::getMain(),
			'minPageSize'      => 100,
			'maxPageSize'      => -1,
			'originalPages'    => []
		] );
		$notices = $context->calculateSearchNotices();
		$this->assertContains( 'nuke-searchnotice-negmax', $notices );
		$this->assertCount( 1, $notices );

		// Case 3: minPageSize is negative, maxPageSize positive.
		$context = new NukeContext( [
			'requestContext'   => RequestContext::getMain(),
			'minPageSize'      => -50,
			'maxPageSize'      => 1000,
			'originalPages'    => []
		] );
		$notices = $context->calculateSearchNotices();
		$this->assertContains( 'nuke-searchnotice-negmin', $notices );
		$this->assertCount( 1, $notices );

		// Case 4: Both values are negative.
		$context = new NukeContext( [
			'requestContext'   => RequestContext::getMain(),
			'minPageSize'      => -1,
			'maxPageSize'      => -1,
			'originalPages'    => []
		] );
		$notices = $context->calculateSearchNotices();
		// The method will add a notice for max (<= 0) and one for min (< 0).
		$this->assertContains( 'nuke-searchnotice-negmax', $notices );
		$this->assertContains( 'nuke-searchnotice-negmin', $notices );
		$this->assertCount( 2, $notices );

		// Case 5: Both values valid and compatible.
		$context = new NukeContext( [
			'requestContext'   => RequestContext::getMain(),
			'minPageSize'      => 100,
			'maxPageSize'      => 1000,
			'originalPages'    => []
		] );
		$notices = $context->calculateSearchNotices();
		$this->assertSame( [], $notices );
	}
}
