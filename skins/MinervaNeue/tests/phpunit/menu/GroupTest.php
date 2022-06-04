<?php

namespace Tests\MediaWiki\Minerva\Menu;

use DomainException;
use MediaWiki\Minerva\Menu\Entries\IMenuEntry;
use MediaWiki\Minerva\Menu\Group;

/**
 * @group MinervaNeue
 * @coversDefaultClass \MediaWiki\Minerva\Menu\Group
 */
class GroupTest extends \MediaWikiIntegrationTestCase {
	/** @var string[] */
	private $homeComponent = [
		'text' => 'Home',
		'href' => '/Main_page',
		'class' => 'mw-ui-icon mw-ui-icon-before mw-ui-icon-home',
		'data-event-name' => 'home',
		'icon' => null,
	];

	/** @var string[] */
	private $nearbyComponent = [
		'text' => 'Nearby',
		'href' => '/wiki/Special:Nearby',
		'class' => 'mw-ui-icon mw-ui-icon-before mw-ui-icon-nearby',
		'icon' => null
	];

	/**
	 * @covers ::getEntries
	 */
	public function testItShouldntHaveEntriesByDefault() {
		$menu = new Group( 'p-test' );

		$this->assertEmpty( $menu->getEntries() );
	}

	/**
	 * @covers ::insert
	 * @covers ::search
	 * @covers ::getEntries
	 * @covers \MediaWiki\Minerva\Menu\Entries\MenuEntry::addComponent
	 */
	public function testInsertingAnEntry() {
		$menu = new Group( 'p-test' );
		$menu->insert( 'home' )
			->addComponent(
				$this->homeComponent['text'],
				$this->homeComponent['href'],
				$this->homeComponent['class'],
				[
					'data-event-name' => $this->homeComponent['data-event-name']
				]
			);

		$expectedEntries = [
			[
				'name' => 'home',
				'components' => [ $this->homeComponent ],
			],
		];

		$this->assertEquals( $expectedEntries, $menu->getEntries() );
	}

	/**
	 * @covers ::insert
	 * @covers ::search
	 * @covers ::getEntries
	 * @covers \MediaWiki\Minerva\Menu\Entries\MenuEntry::addComponent
	 */
	public function testInsertingAnEntryAfterAnother() {
		$menu = new Group( 'p-test' );
		$menu->insert( 'home' )
			->addComponent(
				$this->homeComponent['text'],
				$this->homeComponent['href'],
				$this->homeComponent['class'],
				[
					'data-event-name' => $this->homeComponent['data-event-name']
				]
			);
		$menu->insert( 'another_home' )
			->addComponent(
				$this->homeComponent['text'],
				$this->homeComponent['href'],
				$this->homeComponent['class'],
				[
					'data-event-name' => $this->homeComponent['data-event-name']
				]
			);
		$menu->insertAfter( 'home', 'nearby' )
			->addComponent(
				$this->nearbyComponent['text'],
				$this->nearbyComponent['href'],
				$this->nearbyComponent['class']
			);

		$expectedEntries = [
			[
				'name' => 'home',
				'components' => [ $this->homeComponent ],
			],
			[
				'name' => 'nearby',
				'components' => [ $this->nearbyComponent ],
			],
			[
				'name' => 'another_home',
				'components' => [ $this->homeComponent ],
			],
		];

		$this->assertEquals( $expectedEntries, $menu->getEntries() );
	}

	/**
	 * @covers ::insertAfter
	 * @covers ::search
	 * @covers \MediaWiki\Minerva\Menu\Entries\MenuEntry::addComponent
	 */
	public function testInsertAfterWhenTargetEntryDoesntExist() {
		$menu = new Group( 'p-test' );
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'The "home" entry doesn\'t exist.' );
		$menu->insertAfter( 'home', 'nearby' )
			->addComponent(
				$this->nearbyComponent['text'],
				$this->nearbyComponent['href'],
				$this->nearbyComponent['class']
			);
	}

	/**
	 * @covers ::insertAfter
	 */
	public function testInsertAfterWithAnEntryWithAnExistingName() {
		$menu = new Group( 'p-test' );
		$menu->insert( 'home' );
		$menu->insert( 'car' );
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'The "car" entry already exists.' );
		$menu->insertAfter( 'home', 'car' );
	}

	/**
	 * @covers ::insert
	 */
	public function testInsertingAnEntryWithAnExistingName() {
		$menu = new Group( 'p-test' );
		$menu->insert( 'home' );
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'The "home" entry already exists.' );
		$menu->insert( 'home' );
	}

	/**
	 * @covers ::insert
	 * @covers ::insertAfter
	 */
	public function testInsertingAnEntryAfterAnotherOne() {
		$menu = new Group( 'p-test' );
		$menu->insert( 'first' );
		$menu->insert( 'last' );
		$menu->insertAfter( 'first', 'middle' );
		$items = $menu->getEntries();
		$this->assertCount( 3, $items );
		$this->assertSame( 'first', $items[0]['name'] );
		$this->assertSame( 'middle', $items[1]['name'] );
		$this->assertSame( 'last', $items[2]['name'] );
	}

	/**
	 * @covers ::insert
	 * @covers ::getEntries
	 * @covers \MediaWiki\Minerva\Menu\Entries\MenuEntry::addComponent
	 */
	public function testinsertingAnEntryWithMultipleComponents() {
		$authLoginComponent = [
			'text' => 'Phuedx (WMF)',
			'href' => '/wiki/User:Phuedx_(WMF)',
			'class' =>
				'mw-ui-icon mw-ui-icon-before mw-ui-icon-profile',
			'icon' => null,
		];
		$authLogoutComponent = [
			'text' => 'Logout',
			'href' => '/wiki/Special:UserLogout',
			'class' =>
				'mw-ui-icon mw-ui-icon-element secondary-logout',
			'icon' => null,
		];

		$menu = new Group( 'p-test' );
		$menu->insert( 'auth' )
			->addComponent(
				$authLoginComponent['text'],
				$authLoginComponent['href'],
				$authLoginComponent['class']
			)
			->addComponent(
				$authLogoutComponent['text'],
				$authLogoutComponent['href'],
				$authLogoutComponent['class']
			);

		$expectedEntries = [
			[
				'name' => 'auth',
				'components' => [
					$authLoginComponent,
					$authLogoutComponent
				],
			],
		];

		$this->assertEquals( $expectedEntries, $menu->getEntries() );
	}

	/**
	 * @covers ::insert
	 * @covers ::getEntries
	 * @covers \MediaWiki\Minerva\Menu\Entries\MenuEntry::addComponent
	 */
	public function testInsertingAJavascriptOnlyEntry() {
		$menu = new Group( 'p-test' );
		$menu->insert( 'nearby', $isJSOnly = true )
			->addComponent(
				$this->nearbyComponent['text'],
				$this->nearbyComponent['href'],
				$this->nearbyComponent['class']
			);

		$expectedEntries = [
			[
				'name' => 'nearby',
				'components' => [ $this->nearbyComponent ],
				'class' => 'jsonly'
			],
		];

		$this->assertEquals( $expectedEntries, $menu->getEntries() );
	}

	/**
	 * @covers ::getEntryByName
	 * @covers ::search
	 */
	public function testGetEntryByName() {
		$menu = new Group( 'p-test' );
		$menu->insert( 'home' )
			->addComponent(
				$this->homeComponent['text'],
				$this->homeComponent['href']
			);
		$this->assertInstanceOf( IMenuEntry::class, $menu->getEntryByName( 'home' ) );
	}

	/**
	 * @covers ::getEntryByName
	 * @covers ::search
	 */
	public function testGetEntryByNameException() {
		$menu = new Group( 'p-test' );
		$this->expectException( DomainException::class );
		$menu->getEntryByName( 'home' );
	}

}
