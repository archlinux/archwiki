<?php

namespace MediaWiki\Extension\Math\Tests\TexVC;

use InvalidArgumentException;
use MediaWiki\Extension\Math\TexVC\TexVC;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Math\TexVC\TexVC
 * @covers \MediaWiki\Extension\Math\TexVC\Parser
 * @group Stub
 */
class ChemRegressionTest extends MediaWikiUnitTestCase {
	private $texVC;
	private $ACTIVE = true; # indicate whether this test is active
	private $FILENAME = "chem-regression.json";
	private $CHUNKSIZE = 100;

	protected function setUp(): void {
		parent::setUp();
		$this->texVC = new TexVC();
	}

	/**
	 * Reads the json file to an object
	 * @throws InvalidArgumentException File with testcases does not exists.
	 * @return array json with testcases
	 */
	private function getJSON() {
		$filePath = __DIR__ . '/' . $this->FILENAME;
		if ( !file_exists( $filePath ) ) {
			throw new InvalidArgumentException( "No testfile found at specified path: " . $filePath );
		}
		$file = file_get_contents( $filePath );
		$json = json_decode( $file, true );
		return $json;
	}

	private function mkgroups( $arr, $n ) {
		$result = [];
		$group = [];
		$seen = [];
		foreach ( $arr as $elem ) {
			if ( array_key_exists( $elem["input"], $seen ) ) {
				continue;
			} else {
				$seen[$elem["input"]] = true;
			}
			array_push( $group, $elem );
			if ( count( $group ) >= $n ) {
				array_push( $result, $group );
				$group = [];
			}
		}
		if ( count( $group ) > 0 ) {
			array_push( $result, $group );
		}
		return $result;
	}

	public function testAllChemRegression() {
		if ( !$this->ACTIVE ) {
			$this->markTestSkipped( "Chem-Regression test not active and skipped. Can be activated in test-flag." );
			return;
		}

		$texVC = new TexVC();
		$groups = $this->mkgroups( $this->getJSON(), $this->CHUNKSIZE );
		foreach ( $groups as  $group ) {
			foreach ( $group as $testcase ) {
				$testHash = $testcase["inputhash"];
				$f = $testcase["input"];
				$type = $testcase["type"];
				try {
					$options = [
						"debug" => false,
						"usemathrm" => false,
						"oldtexvc" => false
					];

					if ( $type === "chem" ) {
						$options["usemhchem"] = true;
					}

					$result = $texVC->check( $testcase["input"], $options );
					$this->assertEquals( '+', $result["status"], $testHash . " with input: " . $f );
				} catch ( PhpPegJs\SyntaxError $ex ) {
					$message = "Syntax error: " . $ex->getMessage() .
						' at line ' . $ex->grammarLine . ' column ' .
						$ex->grammarColumn . ' offset ' . $ex->grammarOffset;

					$this->assertTrue( false,  $message );
				}
			}
		}
	}
}
