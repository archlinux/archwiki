<?php

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Specials\SpecialUnblock;
use MediaWiki\Title\Title;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Blocking
 * @group Database
 * @coversDefaultClass \MediaWiki\Specials\SpecialUnblock
 */
class SpecialUnblockTest extends SpecialPageTestBase {
	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
		return new SpecialUnblock(
			$services->getUnblockUserFactory(),
			$services->getBlockUtils(),
			$services->getUserNameUtils(),
			$services->getUserNamePrefixSearch(),
			$services->getWatchlistManager()
		);
	}

	protected function tearDown(): void {
		$this->db->delete( 'ipblocks', '*', __METHOD__ );
		parent::tearDown();
	}

	/**
	 * @dataProvider provideGetFields
	 * @covers ::getFields
	 */
	public function testGetFields( $target, $expected ) {
		$page = TestingAccessWrapper::newFromObject( $this->newSpecialPage() );
		$page->target = $target;
		$page->block = new DatabaseBlock( [
			'address' => '1.2.3.4',
			'by' => $this->getTestSysop()->getUser(),
		] );

		$fields = $page->getFields();
		$this->assertIsArray( $fields );
		foreach ( $expected as $fieldName ) {
			$this->assertArrayHasKey( $fieldName, $fields );
		}
	}

	public static function provideGetFields() {
		return [
			'No target specified' => [
				'',
				[ 'Target', 'Reason' ],
			],
			'Target is not blocked' => [
				'1.1.1.1',
				[ 'Target', 'Reason' ],
			],
			'Target is blocked' => [
				'1.2.3.4',
				[ 'Target', 'Reason', 'Name' ],
			],
		];
	}

	/**
	 * @dataProvider provideProcessUnblockErrors
	 * @covers ::execute
	 */
	public function testProcessUnblockErrors( $options, $expected ) {
		$performer = $this->getTestSysop()->getUser();

		$target = '1.1.1.1';
		if ( !empty( $options['block'] ) ) {
			$block = new DatabaseBlock( [
				'address' => $target,
				'by' => $performer,
				'hideName' => true,
			] );
			$this->getServiceContainer()->getDatabaseBlockStore()->insertBlock( $block );
		}

		if ( !empty( $options['readOnly'] ) ) {
			$this->overrideConfigValue( MainConfigNames::ReadOnly, true );
			$this->expectException( ReadOnlyError::class );
		}

		if ( isset( $options['permissions'] ) ) {
			$this->overrideUserPermissions( $performer, $options['permissions'] );
		}

		$request = new FauxRequest( [
			'wpTarget' => $target,
			'wpReason' => '',
		], true );
		[ $html, ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertStringContainsString( $expected, $html );
	}

	public static function provideProcessUnblockErrors() {
		return [
			'Target is not blocked' => [
				[
					'permissions' => [ 'block', 'hideuser' => true ],
				],
				'ipb_cant_unblock',
			],
			'Wrong permissions for unhiding user' => [
				[
					'block' => true,
					'permissions' => [ 'block', 'hideuser' => false ],
				],
				'unblock-hideuser',
			],
			'Delete block failed' => [
				[
					'block' => true,
					'permissions' => [ 'block', 'hideuser' ],
					'readOnly' => true,
				],
				'ipb_cant_unblock',
			],
		];
	}

	/**
	 * @covers ::execute
	 */
	public function testProcessUnblockErrorsUnblockSelf() {
		$performer = $this->getTestSysop()->getUser();

		$this->overrideUserPermissions( $performer, [ 'block', 'unblockself' => false ] );

		// Blocker must be different user for unblock self to be disallowed
		$blocker = $this->getTestUser()->getUser();
		$block = new DatabaseBlock( [
			'by' => $blocker,
			'address' => $performer,
		] );
		$this->getServiceContainer()->getDatabaseBlockStore()->insertBlock( $block );

		$request = new FauxRequest( [
			'wpTarget' => $performer->getName(),
			'wpReason' => '',
		], true );
		[ $html, ] = $this->executeSpecialPage( '', $request, 'qqx', $performer );

		$this->assertStringContainsString( 'ipbnounblockself', $html );
	}

	/**
	 * @covers ::execute
	 */
	public function testWatched() {
		$performer = $this->getTestSysop()->getUser();

		$target = '1.2.3.4';
		$block = new DatabaseBlock( [
			'by' => $performer,
			'address' => $target,
		] );
		$this->getServiceContainer()->getDatabaseBlockStore()->insertBlock( $block );

		$request = new FauxRequest( [
			'wpTarget' => $target,
			'wpReason' => '',
			'wpWatch' => '1',
		], true );
		$this->executeSpecialPage( '', $request, 'qqx', $performer );

		$userPage = Title::makeTitle( NS_USER, $target );
		$this->assertTrue( $this->getServiceContainer()->getWatchlistManager()
			->isWatched( $performer, $userPage ) );
	}
}
