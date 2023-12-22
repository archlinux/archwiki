<?php

use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;

class CssContentHandlerTest extends MediaWikiLangTestCase {

	/**
	 * @dataProvider provideMakeRedirectContent
	 * @covers CssContentHandler::makeRedirectContent
	 */
	public function testMakeRedirectContent( int $namespace, string $title, $expected ) {
		$this->overrideConfigValues( [
			MainConfigNames::Server => '//example.org',
			MainConfigNames::Script => '/w/index.php',
		] );
		$ch = new CssContentHandler();
		$content = $ch->makeRedirectContent( Title::makeTitle( $namespace, $title ) );
		$this->assertInstanceOf( CssContent::class, $content );
		$this->assertEquals( $expected, $content->serialize( CONTENT_FORMAT_CSS ) );
	}

	/**
	 * Keep this in sync with CssContentTest::provideGetRedirectTarget()
	 */
	public static function provideMakeRedirectContent() {
		return [
			[
				NS_MEDIAWIKI,
				'MonoBook.css',
				"/* #REDIRECT */@import url(//example.org/w/index.php?title=MediaWiki:MonoBook.css&action=raw&ctype=text/css);"
			],
			[
				NS_USER,
				'FooBar/common.css',
				"/* #REDIRECT */@import url(//example.org/w/index.php?title=User:FooBar/common.css&action=raw&ctype=text/css);"
			],
			[
				NS_USER,
				'😂/unicode.css',
				'/* #REDIRECT */@import url(//example.org/w/index.php?title=User:%F0%9F%98%82/unicode.css&action=raw&ctype=text/css);'
			],
		];
		// phpcs:enable
	}
}
