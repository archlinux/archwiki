<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Consequences\Consequence;

use MediaWiki\Extension\AbuseFilter\ActionSpecifier;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Tag;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Tag
 * @covers ::__construct
 */
class TagTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::execute
	 */
	public function testExecute() {
		$tagsToAdd = [ 'tag1', 'tag2' ];
		$accountName = 'foobar';
		$tagger = $this->createMock( ChangeTagger::class );
		$addTags = function ( ActionSpecifier $specs, $tags ) use ( $tagsToAdd, $accountName ) {
			$this->assertSame( $tagsToAdd, $tags );
			$this->assertSame( $accountName, $specs->getAccountName() );
		};
		$tagger->expects( $this->once() )->method( 'addTags' )->willReturnCallback( $addTags );
		$tag = new Tag(
			$this->createMock( Parameters::class ),
			$accountName,
			$tagsToAdd,
			$tagger
		);
		$this->assertTrue( $tag->execute() );
	}
}
