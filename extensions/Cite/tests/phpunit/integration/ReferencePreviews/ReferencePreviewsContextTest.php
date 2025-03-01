<?php

namespace Cite\Tests\Integration\ReferencePreviews;

use Cite\ReferencePreviews\ReferencePreviewsContext;
use MediaWiki\Config\HashConfig;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Skin;

/**
 * @group Popups
 * @coversDefaultClass \Cite\ReferencePreviews\ReferencePreviewsContext
 * @license GPL-2.0-or-later
 */
class ReferencePreviewsContextTest extends MediaWikiIntegrationTestCase {

	/**
	 * Tests #shouldSendModuleToUser when the user is logged in and the reference previews feature
	 * is disabled.
	 *
	 * @covers ::isReferencePreviewsEnabled
	 * @dataProvider provideIsReferencePreviewsEnabled_requirements
	 */
	public function testIsReferencePreviewsEnabled_requirements( bool $setting, string $skinName, bool $expected ) {
		$config = new HashConfig( [
			'CiteReferencePreviews' => $setting,
			'CiteReferencePreviewsConflictingNavPopupsGadgetName' => '',
			'CiteReferencePreviewsConflictingRefTooltipsGadgetName' => '',
		] );
		$userOptLookup = $this->createNoOpMock( UserOptionsLookup::class );

		$user = $this->createMock( User::class );
		$user->method( 'isNamed' )->willReturn( false );

		$skin = $this->createMock( Skin::class );
		$skin->method( 'getSkinName' )->willReturn( $skinName );

		$this->assertSame( $expected,
			( new ReferencePreviewsContext(
				$config,
				$this->getServiceContainer()->getService( 'Cite.GadgetsIntegration' ),
				$userOptLookup
			) )
				->isReferencePreviewsEnabled( $user, $skin ),
			( $expected ? 'A' : 'No' ) . ' module is sent to the user.' );
	}

	public static function provideIsReferencePreviewsEnabled_requirements() {
		yield [ true, 'minerva', false ];
		yield [ false, 'minerva', false ];
		yield [ true, 'vector', true ];
		yield [ false, 'vector', false ];
	}

	/**
	 * Tests #shouldSendModuleToUser when the user is logged in and the reference previews feature
	 * is disabled.
	 *
	 * @covers ::isReferencePreviewsEnabled
	 * @dataProvider provideIsReferencePreviewsEnabled_userOptions
	 */
	public function testIsReferencePreviewsEnabled_userOptions( bool $isNamed, bool $option, bool $expected ) {
		$user = $this->createMock( User::class );
		$user->method( 'isNamed' )->willReturn( $isNamed );

		$userOptLookup = $this->createMock( UserOptionsLookup::class );
		$userOptLookup->method( 'getBoolOption' )
			->with( $user, ReferencePreviewsContext::REFERENCE_PREVIEWS_PREFERENCE_NAME )
			->willReturn( $option );

		$config = new HashConfig( [
			'CiteReferencePreviews' => true,
			'CiteReferencePreviewsConflictingNavPopupsGadgetName' => '',
			'CiteReferencePreviewsConflictingRefTooltipsGadgetName' => '',
		] );

		$skin = $this->createMock( Skin::class );

		$this->assertSame( $expected,
			( new ReferencePreviewsContext(
				$config,
				$this->getServiceContainer()->getService( 'Cite.GadgetsIntegration' ),
				$userOptLookup
			) )
				->isReferencePreviewsEnabled( $user, $skin ),
			( $expected ? 'A' : 'No' ) . ' module is sent to the user.' );
	}

	public static function provideIsReferencePreviewsEnabled_userOptions() {
		yield [ true, true, true ];
		yield [ true, false, false ];
		yield [ false, false, true ];
		yield [ false, true, true ];
	}

}
