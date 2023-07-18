<?php

declare( strict_types = 1 );

use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;

/**
 * @covers LinkHolderArray
 */
class LinkHolderArrayIntegrationTest extends MediaWikiLangTestCase {

	/**
	 * @dataProvider provideIsBig
	 * @covers LinkHolderArray::isBig
	 *
	 * @param int $size
	 * @param int $global
	 * @param bool $expected
	 */
	public function testIsBig( int $size, int $global, bool $expected ) {
		$this->overrideConfigValue( MainConfigNames::LinkHolderBatchSize, $global );
		$linkHolderArray = new LinkHolderArray(
			$this->createMock( Parser::class ),
			$this->createMock( ILanguageConverter::class ),
			$this->createHookContainer()
		);
		$linkHolderArray->size = $size;

		$this->assertSame( $expected, $linkHolderArray->isBig() );
	}

	public function provideIsBig() {
		yield [ 0, 0, false ];
		yield [ 0, 1, false ];
		yield [ 1, 0, true ];
		yield [ 1, 1, false ];
	}

	/**
	 * @dataProvider provideMakeHolder_withNsText
	 * @covers LinkHolderArray::makeHolder
	 *
	 * @param bool $isExternal
	 * @param string $expected
	 */
	public function testMakeHolder_withNsText(
		bool $isExternal,
		string $expected
	) {
		$link = new LinkHolderArray(
			$this->createMock( Parser::class ),
			$this->createMock( ILanguageConverter::class ),
			$this->createHookContainer()
		);
		$parser = $this->createMock( Parser::class );
		$parser->method( 'nextLinkID' )->willReturn( 9 );
		$link->parent = $parser;
		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedDBkey' )->willReturn( 'Talk:Dummy' );
		$title->method( 'getNamespace' )->willReturn( 1234 );
		$title->method( 'isExternal' )->willReturn( $isExternal );

		$this->assertSame( 0, $link->size );
		$result = $link->makeHolder(
			$title,
			'test1 text',
			'test2 trail',
			'test3 prefix'
		);
		$this->assertSame( $expected, $result );
		$this->assertSame( 1, $link->size );

		if ( $isExternal ) {
			$this->assertArrayEquals(
				[
					9 => [
						'title' => $title,
						'text' => 'test3 prefixtest1 texttest',
						'pdbk' => 'Talk:Dummy',
					],
				],
				$link->interwikis
			);
			$this->assertCount( 0, $link->internals );
		} else {
			$this->assertArrayEquals(
				[
					1234 => [
						9 => [
							'title' => $title,
							'text' => 'test3 prefixtest1 texttest',
							'pdbk' => 'Talk:Dummy',
						],
					],
				],
				$link->internals
			);
			$this->assertCount( 0, $link->interwikis );
		}
	}

	public function provideMakeHolder_withNsText() {
		yield [
			false,
			'<!--LINK\'" 1234:9-->2 trail',
		];
		yield [
			true,
			'<!--IWLINK\'" 9-->2 trail',
		];
	}
}
