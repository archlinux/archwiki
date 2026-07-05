<?php
namespace MediaWiki\Extension\Math\WikiTexVC;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLTestUtil;
use MediaWikiIntegrationTestCase;

/**
 * This is a very basic test for running more cases for MathML generation.
 * WIP: This tests is for running the specified testfiles in development.
 * Categories can be selected within 'provideTestCases' functions.
 * @covers \MediaWiki\Extension\Math\WikiTexVC\TexVC
 */
final class MMLGenerationParserTest extends MediaWikiIntegrationTestCase {

	/** @var string */
	private static $SELECTEDCATEGORY1 = "texvctreebugs";
	/** @var string */
	private static $FILENAME1 = __DIR__ . "/tex-2-mml.json";
	/** @var string */
	private static $FILENAME2 = __DIR__ . "/ParserTest-Ref.json";

	/**
	 * @dataProvider provideTestCases1
	 * @dataProvider provideTestCases2
	 */
	public function testTexVC( $title, $tc ) {
		$texVC = new TexVC();

		if ( $tc->skipped ?? false ) {
			$this->addToAssertionCount( 1 );
			return;
		}
		# Fetch result from WikiTexVC(PHP)
		$resultT = $texVC->check( $tc->input, [
			'debug' => false,
			'usemathrm' => $tc->usemathrm ?? false,
			'oldtexvc' => $tc->oldtexvc ?? false
		] );
		$this->assertArrayHasKey( 'input', $resultT );
		MMLTestUtil::getMMLwrapped( $resultT['input'] );
	}

	/**
	 * Provide testcases and filter and format them for
	 * the first testfile 'tex-2-mml.json'.
	 * @return array
	 */
	public static function provideTestCases1() {
		$res = MMLTestUtil::getJSON( self::$FILENAME1 );
		$f = $res->{self::$SELECTEDCATEGORY1};

		// Adding running indices for location of tests.
		$indexCtr = 0;
		foreach ( $f as $tc ) {
			$tc[1]->ctr = $indexCtr;
			$indexCtr += 1;
		}
		return $f;
	}

	/**
	 * Provide testcases and filter and format them for
	 * the second testfile 'ParserTest.json'.
	 * @return array
	 */
	public static function provideTestCases2() {
		$res = MMLTestUtil::getJSON( self::$FILENAME2 );
		$f = [];
		// Adding running indices for location of tests.
		$indexCtr = 0;
		foreach ( $res as $tc ) {
			$tc->ctr = $indexCtr;
			// Just to have uniform access here
			$tc->input = $tc->tex;
			$indexCtr += 1;
			array_push( $f, [ "title N/A", $tc ] );
		}
		return $f;
	}
}
