<?php

namespace MediaWiki\Extension\MultimediaViewer\Tests;

use MediaWiki\Extension\MultimediaViewer\Hooks;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use SkinTemplate;

/**
 * @covers \MediaWiki\Extension\MultimediaViewer\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	public function newHooksInstance() {
		return new Hooks(
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getSpecialPageFactory()
		);
	}

	public static function provideOnBeforePageDisplay() {
		return [
			'no files' => [ 'Main Page', 0, false ],
			'with files' => [ 'Main Page', 1, true ],
			'special with files' => [ 'Special:ListFiles', 0, true ],
			'special no files' => [ 'Special:Watchlist', 0, false ],
		];
	}

	/**
	 * @dataProvider provideOnBeforePageDisplay
	 */
	public function testOnBeforePageDisplay( $pagename, $fileCount, $modulesExpected ) {
		$skin = new SkinTemplate();
		$output = $this->createMock( OutputPage::class );
		$output->method( 'getTitle' )->willReturn( Title::newFromText( $pagename ) );
		$output->method( 'getFileSearchOptions' )->willReturn( array_fill( 0, $fileCount, null ) );
		$output->expects( $this->exactly( $modulesExpected ? 1 : 0 ) )->method( 'addModules' );
		$this->newHooksInstance()->onBeforePageDisplay( $output, $skin );
	}
}
