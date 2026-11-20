<?php

namespace MediaWiki\Extension\VisualEditor\Tests\Integration;

use MediaWiki\MainConfigNames;
use SpecialPageTestBase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\VisualEditor\SpecialEditChecks
 */
class SpecialEditChecksTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			MainConfigNames::LanguageCode => 'de',
			MainConfigNames::UseDatabaseMessages => true,
			'VisualEditorEditCheck' => true,
		] );
	}

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'EditChecks' );
	}

	public static function provideOnWikiConfigIsEscaped() {
		// ',a:' inside the string value used to break the JS literal parser, and the
		// unparseable string was then output as raw HTML.
		$payload = ',a:<img src=x onerror=alert(1)>';
		return [
			'object config' => [ [ 'a' => $payload ] ],
			'string config' => [ $payload ],
		];
	}

	/**
	 * @dataProvider provideOnWikiConfigIsEscaped
	 */
	public function testOnWikiConfigIsEscaped( $ruleConfig ) {
		// The /en fallback page has the wikitext content model, so it bypasses JSON validation.
		$json = json_encode( [
			'textMatch' => [ 'matchItems' => [ 'r1' => [ 'config' => $ruleConfig ] ] ],
		] );
		$this->editPage( 'MediaWiki:Editcheck-config.json/en', $json );

		[ $html ] = $this->executeSpecialPage( '', null, 'de' );

		$this->assertStringContainsString( 'textMatch (r1)', $html );
		$this->assertStringNotContainsString( '<img src=x onerror', $html );
		$this->assertStringContainsString( '&lt;img src=x onerror', $html );
	}
}
