<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC;

use MediaWiki\Extension\Math\WikiTexVC\TexVC;
use MediaWikiIntegrationTestCase;

/**
 * Tests TexVC against a large set of formulae from English Wikipedia.
 * File download of the json-input can be done by running:
 * $ cd maintenance && ./downloadMoreTexVCtests.sh
 * @covers \MediaWiki\Extension\Math\WikiTexVC\Parser
 *
 * @group Standalone
 */
class EnWikiFormulaeTest extends MediaWikiIntegrationTestCase {

	private const FILEPATH = __DIR__ . '/en-wiki-formulae-good.json';
	private const REF_FILEPATH = __DIR__ . '/en-wiki-formulae-good-reference.json';

	public function setUp(): void {
		if ( !file_exists( self::FILEPATH ) || !file_exists( self::REF_FILEPATH ) ) {
			self::markTestSkipped( 'Missing test files. Required: ' .
				self::FILEPATH . ' and ' .
				self::REF_FILEPATH );
		}
		parent::setUp();
	}

	/**
	 * Reads the JSON file to an object
	 * @param string $filePath file to be read
	 * @return array JSON with testcases
	 */
	private static function getJSON( $filePath ): array {
		$file = file_get_contents( $filePath );
		return json_decode( $file, true );
	}

	private static function getTestCases(): array {
		$group = [];
		$references = self::getJSON( self::REF_FILEPATH );
		foreach ( self::getJSON( self::FILEPATH ) as $key => $elem ) {
			$group[$key] = [ $elem, $references[ $key ] ];
		}
		return $group;
	}

	public function testRunCases() {
		$texVC = new TexVC();

		foreach ( self::getTestCases() as $hash => [ $tex, $ref ] ) {
			try {
				$result = $texVC->check( $tex, [
					"debug" => false,
					"usemathrm" => false,
					"oldtexvc" => false
				] );

				$this->assertEquals( '+', $result["status"],
					$hash . " failed. Input: " . $tex );
				if ( preg_match( '/\\\\definecolor \{/m', $ref ) ) {
					// crop long numbers in color codes from 16 to 14 digits
					// while this heuristic might produce false positivies in general, it is sufficient
					// for this dataset
					$ref = preg_replace( '/(0.\d{14})\d{2}([,\}])/m', '$1$2', $ref );
				}

				$this->assertEquals( $ref, $result["output"],
					$hash . " does not match reference." );

				$r1 = $texVC->check( $result["output"] );
				$this->assertEquals( "+", $r1["status"],
					"error rechecking output: " . $tex . " -> " . $result["output"] );
				$mathml = $result["input"]->toMMLtree();
				$this->assertStringNotContainsString( 'merror', $mathml,
					"error rendering MathML: " . $tex . " -> " . $result["output"] );

			} catch ( PhpPegJs\SyntaxError $ex ) {
				$message = "Syntax error: " . $ex->getMessage() .
					' at line ' . $ex->grammarLine . ' column ' .
					$ex->grammarColumn . ' offset ' . $ex->grammarOffset;

				$this->assertTrue( false, $message );
			}
		}
	}
}
