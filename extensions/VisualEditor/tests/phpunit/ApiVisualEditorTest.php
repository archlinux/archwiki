<?php

namespace MediaWiki\Extension\VisualEditor\Tests;

use ApiTestCase;
use ApiVisualEditor;
use ExtensionRegistry;
use HashConfig;
use Wikimedia\ScopedCallback;

/**
 * @group medium
 *
 * @covers \ApiVisualEditor
 */
class ApiVisualEditorTest extends ApiTestCase {

	/** @var ScopedCallback|null */
	private $scopedCallback;

	protected function setUp(): void {
		parent::setUp();
		$this->scopedCallback = ExtensionRegistry::getInstance()->setAttributeForTest(
			'VisualEditorAvailableNamespaces',
			[ 'User' => true, 'Template_Talk' => true ]
		);
	}

	protected function tearDown(): void {
		$this->scopedCallback = null;
		parent::tearDown();
	}

	private function loadEditor( $overrideParams = [] ) {
		$params = array_merge( [
			'action' => 'visualeditor',
			'paction' => 'metadata',
			'page' => 'SomeTestPage',
		], $overrideParams );
		return $this->doApiRequestWithToken( $params );
	}

	public function testLoadEditorBasic() {
		$data = $this->loadEditor()[0]['visualeditor'];

		$this->assertSame( 'success', $data['result'] );

		$properties = [
			'notices',
			'copyrightWarning',
			'checkboxesDef',
			'checkboxesMessages',
			'protectedClasses',
			'basetimestamp',
			'starttimestamp',
			'oldid',
			'blockinfo',
			'canEdit',
			'content',
		];
		foreach ( $properties as $prop ) {
			$this->assertArrayHasKey( $prop, $data, "Result has key '$prop'" );
		}
	}

	/**
	 * @dataProvider provideLoadEditorPreload
	 */
	public function testLoadEditorPreload( $params, $expected ) {
		$this->assertSame(
			$expected,
			$this->loadEditor( $params )[0]['visualeditor']['content']
		);
	}

	public function provideLoadEditorPreload() {
		return [
			'load with preload content' => [
				[
					'preload' => 'UTPage',
					'paction' => 'wikitext',
				],
				'UTContent',
			],
			'load with preload via Special:MyLanguage' => [
				// NB UTPage isn't actually translated, so we get the same content back.
				[
					'preload' => 'Special:MyLanguage/UTPage',
					'paction' => 'wikitext',
				],
				'UTContent',
			]
		];
	}

	public function testIsAllowedNamespace() {
		$config = new HashConfig( [ 'VisualEditorAvailableNamespaces' => [
			0 => true,
			1 => false,
		] ] );
		$this->assertTrue( ApiVisualEditor::isAllowedNamespace( $config, 0 ) );
		$this->assertFalse( ApiVisualEditor::isAllowedNamespace( $config, 1 ) );
	}

	public function testGetAvailableNamespaceIds() {
		$config = new HashConfig( [ 'VisualEditorAvailableNamespaces' => [
			0 => true,
			1 => false,
			-1 => true,
			999999 => true,
			2 => false,
			'Template' => true,
			'Foobar' => true,
		] ] );
		$this->assertSame(
			[ -1, 0, 10, 11 ],
			ApiVisualEditor::getAvailableNamespaceIds( $config )
		);
	}

	public function testIsAllowedContentType() {
		$config = new HashConfig( [ 'VisualEditorAvailableContentModels' => [
			'on' => true,
			'off' => false,
		] ] );
		$this->assertTrue( ApiVisualEditor::isAllowedContentType( $config, 'on' ) );
		$this->assertFalse( ApiVisualEditor::isAllowedContentType( $config, 'off' ) );
		$this->assertFalse( ApiVisualEditor::isAllowedContentType( $config, 'unknown' ) );
	}

}
