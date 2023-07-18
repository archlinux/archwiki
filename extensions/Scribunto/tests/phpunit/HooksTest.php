<?php

namespace MediaWiki\Extension\Scribunto\Tests;

use MediaWiki\Extension\Scribunto\Hooks;
use MediaWikiCoversValidator;
use Monolog\Test\TestCase;
use Title;

/**
 * @covers \MediaWiki\Extension\Scribunto\Hooks
 */
class HooksTest extends TestCase {
	use MediaWikiCoversValidator;

	public function provideContentHandlerDefaultModelFor() {
		return [
			[ 'Module:Foo', CONTENT_MODEL_SCRIBUNTO, true ],
			[ 'Module:Foo/doc', null, true ],
			[ 'Module:Foo/styles.css', 'sanitized-css', true, 'sanitized-css' ],
			[ 'Module:Foo.json', CONTENT_MODEL_JSON, true ],
			[ 'Module:Foo/subpage.json', CONTENT_MODEL_JSON, true ],
			[ 'Main Page', null, true ],
		];
	}

	/**
	 * @dataProvider provideContentHandlerDefaultModelFor
	 */
	public function testContentHandlerDefaultModelFor( $name, $expected,
		$retVal, $before = null
	) {
		$title = Title::newFromText( $name );
		$model = $before;
		$ret = Hooks::contentHandlerDefaultModelFor( $title, $model );
		$this->assertSame( $retVal, $ret );
		$this->assertSame( $expected, $model );
	}
}
