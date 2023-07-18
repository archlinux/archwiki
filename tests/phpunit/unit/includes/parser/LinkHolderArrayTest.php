<?php

declare( strict_types = 1 );

use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;

/**
 * @covers LinkHolderArray
 */
class LinkHolderArrayTest extends MediaWikiUnitTestCase {

	/**
	 * @covers LinkHolderArray::merge
	 */
	public function testMerge() {
		$link1 = new LinkHolderArray(
			$this->createMock( Parser::class ),
			$this->createMock( ILanguageConverter::class ),
			$this->createHookContainer()
		);
		$link2 = new LinkHolderArray(
			$this->createMock( Parser::class ),
			$this->createMock( ILanguageConverter::class ),
			$this->createHookContainer()
		);

		$link1->internals = [
			100 => [
				10 => 'dummy entries 1',
				11 => 'dummy entries 1',
			],
			101 => [
				10 => 'dummy entries 1',
				11 => 'dummy entries 1',
			],
		];
		$link2->internals = [
			100 => [
				10 => 'dummy entries 2',
				12 => 'dummy entries 2',
			],
			102 => [
				10 => 'dummy entries 2',
				12 => 'dummy entries 2',
			],
		];
		$link1->interwikis = [
			10 => [
				'dummy' => 'dummy interwikis 1',
				'dummy interwikis 1' => 'dummy interwikis 1',
			],
			18 => [
				'dummy' => 'dummy interwikis 1',
				'dummy entries 1' => 'dummy interwikis 1',
			],
		];
		$link2->interwikis = [
			10 => [
				'dummy' => 'dummy interwikis 2',
				'dummy interwikis 2' => 'dummy interwikis 2',
			],
			19 => [
				'dummy' => 'dummy interwikis 2',
				'dummy interwikis 2' => 'dummy interwikis 2',
			],
		];

		$link1->size = 123;
		$link2->size = 321;

		$link1->merge( $link2 );

		$this->assertArrayEquals(
			[
				100 => [
					10 => 'dummy entries 1',
					11 => 'dummy entries 1',
					12 => 'dummy entries 2',
				],
				101 => [
					10 => 'dummy entries 1',
					11 => 'dummy entries 1',
				],
				102 => [
					10 => 'dummy entries 2',
					12 => 'dummy entries 2',
				],
			],
			$link1->internals
		);

		$this->assertArrayEquals(
			[
				100 => [
					10 => 'dummy entries 2',
					12 => 'dummy entries 2',
				],
				102 => [
					10 => 'dummy entries 2',
					12 => 'dummy entries 2',
				],
			],
			$link2->internals
		);

		$this->assertArrayEquals(
			[
				10 => [
					'dummy' => 'dummy interwikis 1',
					'dummy interwikis 1' => 'dummy interwikis 1',
				],
				18 => [
					'dummy' => 'dummy interwikis 1',
					'dummy entries 1' => 'dummy interwikis 1',
				],
				19 => [
					'dummy' => 'dummy interwikis 2',
					'dummy interwikis 2' => 'dummy interwikis 2',
				],
			],
			$link1->interwikis
		);
		$this->assertArrayEquals(
			[
				10 => [
					'dummy' => 'dummy interwikis 2',
					'dummy interwikis 2' => 'dummy interwikis 2',
				],
				19 => [
					'dummy' => 'dummy interwikis 2',
					'dummy interwikis 2' => 'dummy interwikis 2',
				],
			],
			$link2->interwikis
		);
		$this->assertSame( 127, $link1->size );
		$this->assertSame( 321, $link2->size );
	}

	/**
	 * @covers LinkHolderArray::clear
	 */
	public function testClear() {
		$linkHolderArray = new LinkHolderArray(
			$this->createMock( Parser::class ),
			$this->createMock( ILanguageConverter::class ),
			$this->createHookContainer()
		);
		$linkHolderArray->internals = [ 'dummy data' ];
		$linkHolderArray->interwikis = [ 'dummy data' ];
		$linkHolderArray->size = -123;
		$linkHolderArray->clear();

		$this->assertSame( [], $linkHolderArray->internals );
		$this->assertSame( [], $linkHolderArray->interwikis );
		$this->assertSame( 0, $linkHolderArray->size );
	}

	/**
	 * @dataProvider provideReplaceText
	 * @covers LinkHolderArray::replaceText
	 *
	 * @param string $input
	 * @param string $expected
	 */
	public function testReplaceText(
		string $input,
		string $expected
	) {
		$linkHolderArray = new LinkHolderArray(
			$this->createMock( Parser::class ),
			$this->createMock( ILanguageConverter::class ),
			$this->createHookContainer()
		);

		$this->assertSame(
			$input,
			$linkHolderArray->replaceText( $input )
		);
		$linkHolderArray->internals = [
			101 => [ 1 => [ 'text' => 'dummy 1' ] ],
			102 => [ 2 => [ 'text' => 'dummy 2' ] ],
		];
		$linkHolderArray->interwikis = [
			3 => [ 'text' => 'dummy 3' ],
			4 => [ 'text' => 'dummy 4' ],
		];

		$this->assertSame(
			$expected,
			$linkHolderArray->replaceText( $input )
		);
	}

	public function provideReplaceText() {
		yield [
			'<!--LINK\'" 101:9--> <!-- <!-- <!--IWLINK\'" 9-->',
			'<!--LINK\'" 101:9--> <!-- <!-- <!--IWLINK\'" 9-->',
		];
		yield [
			'<!--<!--<!--LINK\'" 101:1-->-->-->',
			'<!--<!--dummy 1-->-->',
		];
		yield [
			'<!--LINK\'" 101:9--><!--LINK\'" 101:1-->  <!--LINK\'" 102:2-->',
			'<!--LINK\'" 101:9-->dummy 1  dummy 2',
		];
		yield [
			'<!--IWLINK\'" 9--><!--IWLINK\'" 3-->  <!--IWLINK\'" 4-->',
			'<!--IWLINK\'" 9-->dummy 3  dummy 4',
		];
		yield [
			'<!--IWLINK\'" 9-->  <!--LINK\'" 101:1--><!--IWLINK\'" 4-->',
			'<!--IWLINK\'" 9-->  dummy 1dummy 4',
		];
		yield [
			'<!--LINK\'" 101:1--><!--LINK\'" 101:1--><!--LINK\'" 101:1-->',
			'dummy 1dummy 1dummy 1',
		];
		yield [
			'<!--IWLINK\'" 4--><!--IWLINK\'" 4--><!--IWLINK\'" 4-->',
			'dummy 4dummy 4dummy 4',
		];
	}

	/**
	 * @dataProvider provideReplace_external
	 * @covers LinkHolderArray::replace
	 * @covers LinkHolderArray::replaceInterwiki
	 *
	 * @param string $text
	 * @param string $extended
	 */
	public function testReplace_external(
		string $text,
		string $extended
	) {
		$link = new LinkHolderArray(
			$this->createMock( Parser::class ),
			$this->createMock( ILanguageConverter::class ),
			$this->createHookContainer()
		);
		$title = $this->createMock( Title::class );
		$title->method( 'isExternal' )->willReturn( true );

		$link->interwikis = [
			9 => [
				'title' => $title,
				'text' => 'text',
			],
		];
		$parser = $this->createMock( Parser::class );
		$link->parent = $parser;

		$parserOutput = $this->createMock( ParserOutput::class );
		$parser->method( 'getOutput' )->willReturn( $parserOutput );

		$linkRenderer = $this->createMock( LinkRenderer::class );
		$parser->method( 'getLinkRenderer' )->willReturn( $linkRenderer );

		$linkRenderer->method( 'makeLink' )->willReturn( 'new text' );
		$link->replace( $text );
		$this->assertSame( $extended, $text );
	}

	public function provideReplace_external() {
		yield [
			'dummy text',
			'dummy text',
		];
		yield [
			'<!--IWLINK\'" 9-->',
			'new text',
		];
		yield [
			'text1<!--IWLINK\'" 9--><!--IWLINK\'" 9-->  text2',
			'text1new textnew text  text2',
		];
	}
}
