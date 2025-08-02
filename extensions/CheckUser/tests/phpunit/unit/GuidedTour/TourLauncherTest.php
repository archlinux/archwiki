<?php

namespace MediaWiki\CheckUser\Tests\Unit\GuidedTour;

use MediaWiki\CheckUser\GuidedTour\TourLauncher;
use MediaWiki\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\GuidedTour\TourLauncher
 */
class TourLauncherTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;

	public function testMakeTourLinkWhenGuidedTourNotInstalled() {
		// Simulate that the GuidedTour extension is not installed.
		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->with( 'GuidedTour' )
			->willReturn( false );
		/** @var TourLauncher $objectUnderTest */
		$objectUnderTest = $this->newServiceInstance( TourLauncher::class, [
			'extensionRegistry' => $mockExtensionRegistry,
		] );
		$this->assertSame(
			'',
			$objectUnderTest->makeTourLink( 'tour', $this->createMock( LinkTarget::class ) ),
			'No link should be generated when the GuidedTour extension is not installed'
		);
	}

	/** @dataProvider provideMakeTourLink */
	public function testMakeTourLink( $tourName, $expectedExtraAttribs ) {
		// Simulate that the GuidedTour extension is installed.
		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->with( 'GuidedTour' )
			->willReturn( true );
		// Create a mock LinkRenderer that expects the mock arguments including the
		// expected extra attributes.
		$mockLinkTarget = $this->createMock( LinkTarget::class );
		$mockLinkRenderer = $this->createMock( LinkRenderer::class );
		$mockLinkRenderer->expects( $this->once() )
			->method( 'makeLink' )
			->with(
				$mockLinkTarget,
				null,
				$expectedExtraAttribs,
				[]
			)
			->willReturn( 'test link' );
		// Call the method under test with the given tour name.
		/** @var TourLauncher $objectUnderTest */
		$objectUnderTest = $this->newServiceInstance( TourLauncher::class, [
			'extensionRegistry' => $mockExtensionRegistry,
			'linkRenderer' => $mockLinkRenderer,
		] );
		$this->assertSame(
			'test link', $objectUnderTest->makeTourLink( $tourName, $mockLinkTarget )
		);
	}

	public static function provideMakeTourLink() {
		return [
			'Investigate tour' => [
				SpecialInvestigate::TOUR_INVESTIGATE,
				[ 'class' => [ 'ext-checkuser-investigate-reset-guided-tour' ] ],
			],
			'Investigate form tour' => [
				SpecialInvestigate::TOUR_INVESTIGATE_FORM,
				[ 'class' => [
					'ext-checkuser-investigate-reset-guided-tour', 'ext-checkuser-investigate-reset-form-guided-tour'
				] ],
			],
		];
	}
}
