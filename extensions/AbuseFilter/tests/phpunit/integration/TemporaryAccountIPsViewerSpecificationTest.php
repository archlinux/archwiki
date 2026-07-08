<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\AbuseFilter\TemporaryAccountIPsViewerSpecification;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\TemporaryAccountIPsViewerSpecification
 */
class TemporaryAccountIPsViewerSpecificationTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	/**
	 * @dataProvider isSatisfiedByDataProvider
	 */
	public function testIsSatisfiedBy(
		bool $expected,
		bool $tempUsersAreKnown,
		bool $hasPermissionManager,
		bool $hasAccess
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		if ( $tempUsersAreKnown ) {
			$this->enableAutoCreateTempUser();
		} else {
			$this->disableAutoCreateTempUser( [ 'known' => false ] );
		}

		$status = $this->createMock( CheckUserPermissionStatus::class );
		$status
			->method( 'isGood' )
			->willReturn( $hasAccess );

		$performer = $this->createMock( Authority::class );

		$manager = null;
		if ( $hasPermissionManager ) {
			$manager = $this->createMock( CheckUserPermissionManager::class );
			$manager
				->method( 'canAccessTemporaryAccountIPAddresses' )
				->with( $performer )
				->willReturn( $status );
		}

		$services = $this->getServiceContainer();
		$sut = new TemporaryAccountIPsViewerSpecification(
			$services->getTempUserConfig(),
			$manager
		);

		$this->assertEquals( $expected, $sut->isSatisfiedBy( $performer ) );
	}

	public static function isSatisfiedByDataProvider(): array {
		return [
			'When temp users are not known' => [
				'expected' => false,
				'tempUsersAreKnown' => false,
				'hasPermissionManager' => true,
				'hasAccess' => true,
			],
			'When the permission manager is not available' => [
				'expected' => false,
				'tempUsersAreKnown' => true,
				'hasPermissionManager' => false,
				'hasAccess' => true,
			],
			'When access is forbidden' => [
				'expected' => false,
				'tempUsersAreKnown' => true,
				'hasPermissionManager' => true,
				'hasAccess' => false,
			],
			'When access is granted' => [
				'expected' => true,
				'tempUsersAreKnown' => true,
				'hasPermissionManager' => true,
				'hasAccess' => true,
			],
		];
	}
}
