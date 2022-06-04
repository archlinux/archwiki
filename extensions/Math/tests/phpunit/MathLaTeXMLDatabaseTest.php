<?php

use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathLaTeXML;

/**
 * @covers \MediaWiki\Extension\Math\MathLaTeXML
 *
 * @group Math
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class MathLaTeXMLDatabaseTest extends MediaWikiIntegrationTestCase {
	public $renderer;
	private const SOME_TEX = "a+b";
	private const SOME_HTML = "a<sub>b</sub>";
	private const SOME_MATHML = "iℏ∂_tΨ=H^Ψ<mrow><\ci>";
	private const SOME_LOG = "Sample Log Text.";
	private const SOME_TIMESTAMP = 1272509157;
	private const SOME_SVG = "<?xml </svg >>%%LIKE;'\" DROP TABLE math;";

	/**
	 * Helper function to test protected/private Methods
	 * @param string $name
	 * @return ReflectionMethod
	 */
	protected static function getMethod( $name ) {
		$class = new ReflectionClass( MathLaTeXML::class );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * creates a new database connection and a new math renderer
	 * TODO: Check if there is a way to get database access without creating
	 * the connection to the database explicitly
	 * function addDBData() {
	 * 	$this->tablesUsed[] = 'math';
	 * }
	 * was not sufficient.
	 */
	protected function setUp(): void {
		parent::setUp();
		// TODO: figure out why this is necessary
		$this->db = wfGetDB( DB_PRIMARY );
		$this->renderer = new MathLaTeXML( self::SOME_TEX );
		self::setupTestDB( $this->db, "mathtest" );
	}

	/**
	 * Checks the tex and hash functions
	 * @covers \MediaWiki\Extension\Math\MathRenderer::getInputHash
	 */
	public function testInputHash() {
		$expectedhash = $this->db->encodeBlob( pack( "H32", md5( self::SOME_TEX ) ) );
		$this->assertEquals( $expectedhash, $this->renderer->getInputHash() );
	}

	/**
	 * Helper function to set the current state of the sample renderer instance to the test values
	 */
	public function setValues() {
		// set some values
		$this->renderer->setTex( self::SOME_TEX );
		$this->renderer->setMathml( self::SOME_MATHML );
	}

	/**
	 * @covers \MediaWiki\Extension\Math\MathLaTeXML::getMathTableName
	 */
	public function testTableName() {
		$fnGetMathTableName = self::getMethod( 'getMathTableName' );
		$obj = new MathLaTeXML();
		$tableName = $fnGetMathTableName->invokeArgs( $obj, [] );
		$this->assertEquals( "mathlatexml", $tableName, "Wrong latexml table name" );
	}

	/**
	 * Checks the creation of the math table.
	 * @covers \MediaWiki\Extension\Math\Hooks::onLoadExtensionSchemaUpdates
	 */
	public function testCreateTable() {
		$this->setMwGlobals( 'wgMathValidModes', [ MathConfig::MODE_LATEXML ] );
		$this->db->dropTable( "mathlatexml", __METHOD__ );
		$dbu = DatabaseUpdater::newForDB( $this->db );
		$dbu->doUpdates( [ "extensions" ] );
		$this->expectOutputRegex( '/(.*)Creating mathlatexml table(.*)/' );
		$this->setValues();
		$this->renderer->writeToDatabase();
		$res = $this->db->select( "mathlatexml", "*" );
		$row = $res->fetchRow();
		$this->assertCount( 12, $row );
	}

	/**
	 * Checks database access. Writes an entry and reads it back.
	 * @depends testCreateTable
	 * @covers \MediaWiki\Extension\Math\MathRenderer::writeToDatabase
	 * @covers \MediaWiki\Extension\Math\MathRenderer::readFromDatabase
	 */
	public function testDBBasics() {
		$this->setValues();
		$this->renderer->writeToDatabase();

		$renderer2 = $this->renderer = new MathLaTeXML( self::SOME_TEX );
		$renderer2->readFromDatabase();
		// comparing the class object does now work due to null values etc.
		$this->assertEquals(
			$this->renderer->getTex(), $renderer2->getTex(), "test if tex is the same"
		);
		$this->assertEquals(
			$this->renderer->getMathml(), $renderer2->getMathml(), "Check MathML encoding"
		);
	}
}
