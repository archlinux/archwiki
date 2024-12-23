<?php

namespace Cite\Tests\Integration\ReferencePreviews;

use Cite\ReferencePreviews\ReferencePreviewsGadgetsIntegration;
use InvalidArgumentException;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Gadgets\Gadget;
use MediaWiki\Extension\Gadgets\GadgetRepo;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \Cite\ReferencePreviews\ReferencePreviewsGadgetsIntegration
 * @license GPL-2.0-or-later
 */
class ReferencePreviewsGadgetsIntegrationTest extends MediaWikiIntegrationTestCase {
	/**
	 * Gadget name for testing
	 */
	private const NAV_POPUPS_GADGET_NAME = 'navigation-test';

	/**
	 * Helper constants for easier reading
	 */
	private const GADGET_ENABLED = true;

	/**
	 * Helper constants for easier reading
	 */
	private const GADGET_DISABLED = false;

	private function getConfig( ?string $gadgetName = self::NAV_POPUPS_GADGET_NAME ): Config {
		return new HashConfig( [
			ReferencePreviewsGadgetsIntegration::CONFIG_NAVIGATION_POPUPS_NAME => $gadgetName,
			ReferencePreviewsGadgetsIntegration::CONFIG_REFERENCE_TOOLTIPS_NAME => $gadgetName,
		] );
	}

	public function testConflictsWithNavPopupsGadgetIfGadgetsExtensionIsNotLoaded() {
		$this->assertFalse(
			( new ReferencePreviewsGadgetsIntegration(
				$this->getConfig(),
				null
			) )
				->isNavPopupsGadgetEnabled( $this->createNoOpMock( UserIdentity::class ) ),
			'No conflict is identified.'
		);
	}

	public function testConflictsWithNavPopupsGadgetIfGadgetNotExists() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Gadgets' );

		$gadgetRepoMock = $this->createMock( GadgetRepo::class );
		$gadgetRepoMock->expects( $this->once() )
			->method( 'getGadgetIds' )
			->willReturn( [] );

		$this->executeIsNavPopupsGadgetEnabled(
			$this->createNoOpMock( UserIdentity::class ),
			$this->getConfig(),
			$gadgetRepoMock,
			self::GADGET_DISABLED
		);
	}

	public function testConflictsWithNavPopupsGadgetIfGadgetExists() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Gadgets' );

		$user = $this->createMock( UserIdentity::class );

		$gadgetMock = $this->createMock( Gadget::class );
		$gadgetMock->expects( $this->once() )
			->method( 'isEnabled' )
			->with( $user )
			->willReturn( self::GADGET_ENABLED );

		$gadgetRepoMock = $this->createMock( GadgetRepo::class );
		$gadgetRepoMock->expects( $this->once() )
			->method( 'getGadgetIds' )
			->willReturn( [ self::NAV_POPUPS_GADGET_NAME ] );
		$gadgetRepoMock->expects( $this->once() )
			->method( 'getGadget' )
			->with( self::NAV_POPUPS_GADGET_NAME )
			->willReturn( $gadgetMock );

		$this->executeIsNavPopupsGadgetEnabled(
			$user,
			$this->getConfig(),
			$gadgetRepoMock,
			self::GADGET_ENABLED
		);
	}

	/**
	 * Test the edge case when GadgetsRepo::getGadget throws an exception
	 */
	public function testConflictsWithNavPopupsGadgetWhenGadgetNotExists() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Gadgets' );

		$gadgetRepoMock = $this->createMock( GadgetRepo::class );
		$gadgetRepoMock->expects( $this->once() )
			->method( 'getGadgetIds' )
			->willReturn( [ self::NAV_POPUPS_GADGET_NAME ] );
		$gadgetRepoMock->expects( $this->once() )
			->method( 'getGadget' )
			->with( self::NAV_POPUPS_GADGET_NAME )
			->willThrowException( new InvalidArgumentException() );

		$this->executeIsNavPopupsGadgetEnabled(
			$this->createNoOpMock( UserIdentity::class ),
			$this->getConfig(),
			$gadgetRepoMock,
			self::GADGET_DISABLED
		);
	}

	/**
	 * @dataProvider provideGadgetNamesWithSanitizedVersion
	 */
	public function testConflictsWithNavPopupsGadgetNameSanitization( string $gadgetName, string $sanitized ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'Gadgets' );

		$gadgetMock = $this->createMock( Gadget::class );
		$gadgetMock->expects( $this->once() )
			->method( 'isEnabled' )
			->willReturn( self::GADGET_ENABLED );

		$gadgetRepoMock = $this->createMock( GadgetRepo::class );
		$gadgetRepoMock->expects( $this->once() )
			->method( 'getGadgetIds' )
			->willReturn( [ $sanitized ] );
		$gadgetRepoMock->expects( $this->once() )
			->method( 'getGadget' )
			->with( $sanitized )
			->willReturn( $gadgetMock );

		$this->executeIsNavPopupsGadgetEnabled(
			$this->createNoOpMock( UserIdentity::class ),
			$this->getConfig( $gadgetName ),
			$gadgetRepoMock,
			self::GADGET_ENABLED
		);
	}

	public static function provideGadgetNamesWithSanitizedVersion() {
		yield [ ' Popups ', 'Popups' ];
		yield [ 'Navigation_popups-API', 'Navigation_popups-API' ];
		yield [ 'Navigation popups ', 'Navigation_popups' ];
	}

	private function executeIsNavPopupsGadgetEnabled(
		UserIdentity $user,
		Config $config,
		GadgetRepo $repoMock,
		bool $expected
	): void {
		$this->assertSame(
			$expected,
			( new ReferencePreviewsGadgetsIntegration(
				$config,
				$repoMock
			) )
				->isNavPopupsGadgetEnabled( $user ),
			( $expected ? 'A' : 'No' ) . ' conflict is identified.'
		);
	}
}
