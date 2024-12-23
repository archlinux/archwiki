<?php

namespace MediaWiki\Extension\Scribunto\Tests;

use MediaWiki\Extension\Scribunto\Hooks;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWikiCoversValidator;
use Monolog\Test\TestCase;

/**
 * @covers \MediaWiki\Extension\Scribunto\Hooks
 */
class HooksTest extends TestCase {
	use MediaWikiCoversValidator;

	public static function provideContentHandlerDefaultModelFor() {
		return [
			[ 'Module:Foo', CONTENT_MODEL_SCRIBUNTO ],
			[ 'Module:Foo/doc', null ],
			[ 'Module:Foo/styles.css', 'sanitized-css', 'sanitized-css' ],
			[ 'Module:Foo.json', CONTENT_MODEL_JSON ],
			[ 'Module:Foo/subpage.json', CONTENT_MODEL_JSON ],
			[ 'Main Page', null ],
		];
	}

	/**
	 * @dataProvider provideContentHandlerDefaultModelFor
	 */
	public function testContentHandlerDefaultModelFor( $name, $expected,
		$before = null
	) {
		$title = Title::newFromText( $name );
		$model = $before;
		( new Hooks(
			MediaWikiServices::getInstance()->getMainConfig()
		) )->onContentHandlerDefaultModelFor( $title, $model );
		$this->assertSame( $expected, $model );
	}
}
