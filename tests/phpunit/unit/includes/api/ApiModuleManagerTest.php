<?php

use MediaWiki\User\UserFactory;
use Psr\Container\ContainerInterface;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 * @covers ApiModuleManager
 * @group API
 * @group medium
 */
class ApiModuleManagerTest extends MediaWikiUnitTestCase {

	private function getModuleManager() {
		// getContext is called in ApiBase::__construct
		$apiMain = $this->createMock( ApiMain::class );
		$apiMain->method( 'getContext' )
			->willReturn( $this->createMock( RequestContext::class ) );

		$containerInterface = $this->createMock( ContainerInterface::class );
		// Only needs to be able to provide the services used in the tests below, we
		// don't need a full copy of MediaWikiServices's services. The only service
		// actually used is a UserFactory, for demonstration purposes
		$containerInterface->method( 'get' )
			->with( 'UserFactory' )
			->willReturn( $this->createMock( UserFactory::class ) );
		return new ApiModuleManager(
			$apiMain,
			new ObjectFactory( $containerInterface )
		);
	}

	public function newApiRsd( $main, $action ) {
		return new ApiRsd( $main, $action );
	}

	public function addModuleProvider() {
		return [
			'plain class' => [
				'rsd',
				'action',
				ApiRsd::class,
				null,
			],

			'with class and factory' => [
				'rsd',
				'action',
				ApiRsd::class,
				[ $this, 'newApiRsd' ],
			],

			'with spec (class only)' => [
				'rsd',
				'action',
				[
					'class' => ApiRsd::class
				],
				null,
			],

			'with spec' => [
				'rsd',
				'action',
				[
					'class' => ApiRsd::class,
					'factory' => [ $this, 'newApiRsd' ],
				],
				null,
			],

			'with spec (using services)' => [
				'rsd',
				'action',
				[
					'class' => ApiRsd::class,
					'factory' => static function ( ApiMain $main, $action, UserFactory $userFactory ) {
						// we don't actually need the UserFactory, just demonstrating
						return new ApiRsd( $main, $action );
					},
					'services' => [
						'UserFactory'
					],
				],
				null,
			]
		];
	}

	/**
	 * @dataProvider addModuleProvider
	 */
	public function testAddModule( $name, $group, $spec, $factory ) {
		if ( $factory ) {
			$this->hideDeprecated(
				ApiModuleManager::class . '::addModule with $class and $factory'
			);
		}

		$moduleManager = $this->getModuleManager();
		$moduleManager->addModule( $name, $group, $spec, $factory );

		$this->assertTrue( $moduleManager->isDefined( $name, $group ), 'isDefined' );
		$this->assertNotNull( $moduleManager->getModule( $name, $group, true ), 'getModule' );
	}

	public function addModulesProvider() {
		return [
			'empty' => [
				[],
				'action',
			],

			'simple' => [
				[
					'rsd' => ApiRsd::class,
					'logout' => ApiLogout::class,
				],
				'action',
			],

			'with factories' => [
				[
					'rsd' => [
						'class' => ApiRsd::class,
						'factory' => [ $this, 'newApiRsd' ],
					],
					'logout' => [
						'class' => ApiLogout::class,
						'factory' => static function ( ApiMain $main, $action ) {
							return new ApiLogout( $main, $action );
						},
					],
				],
				'action',
			],
		];
	}

	/**
	 * @dataProvider addModulesProvider
	 */
	public function testAddModules( array $modules, $group ) {
		$moduleManager = $this->getModuleManager();
		$moduleManager->addModules( $modules, $group );

		foreach ( array_keys( $modules ) as $name ) {
			$this->assertTrue( $moduleManager->isDefined( $name, $group ), 'isDefined' );
			$this->assertNotNull( $moduleManager->getModule( $name, $group, true ), 'getModule' );
		}

		$this->assertTrue( true ); // Don't mark the test as risky if $modules is empty
	}

	public function getModuleProvider() {
		$modules = [
			'disabled' => ApiDisabled::class,
			'disabled2' => [ 'class' => ApiDisabled::class ],
			'rsd' => [
				'class' => ApiRsd::class,
				'factory' => [ $this, 'newApiRsd' ],
			],
			'logout' => [
				'class' => ApiLogout::class,
				'factory' => static function ( ApiMain $main, $action ) {
					return new ApiLogout( $main, $action );
				},
			],
		];

		return [
			'legacy entry' => [
				$modules,
				'disabled',
				ApiDisabled::class,
			],

			'just a class' => [
				$modules,
				'disabled2',
				ApiDisabled::class,
			],

			'with factory' => [
				$modules,
				'rsd',
				ApiRsd::class,
			],

			'with closure' => [
				$modules,
				'logout',
				ApiLogout::class,
			],
		];
	}

	/**
	 * @covers ApiModuleManager::getModule
	 * @dataProvider getModuleProvider
	 */
	public function testGetModule( $modules, $name, $expectedClass ) {
		$moduleManager = $this->getModuleManager();
		$moduleManager->addModules( $modules, 'test' );

		// should return the right module
		$module1 = $moduleManager->getModule( $name, null, false );
		$this->assertInstanceOf( $expectedClass, $module1 );

		// should pass group check (with caching disabled)
		$module2 = $moduleManager->getModule( $name, 'test', true );
		$this->assertNotNull( $module2 );

		// should use cached instance
		$module3 = $moduleManager->getModule( $name, null, false );
		$this->assertSame( $module1, $module3 );

		// should not use cached instance if caching is disabled
		$module4 = $moduleManager->getModule( $name, null, true );
		$this->assertNotSame( $module1, $module4 );
	}

	/**
	 * @covers ApiModuleManager::getModule
	 */
	public function testGetModule_null() {
		$modules = [
			'rsd' => ApiRsd::class,
			'logout' => ApiLogout::class,
		];

		$moduleManager = $this->getModuleManager();
		$moduleManager->addModules( $modules, 'test' );

		$this->assertNull( $moduleManager->getModule( 'quux' ), 'unknown name' );
		$this->assertNull( $moduleManager->getModule( 'login', 'bla' ), 'wrong group' );
	}

	/**
	 * @covers ApiModuleManager::getNames
	 */
	public function testGetNames() {
		$fooModules = [
			'rsd' => ApiRsd::class,
			'logout' => ApiLogout::class,
		];

		$barModules = [
			'feedcontributions' => [ 'class' => ApiFeedContributions::class ],
			'feedrecentchanges' => [ 'class' => ApiFeedRecentChanges::class ],
		];

		$moduleManager = $this->getModuleManager();
		$moduleManager->addModules( $fooModules, 'foo' );
		$moduleManager->addModules( $barModules, 'bar' );

		$fooNames = $moduleManager->getNames( 'foo' );
		$this->assertArrayEquals( array_keys( $fooModules ), $fooNames );

		$allNames = $moduleManager->getNames();
		$allModules = array_merge( $fooModules, $barModules );
		$this->assertArrayEquals( array_keys( $allModules ), $allNames );
	}

	/**
	 * @covers ApiModuleManager::getNamesWithClasses
	 */
	public function testGetNamesWithClasses() {
		$fooModules = [
			'rsd' => ApiRsd::class,
			'logout' => ApiLogout::class,
		];

		$barModules = [
			'feedcontributions' => [ 'class' => ApiFeedContributions::class ],
			'feedrecentchanges' => [ 'class' => ApiFeedRecentChanges::class ],
		];

		$moduleManager = $this->getModuleManager();
		$moduleManager->addModules( $fooModules, 'foo' );
		$moduleManager->addModules( $barModules, 'bar' );

		$fooNamesWithClasses = $moduleManager->getNamesWithClasses( 'foo' );
		$this->assertArrayEquals( $fooModules, $fooNamesWithClasses );

		$allNamesWithClasses = $moduleManager->getNamesWithClasses();
		$allModules = array_merge( $fooModules, [
			'feedcontributions' => ApiFeedContributions::class,
			'feedrecentchanges' => ApiFeedRecentChanges::class,
		] );
		$this->assertArrayEquals( $allModules, $allNamesWithClasses );
	}

	/**
	 * @covers ApiModuleManager::getModuleGroup
	 */
	public function testGetModuleGroup() {
		$fooModules = [
			'rsd' => ApiRsd::class,
			'logout' => ApiLogout::class,
		];

		$barModules = [
			'feedcontributions' => [ 'class' => ApiFeedContributions::class ],
			'feedrecentchanges' => [ 'class' => ApiFeedRecentChanges::class ],
		];

		$moduleManager = $this->getModuleManager();
		$moduleManager->addModules( $fooModules, 'foo' );
		$moduleManager->addModules( $barModules, 'bar' );

		$this->assertEquals( 'foo', $moduleManager->getModuleGroup( 'rsd' ) );
		$this->assertEquals( 'bar', $moduleManager->getModuleGroup( 'feedrecentchanges' ) );
		$this->assertNull( $moduleManager->getModuleGroup( 'quux' ) );
	}

	/**
	 * @covers ApiModuleManager::getGroups
	 */
	public function testGetGroups() {
		$fooModules = [
			'rsd' => ApiRsd::class,
			'logout' => ApiLogout::class,
		];

		$barModules = [
			'feedcontributions' => [ 'class' => ApiFeedContributions::class ],
			'feedrecentchanges' => [ 'class' => ApiFeedRecentChanges::class ],
		];

		$moduleManager = $this->getModuleManager();
		$moduleManager->addModules( $fooModules, 'foo' );
		$moduleManager->addModules( $barModules, 'bar' );

		$groups = $moduleManager->getGroups();
		$this->assertArrayEquals( [ 'foo', 'bar' ], $groups );
	}

	/**
	 * @covers ApiModuleManager::getClassName
	 */
	public function testGetClassName() {
		$fooModules = [
			'rsd' => ApiRsd::class,
			'logout' => ApiLogout::class,
		];

		$barModules = [
			'feedcontributions' => [ 'class' => ApiFeedContributions::class ],
			'feedrecentchanges' => [ 'class' => ApiFeedRecentChanges::class ],
		];

		$moduleManager = $this->getModuleManager();
		$moduleManager->addModules( $fooModules, 'foo' );
		$moduleManager->addModules( $barModules, 'bar' );

		$this->assertEquals(
			ApiRsd::class,
			$moduleManager->getClassName( 'rsd' )
		);
		$this->assertEquals(
			ApiLogout::class,
			$moduleManager->getClassName( 'logout' )
		);
		$this->assertEquals(
			ApiFeedContributions::class,
			$moduleManager->getClassName( 'feedcontributions' )
		);
		$this->assertEquals(
			ApiFeedRecentChanges::class,
			$moduleManager->getClassName( 'feedrecentchanges' )
		);
		$this->assertFalse(
			$moduleManager->getClassName( 'nonexistentmodule' )
		);
	}

	public function testAddModuleWithIncompleteSpec() {
		$moduleManager = $this->getModuleManager();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( '$spec must define a class name' );
		$moduleManager->addModule(
			'logout',
			'action',
			[
				'factory' => static function ( ApiMain $main, $action ) {
					return new ApiLogout( $main, $action );
				},
			]
		);
	}
}
