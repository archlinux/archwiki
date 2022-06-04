<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use BadMethodCallException;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\EditBox\AceEditBoxBuiler;
use MediaWiki\Extension\AbuseFilter\EditBox\EditBoxBuilderFactory;
use MediaWiki\Extension\AbuseFilter\EditBox\PlainEditBoxBuiler;
use MediaWiki\Extension\AbuseFilter\KeywordsManager;
use MediaWikiUnitTestCase;
use MessageLocalizer;
use OutputPage;
use User;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Editbox\EditBoxBuilderFactory
 */
class EditBoxBuilderFactoryTest extends MediaWikiUnitTestCase {

	/**
	 * @param bool $isCodeEditorLoaded
	 * @return EditBoxBuilderFactory
	 */
	private function getFactory( bool $isCodeEditorLoaded ): EditBoxBuilderFactory {
		return new EditBoxBuilderFactory(
			$this->createMock( AbuseFilterPermissionManager::class ),
			$this->createMock( KeywordsManager::class ),
			$isCodeEditorLoaded
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::newEditBoxBuilder
	 * @covers \MediaWiki\Extension\AbuseFilter\EditBox\EditBoxBuilder::__construct
	 * @dataProvider provideNewEditBoxBuilder
	 * @param bool $isCodeEditorLoaded
	 */
	public function testNewEditBoxBuilder( bool $isCodeEditorLoaded ) {
		$builder = $this->getFactory( $isCodeEditorLoaded )->newEditBoxBuilder(
			$this->createMock( MessageLocalizer::class ),
			$this->createMock( User::class ),
			$this->createMock( OutputPage::class )
		);
		$isCodeEditorLoaded
			? $this->assertInstanceOf( AceEditBoxBuiler::class, $builder )
			: $this->assertInstanceOf( PlainEditBoxBuiler::class, $builder );
	}

	public function provideNewEditBoxBuilder(): array {
		return [
			[ true ],
			[ false ]
		];
	}

	/**
	 * @covers ::newPlainBoxBuilder
	 */
	public function testNewPlainBoxBuilder() {
		$this->assertInstanceOf(
			PlainEditBoxBuiler::class,
			$this->getFactory( false )->newPlainBoxBuilder(
				$this->createMock( MessageLocalizer::class ),
				$this->createMock( User::class ),
				$this->createMock( OutputPage::class )
			)
		);
	}

	/**
	 * @covers ::newAceBoxBuilder
	 */
	public function testNewAceBoxBuilder() {
		$this->assertInstanceOf(
			AceEditBoxBuiler::class,
			$this->getFactory( true )->newAceBoxBuilder(
				$this->createMock( MessageLocalizer::class ),
				$this->createMock( User::class ),
				$this->createMock( OutputPage::class )
			)
		);
	}

	/**
	 * @covers ::newAceBoxBuilder
	 */
	public function testNewAceBoxBuilder__invalid() {
		$this->expectException( BadMethodCallException::class );
		$this->getFactory( false )->newAceBoxBuilder(
			$this->createMock( MessageLocalizer::class ),
			$this->createMock( User::class ),
			$this->createMock( OutputPage::class )
		);
	}
}
