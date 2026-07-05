<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use LogicException;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\EditBox\AceEditBoxBuilder;
use MediaWiki\Extension\AbuseFilter\EditBox\CodeMirrorEditBoxBuilder;
use MediaWiki\Extension\AbuseFilter\EditBox\EditBoxBuilderFactory;
use MediaWiki\Extension\AbuseFilter\EditBox\PlainEditBoxBuilder;
use MediaWiki\Extension\AbuseFilter\KeywordsManager;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Editbox\EditBoxBuilderFactory
 */
class EditBoxBuilderFactoryTest extends MediaWikiUnitTestCase {

	private function getFactory( bool $isCodeEditorLoaded, bool $isCodeMirrorLoaded = false ): EditBoxBuilderFactory {
		return new EditBoxBuilderFactory(
			$this->createMock( AbuseFilterPermissionManager::class ),
			$this->createMock( KeywordsManager::class ),
			$isCodeEditorLoaded,
			$isCodeMirrorLoaded
		);
	}

	/**
	 * @covers \MediaWiki\Extension\AbuseFilter\EditBox\EditBoxBuilder
	 * @dataProvider provideNewEditBoxBuilder
	 * @param bool $isCodeEditorLoaded
	 * @param bool $isCodeMirrorLoaded
	 */
	public function testNewEditBoxBuilder( bool $isCodeEditorLoaded, bool $isCodeMirrorLoaded = false ) {
		$builder = $this->getFactory( $isCodeEditorLoaded, $isCodeMirrorLoaded )->newEditBoxBuilder(
			$this->createMock( MessageLocalizer::class ),
			$this->createMock( Authority::class ),
			$this->createMock( OutputPage::class )
		);
		if ( $isCodeEditorLoaded ) {
			$this->assertInstanceOf( AceEditBoxBuilder::class, $builder );
		} elseif ( $isCodeMirrorLoaded ) {
			$this->assertInstanceOf( CodeMirrorEditBoxBuilder::class, $builder );
		} else {
			$this->assertInstanceOf( PlainEditBoxBuilder::class, $builder );
		}
	}

	public static function provideNewEditBoxBuilder(): array {
		return [
			[ true, true ],
			[ true, false ],
			[ false, true ],
			[ false, false ]
		];
	}

	public function testNewPlainBoxBuilder() {
		$this->assertInstanceOf(
			PlainEditBoxBuilder::class,
			$this->getFactory( false )->newPlainBoxBuilder(
				$this->createMock( MessageLocalizer::class ),
				$this->createMock( Authority::class ),
				$this->createMock( OutputPage::class )
			)
		);
	}

	public function testNewAceBoxBuilder() {
		$this->assertInstanceOf(
			AceEditBoxBuilder::class,
			$this->getFactory( true )->newAceBoxBuilder(
				$this->createMock( MessageLocalizer::class ),
				$this->createMock( Authority::class ),
				$this->createMock( OutputPage::class )
			)
		);
	}

	public function testNewAceBoxBuilder__invalid() {
		$this->expectException( LogicException::class );
		$this->getFactory( false )->newAceBoxBuilder(
			$this->createMock( MessageLocalizer::class ),
			$this->createMock( Authority::class ),
			$this->createMock( OutputPage::class )
		);
	}

	public function testNewCodeMirrorBoxBuilder() {
		$this->assertInstanceOf(
			CodeMirrorEditBoxBuilder::class,
			$this->getFactory( false, true )->newCodeMirrorBoxBuilder(
				$this->createMock( MessageLocalizer::class ),
				$this->createMock( Authority::class ),
				$this->createMock( OutputPage::class )
			)
		);
	}

	public function testNewCodeMirrorBoxBuilder__invalid() {
		$this->expectException( LogicException::class );
		$this->getFactory( false )->newCodeMirrorBoxBuilder(
			$this->createMock( MessageLocalizer::class ),
			$this->createMock( Authority::class ),
			$this->createMock( OutputPage::class )
		);
	}
}
