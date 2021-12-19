<?php

namespace MediaWiki\Tests\PageImages;

use File;
use MediaWikiUnitTestCase;
use PageImages\PageImageCandidate;
use Title;

/**
 * @covers \PageImages\PageImageCandidate
 * @package MediaWiki\Tests\PageImages
 */
class PageImageCandidateTest extends MediaWikiUnitTestCase {

	/**
	 * @param int|bool $width
	 * @param int|bool $height
	 * @return File
	 */
	private function fileMock( $width, $height ): File {
		$title = $this->createMock( Title::class );
		$title->method( 'getDBKey' )->willReturn( 'Testing' );
		$file = $this->createMock( File::class );
		$file->method( 'getTitle' )->willReturn( $title );
		$file->method( 'getWidth' )->willReturn( $width );
		$file->method( 'getHeight' )->willReturn( $height );
		return $file;
	}

	public function provideTestCases() {
		yield 'file no width, no height' => [
			$this->fileMock( false, false ),
			[], 'Testing', 0, 0, 0
		];
		yield 'file with width and height' => [
			$this->fileMock( 42, 24 ),
			[], 'Testing', 42, 24, 0
		];
		yield 'file with width and height, handler, no width' => [
			$this->fileMock( 42, 24 ),
			[ 'handler' => [] ], 'Testing', 42, 24, 0
		];
		yield 'file with width and height, handler, with width' => [
			$this->fileMock( 42, 24 ),
			[ 'handler' => [ 'width' => 4224 ] ], 'Testing', 42, 24, 4224
		];
	}

	/**
	 * @dataProvider provideTestCases
	 * @param File $file
	 * @param array $params
	 * @param string $expectedName
	 * @param int $expectedWidth
	 * @param int $expectedHeight
	 * @param int $expectedHandlerWidth
	 */
	public function testNewFromFileAndParams(
		File $file,
		array $params,
		string $expectedName,
		int $expectedWidth,
		int $expectedHeight,
		int $expectedHandlerWidth
	) {
		$image = PageImageCandidate::newFromFileAndParams( $file, $params );
		$this->assertSame( $expectedName, $image->getFileName() );
		$this->assertSame( $expectedWidth, $image->getFullWidth() );
		$this->assertSame( $expectedHeight, $image->getFullHeight() );
		$this->assertSame( $expectedHandlerWidth, $image->getHandlerWidth() );
	}

	/**
	 * @dataProvider provideTestCases
	 * @param File $file
	 * @param array $params
	 */
	public function testSerializeDeserialize( File $file, array $params ) {
		$candidate = PageImageCandidate::newFromFileAndParams( $file, $params );
		$deserialized = PageImageCandidate::newFromArray( $candidate->jsonSerialize() );
		$this->assertSame( $candidate->getFileName(), $deserialized->getFileName() );
		$this->assertSame( $candidate->getFullHeight(), $deserialized->getFullHeight() );
		$this->assertSame( $candidate->getFullHeight(), $deserialized->getFullHeight() );
		$this->assertSame( $candidate->getHandlerWidth(), $deserialized->getHandlerWidth() );
	}
}
