<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\Tests;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Math\MathWikibaseConnector;
use MediaWiki\Extension\Math\MathWikibaseInfo;
use MediaWiki\Extension\Math\SpecialMathWikibase;
use MediaWiki\Output\OutputPage;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\ItemId;

/**
 * @covers \MediaWiki\Extension\Math\SpecialMathWikibase
 */
class SpecialMathWikibaseTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseClient' );
	}

	private function buildOutput( string $label, string $description ): OutputPage {
		$info = new MathWikibaseInfo( new ItemId( 'Q1234' ) );
		$info->setLabel( $label );
		$info->setDescription( $description );

		$connector = $this->createMock( MathWikibaseConnector::class );
		$connector->method( 'buildURL' )->willReturn( 'https://repo.example/wiki/Q1234' );

		$context = new DerivativeContext( RequestContext::getMain() );
		$output = new OutputPage( $context );
		$context->setOutput( $output );
		$special = new SpecialMathWikibase( $connector );
		$special->setContext( $context );

		$special->buildPageRepresentation( $info, 'Q1234', $output );
		return $output;
	}

	public static function provideSubtitle(): array {
		return [
			'plain text' => [ 'mathematical constant: ratio', 'mathematical constant' ],
			'HTML is escaped' => [
				'<b>XSS-MARKER</b>: formula',
				'&lt;b&gt;XSS-MARKER&lt;/b&gt;',
			],
		];
	}

	/** @dataProvider provideSubtitle */
	public function testBuildPageRepresentationSubtitle( string $description, string $expected ): void {
		$output = $this->buildOutput( 'Label', $description );

		$this->assertSame(
			$expected,
			$output->getSubtitle(),
			'The part of the description before the colon should be escaped in the subtitle'
		);
	}

	public function testBuildPageRepresentationDoesNotParseTerms(): void {
		$output = $this->buildOutput( "[[LabelTarget]] ''label''", '[[TypeTarget]]: [[DescTarget]]' );
		$html = $output->getHTML();

		$this->assertStringContainsString( '[[LabelTarget]] &#039;&#039;label&#039;&#039;', $html );
		$this->assertStringContainsString( '[[TypeTarget]]', $html );
		$this->assertStringContainsString( '[[DescTarget]]', $html );
		$this->assertStringNotContainsString( '<i>label</i>', $html );
	}
}
