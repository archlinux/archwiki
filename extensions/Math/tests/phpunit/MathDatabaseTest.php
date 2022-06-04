<?php

use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Extension\Math\MathRenderer;

/**
 * Test the database access and core functionality of MathRenderer.
 *
 * @covers \MediaWiki\Extension\Math\MathRenderer
 *
 * @group Math
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class MathDatabaseTest extends MediaWikiIntegrationTestCase {
	/**
	 * @var MathRenderer
	 */
	private $renderer;
	private const SOME_TEX = "a+b";
	private const SOME_HTML = "a<sub>b</sub> and so on";
	private const SOME_MATHML = "iℏ∂_tΨ=H^Ψ<mrow><\ci>";
	private const SOME_CONSERVATIVENESS = 2;
	private const SOME_OUTPUTHASH = 'C65c884f742c8591808a121a828bc09f8<';

	/**
	 * creates a new database connection and a new math renderer
	 * TODO: Check if there is a way to get database access without creating
	 * the connection to the database explicitly
	 * function addDBData() {
	 *    $this->tablesUsed[] = 'math';
	 * }
	 * was not sufficient.
	 */
	protected function setUp(): void {
		parent::setUp();
		// TODO: figure out why this is necessary
		$this->db = wfGetDB( DB_PRIMARY );
		$this->renderer = new MathMathML( self::SOME_TEX );
		$this->tablesUsed[] = 'mathoid';
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
	 * Checks database access. Writes an entry and reads it back.
	 * @covers \MediaWiki\Extension\Math\MathRenderer::writeToDatabase
	 * @covers \MediaWiki\Extension\Math\MathRenderer::readFromDatabase
	 */
	public function testDBBasics() {
		$this->setValues();
		$this->renderer->writeToDatabase( $this->db );
		$renderer2 = new MathMathML( self::SOME_TEX );
		$this->assertTrue( $renderer2->readFromDatabase(), 'Reading from database failed' );
		// comparing the class object does now work due to null values etc.
		$this->assertEquals(
			$this->renderer->getTex(), $renderer2->getTex(), "test if tex is the same"
		);
		$this->assertEquals(
			$this->renderer->getMathml(), $renderer2->getMathml(), "Check MathML encoding"
		);
		$this->assertEquals(
			$this->renderer->getHtmlOutput(), $renderer2->getHtmlOutput(), 'test if HTML is the same'
		);
	}

	/**
	 * Checks the creation of the math table.
	 * @covers \MediaWiki\Extension\Math\Hooks::onLoadExtensionSchemaUpdates
	 */
	public function testCreateTable() {
		$this->setMwGlobals( 'wgMathValidModes', [ MathConfig::MODE_MATHML ] );
		$this->db->dropTable( "mathoid", __METHOD__ );
		$dbu = DatabaseUpdater::newForDB( $this->db );
		$dbu->doUpdates( [ "extensions" ] );
		$this->expectOutputRegex( '/(.*)Creating mathoid table(.*)/' );
		$this->setValues();
		$this->renderer->writeToDatabase();
		$res = $this->db->select( "mathoid", "*" );
		$row = $res->fetchRow();
		$this->assertCount( 16, $row );
	}

	/**
	 * This test checks if no additional write operation
	 * is performed, if the entry already existed in the database.
	 */
	public function testNoWrite() {
		$this->setValues();
		$inputHash = $this->renderer->getInputHash();
		$this->assertTrue( $this->renderer->isChanged() );
		$this->assertTrue( $this->renderer->writeCache(), "Write new entry" );
		$res = $this->db->selectField( "mathoid", "math_inputhash",
			[ "math_inputhash" => $inputHash ] );
		$this->assertTrue( $res !== false, "Check database entry" );
		$this->assertTrue( $this->renderer->readFromDatabase(), "Read entry from database" );
		$this->assertFalse( $this->renderer->isChanged() );
		// modify the database entry manually
		$this->db->delete( "mathoid", [ "math_inputhash" => $inputHash ] );
		// the renderer should not be aware of the modification and should not recreate the entry
		$this->assertFalse( $this->renderer->writeCache() );
		// as a result no entry can be found in the database.
		$this->assertFalse( $this->renderer->readFromDatabase() );
	}
}
