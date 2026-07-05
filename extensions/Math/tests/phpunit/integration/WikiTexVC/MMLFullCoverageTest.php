<?php
namespace MediaWiki\Extension\Math\WikiTexVC;

use InvalidArgumentException;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLComparator;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLTestUtil;
use MediaWikiIntegrationTestCase;

/**
 * This is a test which checks the WikiTexVC (LaTeX to MathML) converter capabilities
 * It uses the Full-Coverage definition of tests from:
 * https://www.mediawiki.org/wiki/Extension:Math/CoverageTest
 *
 * The json test-files for this can be updated with:
 * 'MathSearch-Extension/maintenance/UpdateMath.php  --mode mathml --exportmml /var/www/html/extensions/MathSearch'
 *
 * WIP:
 * Currently this is just checking that texVC can generate MathML
 * for the specified tests, not how the MathML looks like.
 *
 * @covers \MediaWiki\Extension\Math\WikiTexVC\TexVC
 */
final class MMLFullCoverageTest extends MediaWikiIntegrationTestCase {

	/** @var string */
	private static $FILENAMELATEXML = __DIR__ . "/mmlRes-latexml-FullCoverage.json";
	/** @var string */
	private static $FILENAMEMATHOID = __DIR__ . "/mmlRes-mathml-FullCoverage.json";

	/**
	 * @dataProvider provideTestCases
	 */
	public function testTexVC( $title, $tc ) {
		$texVC = new TexVC();
		# Fetch result from WikiTexVC(PHP)
		$resultT = $texVC->check( $tc->tex, [
			'debug' => false,
			'usemathrm' => $tc->usemathrm ?? false,
			'oldtexvc' => $tc->oldtexvc ?? false
		] );

		self::loadXMLandDeleteAttrs( $tc->mml_latexml );
		$mathMLtexVC = MMLTestUtil::getMMLwrapped( $resultT["input"] );
		$this->assertStringNotContainsString( 'merror', $mathMLtexVC,
			"tc $$tc->tex$: MathML $mathMLtexVC contains error" );
		$mmlComparator = new MMLComparator();
		$mmlComparator->compareMathML( $tc->mml_mathoid, $mathMLtexVC );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * Deletes some attributes from the mathml which are not necessary for comparisons.
	 * @param string $mml mathml as string
	 * @return bool|string false if problem, mathml as xml string without the specified attributes if ok
	 */
	public static function loadXMLandDeleteAttrs( $mml ) {
		$xml = simplexml_load_string( $mml );
		self::unsetAttrs( $xml );
		// Recursive call deleting attributes
		self::deleteAttributes( $xml );
		return $xml->asXML();
	}

	public static function deleteAttributes( &$xml ) {
		foreach ( $xml as $node ) {
			self::unsetAttrs( $node );
			self::deleteAttributes( $node );
		}
	}

	public static function unsetAttrs( $node ): void {
		$attrs = $node->attributes();
		unset( $attrs['id'], $attrs['xref'] );
	}

	public static function provideTestCases() {
		$resMathoid = MMLTestUtil::getJSON( self::$FILENAMEMATHOID );
		$resLaTeXML = MMLTestUtil::getJSON( self::$FILENAMELATEXML );
		if ( count( $resMathoid ) != count( $resLaTeXML ) ) {
			throw new InvalidArgumentException( "Test files dont have the same number of entries." );
		}
		$f = [];
		// Adding running indices for location of tests.
		foreach ( $resMathoid as $index => $tcMathoid ) {
			$tcLaTeXML = $resLaTeXML[$index];
			$tc = [
				"ctr" => $index,
				"tex" => $tcMathoid->tex,
				"type" => $tcMathoid->type,
				"mml_mathoid" => $tcMathoid->mml,
				"mml_latexml" => $tcLaTeXML->mml,
			];
			array_push( $f, [ "title N/A", (object)$tc ] );
		}
		return $f;
	}
}
