<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Widgets;

use MediaWiki\CheckUser\CheckUser\Widgets\CIDRCalculator;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\CheckUser\Widgets\CIDRCalculator
 */
class CIDRCalculatorTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideToString */
	public function testToString( $config, $textToBeInHtml, $textNotToBeInHtml ) {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );
		Theme::setSingleton( new BlankTheme() );

		$context = new DerivativeContext( RequestContext::getMain() );
		$objectUnderTest = new CIDRCalculator( $context->getOutput(), $config );

		// Use type casting to call __toString and then test that too. That method calls all of the other methods
		// in the class we are testing.
		$html = (string)$objectUnderTest;

		// Check the modules needed for the calculator JS code are added
		$this->assertArrayEquals( [ 'ext.checkUser', 'ext.checkUser.styles' ], $context->getOutput()->getModules() );

		// Check that the HTML produced for the calculator is as expected
		$panelLayoutHtml = $this->assertAndGetByElementId( $html, 'mw-checkuser-cidrform' );
		$this->assertAndGetByElementClass( $panelLayoutHtml, 'mw-checkuser-cidr-iplist' );
		$this->assertAndGetByElementClass( $panelLayoutHtml, 'mw-checkuser-cidr-res' );
		$resultLabelHtml = $this->assertAndGetByElementClass( $panelLayoutHtml, 'mw-checkuser-cidr-res-label' );
		$this->assertStringContainsString( '(checkuser-cidr-res', $resultLabelHtml );
		$this->assertAndGetByElementClass( $panelLayoutHtml, 'mw-checkuser-cidr-tool-links' );
		$this->assertAndGetByElementClass( $panelLayoutHtml, 'mw-checkuser-cidr-ipnote' );

		// Check that text snippets in $textToBeInHtml are in the HTML of the panel
		foreach ( $textToBeInHtml as $textSnippet ) {
			$this->assertStringContainsString( $textSnippet, $panelLayoutHtml );
		}

		// Check that text snippets in $textNotToBeInHtml are not in the HTML of the panel
		foreach ( $textNotToBeInHtml as $textSnippet ) {
			$this->assertStringNotContainsString( $textSnippet, $panelLayoutHtml );
		}
	}

	public static function provideToString() {
		return [
			'Config left as the defaults' => [ [], [ '(checkuser-cidr-label)' ], [] ],
			'Config defines calculator widget as collapsable' => [
				[ 'collapsable' => true ], [ '(checkuser-cidr-label)', 'collapsibleFieldsetLayout' ], []
			],
			'Config defines has having no wrapper legend text' => [
				[ 'wrapperLegend' => false ], [], [ '(checkuser-cidr-label)', 'fieldsetLayout' ]
			],
		];
	}

	/**
	 * Calls DOMCompat::getElementById, expects that it returns a valid Element object and then returns
	 * the HTML of that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $id The ID to search for, excluding the "#" character
	 * @return string
	 */
	private function assertAndGetByElementId( string $html, string $id ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::getElementById( $specialPageDocument, $id );
		$this->assertNotNull( $element, "Could not find element with ID $id in $html" );
		return DOMCompat::getOuterHTML( $element );
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * the HTML of that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return string
	 */
	private function assertAndGetByElementClass( string $html, string $class ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return DOMCompat::getOuterHTML( $element[0] );
	}
}
