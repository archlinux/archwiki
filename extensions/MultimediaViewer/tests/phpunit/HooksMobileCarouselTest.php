<?php

namespace MediaWiki\Extension\MultimediaViewer\Tests;

use MediaWiki\Extension\MultimediaViewer\Hooks;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\MultimediaViewer\Hooks
 * @group Database
 */
class HooksMobileCarouselTest extends MediaWikiIntegrationTestCase {

	private function newHooksInstance(): Hooks {
		return new class(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getSpecialPageFactory(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getPageProps(),
			null
		) extends Hooks {
			public array $stubThumbs = [];
			public string $currentRequestSkinName = 'minerva';
			public bool $pageExcludedFromMobileCarousel = false;

			protected function isMobileFrontendView(): bool {
				return true;
			}

			protected function getCurrentRequestSkinName(): string {
				return $this->currentRequestSkinName;
			}

			protected function extractImages( OutputPage $out ): array {
				return $this->stubThumbs;
			}

			protected function isPageExcludedFromMobileCarousel( OutputPage $out ): bool {
				return $this->pageExcludedFromMobileCarousel;
			}
		};
	}

	private static function makeFakeThumb( string $name ): array {
		return [
			'title' => "File:$name.jpg",
			'href' => "/wiki/File:$name.jpg",
			'src' => "/$name.jpg",
			'srcset' => "/$name-240px.jpg 2x",
			'width' => 120,
			'height' => 90,
			'alt' => $name,
		];
	}

	public function testOnBeforePageDisplayInjectsCarouselMarkupWhenEnabled(): void {
		$this->overrideConfigValue( 'MediaViewerMobileCarousel', true );

		$user = User::newFromName( 'HooksMobileCarouselUser' );
		$title = Title::newFromText( 'Main Page' );
		$title->setContentModel( CONTENT_MODEL_WIKITEXT );
		$skin = new SkinTemplate();
		$output = $this->createMock( OutputPage::class );
		$output->method( 'getTitle' )->willReturn( $title );
		$output->method( 'getUser' )->willReturn( $user );
		$output->expects( $this->once() )
			->method( 'addModules' )
			->with( [ 'mmv.carousel' ] );
		$output->expects( $this->once() )
			->method( 'prependHTML' )
			->with( $this->callback( static function ( string $html ): bool {
				return str_contains( $html, 'id="mmv-carousel-root"' ) &&
					str_contains( $html, 'class="mw-mmv-wrapper mmv-carousel"' ) &&
					str_contains( $html, 'class="mmv-carousel__items"' );
			} ) );

		$hooks = $this->newHooksInstance();
		$hooks->stubThumbs = [
			self::makeFakeThumb( 'A' ),
			self::makeFakeThumb( 'B' ),
			self::makeFakeThumb( 'C' ),
		];

		$hooks->onBeforePageDisplay( $output, $skin );
	}

	public function testOnBeforePageDisplayInjectsCarouselMarkupForAnonymousReaders(): void {
		$this->overrideConfigValue( 'MediaViewerMobileCarousel', true );

		$user = User::newFromName( '127.0.0.1', false );
		$title = Title::newFromText( 'Main Page' );
		$title->setContentModel( CONTENT_MODEL_WIKITEXT );
		$skin = new SkinTemplate();
		$output = $this->createMock( OutputPage::class );
		$output->method( 'getTitle' )->willReturn( $title );
		$output->method( 'getUser' )->willReturn( $user );
		$output->expects( $this->once() )
			->method( 'addModules' )
			->with( [ 'mmv.carousel' ] );
		$output->expects( $this->once() )
			->method( 'prependHTML' )
			->with( $this->stringContains( 'id="mmv-carousel-root"' ) );

		$hooks = $this->newHooksInstance();
		$hooks->stubThumbs = [
			self::makeFakeThumb( 'A' ),
			self::makeFakeThumb( 'B' ),
			self::makeFakeThumb( 'C' ),
		];

		$hooks->onBeforePageDisplay( $output, $skin );
	}

	public function testOnBeforePageDisplaySkipsCarouselWhenDisabledByPreference(): void {
		$this->overrideConfigValue( 'MediaViewerMobileCarousel', true );

		$user = $this->getTestUser()->getUser();
		$this->getServiceContainer()->getUserOptionsManager()->setOption(
			$user,
			'disable_image_carousel',
			1
		);
		$user->saveSettings();

		$title = Title::newFromText( 'Main Page' );
		$title->setContentModel( CONTENT_MODEL_WIKITEXT );
		$skin = new SkinTemplate();
		$output = $this->createMock( OutputPage::class );
		$output->method( 'getTitle' )->willReturn( $title );
		$output->method( 'getUser' )->willReturn( $user );
		$output->method( 'getRequest' )->willReturn( new FauxRequest( [ 'mmvBeta' => '1' ] ) );
		$output->expects( $this->never() )
			->method( 'addModules' );
		$output->expects( $this->never() )
			->method( 'prependHTML' );

		$this->newHooksInstance()->onBeforePageDisplay( $output, $skin );
	}

	public function testOnBeforePageDisplaySkipsCarouselWhenExcludedByBehaviorSwitch(): void {
		$this->overrideConfigValue( 'MediaViewerMobileCarousel', true );

		$user = User::newFromName( 'HooksMobileCarouselExcludedUser' );
		$title = Title::newFromText( 'Main Page' );
		$title->setContentModel( CONTENT_MODEL_WIKITEXT );
		$skin = new SkinTemplate();
		$output = $this->createMock( OutputPage::class );
		$output->method( 'getTitle' )->willReturn( $title );
		$output->method( 'getUser' )->willReturn( $user );
		$output->method( 'getRequest' )->willReturn( new FauxRequest() );
		$output->expects( $this->never() )
			->method( 'addModules' );
		$output->expects( $this->never() )
			->method( 'prependHTML' );

		$hooks = $this->newHooksInstance();
		$hooks->pageExcludedFromMobileCarousel = true;
		$hooks->stubThumbs = [
			self::makeFakeThumb( 'A' ),
			self::makeFakeThumb( 'B' ),
			self::makeFakeThumb( 'C' ),
		];

		$hooks->onBeforePageDisplay( $output, $skin );
	}

	public function testOnGetPreferencesAddsDisableImageCarouselPreference(): void {
		$this->overrideConfigValue( 'MediaViewerMobileCarousel', true );

		$prefs = [];

		$this->newHooksInstance()->onGetPreferences( User::newFromName( 'PreferenceTestUser' ), $prefs );

		$this->assertArrayHasKey( 'disable_image_carousel', $prefs );
		$this->assertSame( 'toggle', $prefs['disable_image_carousel']['type'] );
		$this->assertSame(
			'multimediaviewer-disable-image-carousel-pref',
			$prefs['disable_image_carousel']['label-message']
		);
		$this->assertSame( 'rendering/files', $prefs['disable_image_carousel']['section'] );
		$this->assertSame( 'toggle', $prefs['disable_image_carousel']['type'] );
	}

	public function testOnGetPreferencesSkipsDisableImageCarouselPreferenceWhenConfigDisabled(): void {
		$this->overrideConfigValue( 'MediaViewerMobileCarousel', false );

		$prefs = [];

		$this->newHooksInstance()->onGetPreferences( User::newFromName( 'PreferenceTestUser' ), $prefs );

		$this->assertArrayHasKey( 'multimediaviewer-enable', $prefs );
		$this->assertArrayNotHasKey( 'disable_image_carousel', $prefs );
	}

	public function testOnGetPreferencesAddsDisableImageCarouselPreferenceAsHiddenForNonMinerva(): void {
		$this->overrideConfigValue( 'MediaViewerMobileCarousel', true );

		$prefs = [];
		$hooks = $this->newHooksInstance();
		$hooks->currentRequestSkinName = 'vector-2022';

		$hooks->onGetPreferences( User::newFromName( 'PreferenceTestUser' ), $prefs );

		$this->assertArrayHasKey( 'disable_image_carousel', $prefs );
		$this->assertSame( 'hidden', $prefs['disable_image_carousel']['type'] );
	}

	public function testOnMakeGlobalVariablesScriptDisablesClicksWhenCarouselPreferenceIsSet(): void {
		$user = $this->getTestUser()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption( $user, 'multimediaviewer-enable', 1 );
		$userOptionsManager->setOption( $user, 'disable_image_carousel', 1 );
		$user->saveSettings();

		$output = $this->createMock( OutputPage::class );
		$output->method( 'getUser' )->willReturn( $user );

		$vars = [];
		$this->newHooksInstance()->onMakeGlobalVariablesScript( $vars, $output );

		$this->assertFalse( $vars['wgMediaViewerOnClick'] );
	}
}
