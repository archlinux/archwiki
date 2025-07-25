<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\Block\DatabaseBlockStoreFactory;
use MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPager;
use MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPager
 */
class PreliminaryCheckPagerTest extends MediaWikiIntegrationTestCase {

	public function testGetQueryInfoFiltersIPsFromTargets() {
		$registry = $this->createMock( ExtensionRegistry::class );
		$registry->method( 'isLoaded' )->willReturn( true );

		$tokenQueryManager = $this->createMock( TokenQueryManager::class );
		$tokenQueryManager->method( 'getDataFromRequest' )->willReturn( [
			'targets' => [ 'UserA', 'UserB', '1.2.3.4' ]
		] );

		$preliminaryCheckService = new PreliminaryCheckService(
			$this->createMock( IConnectionProvider::class ),
			$registry,
			$this->createNoOpMock( UserGroupManagerFactory::class ),
			$this->createNoOpMock( DatabaseBlockStoreFactory::class ),
			'testwiki'
		);

		$services = $this->getServiceContainer();
		$pager = new PreliminaryCheckPager( RequestContext::getMain(),
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			$tokenQueryManager,
			$registry,
			$preliminaryCheckService,
			$this->getServiceContainer()->getUserFactory()
		);

		$result = $pager->getQueryInfo();

		$expected = [
			'tables' => 'localuser',
			'fields' => [
				'lu_name',
				'lu_wiki',
			],
			'conds' => [ 'lu_name' => [ 'UserA', 'UserB' ] ]
		];
		$this->assertSame( $expected, $result );
	}

	public function testGetIndexFieldLocal() {
		$services = $this->getServiceContainer();
		$pager = new PreliminaryCheckPager(
			RequestContext::getMain(),
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$this->createMock( ExtensionRegistry::class ),
			$this->createMock( PreliminaryCheckService::class ),
			$services->getUserFactory()
		);
		$this->assertEquals( 'user_name', $pager->getIndexfield() );
	}

	public function testGetIndexFieldGlobal() {
		$services = $this->getServiceContainer();
		$pager = $this->getMockBuilder( PreliminaryCheckPager::class )
			->setConstructorArgs( [ RequestContext::getMain(),
				$services->getLinkRenderer(),
				$services->getNamespaceInfo(),
				$services->get( 'CheckUserTokenQueryManager' ),
				$this->createMock( ExtensionRegistry::class ),
				$this->createMock( PreliminaryCheckService::class ),
				$services->getUserFactory()
			 ] )
			->onlyMethods( [ 'isGlobalCheck' ] )
			->getMock();

		$pager->method( 'isGlobalCheck' )->willReturn( true );
		$this->assertEquals( [ [ 'lu_name', 'lu_wiki' ] ], $pager->getIndexfield() );
	}
}
