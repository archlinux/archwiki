<?php

namespace MediaWiki\Extension\DiscussionTools\Tests;

use GlobalVarConfig;
use MediaWiki\Extension\DiscussionTools\CommentUtils;
use MediaWiki\MediaWikiServices;

/**
 * @group DiscussionTools
 * @covers \MediaWiki\Extension\DiscussionTools\CommentUtils
 */
class CommentUtilsTest extends IntegrationTestCase {
	/**
	 * @dataProvider provideIsSingleCommentSignedBy
	 */
	public function testIsSingleCommentSignedBy(
		string $msg, string $title, string $username, string $html, bool $expected
	) {
		$doc = static::createDocument( $html );
		$container = static::getThreadContainer( $doc );

		$config = static::getJson( "../data/enwiki-config.json" );
		$data = static::getJson( "../data/enwiki-data.json" );
		$this->setupEnv( $config, $data );
		$title = MediaWikiServices::getInstance()->getTitleParser()->parseTitle( $title );
		$parser = static::createParser( $data );

		$threadItemSet = $parser->parse( $container, $title );
		$isSigned = CommentUtils::isSingleCommentSignedBy( $threadItemSet, $username, $container );
		static::assertEquals( $expected, $isSigned, $msg );
	}

	public static function provideIsSingleCommentSignedBy(): array {
		return static::getJson( '../cases/isSingleCommentSignedBy.json' );
	}

	/**
	 * @covers \MediaWiki\Extension\DiscussionTools\CommentUtils::getTitleFromUrl
	 * @dataProvider provideGetTitleFromUrl
	 */
	public function testGetTitleFromUrl( $expected, $input, $config ) {
		static::assertEquals(
			$expected,
			CommentUtils::getTitleFromUrl( $input, $config )
		);
	}

	public static function provideGetTitleFromUrl() {
		// TODO: Test with different configs.
		$config = new GlobalVarConfig();
		$GLOBALS['wgArticlePath'] = '/wiki/$1';

		yield 'null-string' => [ null, 'Foo', $config ];
		yield 'null-path' => [ null, 'path/Foo', $config ];
		yield 'null-wiki-path' => [ null, 'wiki/Foo', $config ];
		yield 'simple-path' => [ 'Foo', 'site/wiki/Foo', $config ];
		yield 'simple-cgi' => [ 'Foo', 'site/w/index.php?title=Foo', $config ];
		yield 'viewing-path' => [ 'Foo', 'site/wiki/Foo?action=view', $config ];
		yield 'viewing-cgi' => [ 'Foo', 'site/w/index.php?title=Foo&action=view', $config ];
		yield 'editing-path' => [ 'Foo', 'site/wiki/Foo?action=edit', $config ];
		yield 'editing-cgi' => [ 'Foo', 'site/w/index.php?title=Foo&action=edit', $config ];

		yield 'repeated question-mark' => [ 'Foo', 'site/wiki/Foo?Gosh?This?Path?Is?Bad', $config ];
	}
}
