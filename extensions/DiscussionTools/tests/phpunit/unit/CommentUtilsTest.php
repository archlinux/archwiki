<?php

namespace MediaWiki\Extension\DiscussionTools\Tests\Unit;

use MediaWiki\Extension\DiscussionTools\CommentUtils;
use MediaWiki\Extension\DiscussionTools\Tests\TestUtils;
use MediaWikiUnitTestCase;

/**
 * @group DiscussionTools
 * @covers \MediaWiki\Extension\DiscussionTools\CommentUtils
 */
class CommentUtilsTest extends MediaWikiUnitTestCase {

	use TestUtils;

	/**
	 * @dataProvider provideLinearWalk
	 */
	public function testLinearWalk( string $name, string $htmlPath, string $expectedPath ) {
		$html = static::getHtml( $htmlPath );
		$doc = static::createDocument( $html );
		$expected = static::getJson( $expectedPath );

		$actual = [];
		CommentUtils::linearWalk( $doc, static function ( $event, $node ) use ( &$actual ) {
			// Different versions of PHP can give different cases for nodeName (T415942)
			$actual[] = $event . ' ' . strtolower( $node->nodeName );
		} );

		$actualBackwards = [];
		CommentUtils::linearWalkBackwards( $doc, static function ( $event, $node ) use ( &$actualBackwards ) {
			$actualBackwards[] = $event . ' ' . strtolower( $node->nodeName );
		} );

		// Optionally write updated content to the JSON files
		if ( getenv( 'DISCUSSIONTOOLS_OVERWRITE_TESTS' ) ) {
			static::overwriteJsonFile( $expectedPath, $actual );
		}

		static::assertEquals( $expected, $actual, $name );

		$expectedBackwards = array_map(
			static fn ( $a ) => ( $a[0] === 'e' ? 'leave' : 'enter' ) . substr( $a, 5 ),
			array_reverse( $expected )
		);
		static::assertEquals( $expectedBackwards, $actualBackwards, $name . ' (backwards)' );
	}

	public static function provideLinearWalk(): array {
		return static::getJson( '../cases/linearWalk.json' );
	}
}
