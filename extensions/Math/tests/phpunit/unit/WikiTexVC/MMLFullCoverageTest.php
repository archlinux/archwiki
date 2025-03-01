<?php
namespace MediaWiki\Extension\Math\WikiTexVC;

use InvalidArgumentException;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLComparator;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLTestUtil;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLTestUtilHTML;
use MediaWikiUnitTestCase;

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
final class MMLFullCoverageTest extends MediaWikiUnitTestCase {
	/** @var float */
	private static $SIMILARITYTRESH = 0.7;
	/** @var bool */
	private static $SKIPXMLVALIDATION = true;
	/** @var string */
	private static $FILENAMELATEXML = __DIR__ . "/mmlRes-latexml-FullCoverage.json";
	/** @var string */
	private static $FILENAMEMATHOID = __DIR__ . "/mmlRes-mathml-FullCoverage.json";
	/** @var bool */
	private static $APPLYFILTER = false;
	/** @var int */
	private static $FILTERSTART = 0;
	/** @var int */
	private static $FILTERLENGTH = 60;
	/** @var bool */
	private static $GENERATEHTML = false;
	/** @var string */
	private static $GENERATEDHTMLFILE = __DIR__ . "/MMLFullCoverageTest-Output.html";
	/** @var bool */
	private static $GENERATEEVAL = false;
	/** @var string */
	private static $GENERATEDEVALFILE = __DIR__ . "/MMLFullCoverageEval.json";
	/** @var int[] */
	private static $SKIPPEDINDICES = [];

	/** @var bool */
	private static $FILTERMML = true;

	public static function setUpBeforeClass(): void {
		MMLTestUtilHTML::generateHTMLstart( self::$GENERATEDHTMLFILE, [ "name", "TeX-Input", "MathML(LaTeXML)",
			"MathML(Mathoid)", "MathML(WikiTexVC)", "F-Similarity" ], self::$GENERATEHTML );
		if ( self::$GENERATEEVAL ) {
			MMLTestUtil::deleteFile( self::$GENERATEDEVALFILE );
			MMLTestUtil::createJSONstartEnd( true, self::$GENERATEDEVALFILE );
		}
	}

	public static function tearDownAfterClass(): void {
		MMLTestUtilHTML::generateHTMLEnd( self::$GENERATEDHTMLFILE, self::$GENERATEHTML );
		if ( self::$GENERATEEVAL ) {
			MMLTestUtil::createJSONstartEnd( false, self::$GENERATEDEVALFILE );
		}
	}

	/**
	 * @dataProvider provideTestCases
	 */
	public function testTexVC( $title, $tc ) {
		$texVC = new TexVC();

		if ( in_array( $tc->ctr, self::$SKIPPEDINDICES, true ) ) {
			MMLTestUtilHTML::generateHTMLtableRow( self::$GENERATEDHTMLFILE, [ $tc->ctr, $tc->tex,
				"skipped", "skipped", "skipped" ], false, self::$GENERATEHTML );
			$this->addToAssertionCount( 1 );
			return;
		}
		# Fetch result from WikiTexVC(PHP)
		$resultT = $texVC->check( $tc->tex, [
			'debug' => false,
			'usemathrm' => $tc->usemathrm ?? false,
			'oldtexvc' => $tc->oldtexvc ?? false
		] );

		$mml_latexml = self::$FILTERMML ? self::loadXMLandDeleteAttrs( $tc->mml_latexml ) : $tc->mml_latexml;
		$mathMLtexVC = MMLTestUtil::getMMLwrapped( $resultT["input"] );
		$this->assertStringNotContainsString( 'merror', $mathMLtexVC,
			"tc $$tc->tex$: MathML $mathMLtexVC contains error" );
		$mmlComparator = new MMLComparator();
		$compRes = $mmlComparator->compareMathML( $tc->mml_mathoid, $mathMLtexVC );

		if ( self::$GENERATEEVAL ) {
			$entry = [
				"testname" => "FullCoverageTest",
				"ctr" => $tc->ctr,
				"tex" => $tc->tex,
				"mml_latcompareMathMLexml" => $mml_latexml,
				"mml_mathoid" => $tc->mml_mathoid,
				"mml_wikitexvc" => $mathMLtexVC,
				"tree_bracket_wikitexvc" => MMLComparator::functionObtainTreeInBrackets( $mathMLtexVC ),
				"tree_bracket_latexml" => MMLComparator::functionObtainTreeInBrackets( $mml_latexml ),
				"tree_bracket_mathoid" => MMLComparator::functionObtainTreeInBrackets( $tc->mml_mathoid )
			];
			MMLTestUtil::appendToJSONFile( $entry, self::$GENERATEDEVALFILE );
		}

		MMLTestUtilHTML::generateHTMLtableRow( self::$GENERATEDHTMLFILE, [ $tc->ctr, $tc->tex, $mml_latexml,
			$tc->mml_mathoid, $mathMLtexVC, $compRes['similarityF'] ], false, self::$GENERATEHTML );

		if ( !self::$SKIPXMLVALIDATION ) {
			if ( !$tc->mml_mathoid ) {
				$this->fail( "No Mathoid reference found for: " . $tc->tex );
			}
			if ( $compRes['similarityF'] >= self::$SIMILARITYTRESH ) {
				$this->addToAssertionCount( 1 );
			} else {
				$this->assertXmlStringEqualsXmlString( $tc->mml_mathoid, $mathMLtexVC );
			}
		} else {
			$this->addToAssertionCount( 1 );
		}
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
		unset( $attrs['id'] );
		unset( $attrs['xref'] );
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
		// Filtering results by index if necessary
		if ( self::$APPLYFILTER ) {
			$f = array_slice( $f, self::$FILTERSTART, self::$FILTERLENGTH );
		}
		return $f;
	}
}
