<?php

use Wikimedia\Rdbms\UpdateQueryBuilder;

/**
 * @covers \Wikimedia\Rdbms\UpdateQueryBuilder
 */
class UpdateQueryBuilderTest extends PHPUnit\Framework\TestCase {
	use MediaWikiCoversValidator;

	/** @var DatabaseTestHelper */
	private $db;

	/** @var UpdateQueryBuilder */
	private $uqb;

	protected function setUp(): void {
		$this->db = new DatabaseTestHelper( __CLASS__ . '::' . $this->getName() );
		$this->uqb = $this->db->newUpdateQueryBuilder();
	}

	private function assertSQL( $expected, $fname ) {
		$this->uqb->caller( $fname )->execute();
		$actual = $this->db->getLastSqls();
		$actual = preg_replace( '/ +/', ' ', $actual );
		$actual = preg_replace( '/ +$/', '', $actual );
		$this->assertEquals( $expected, $actual );
	}

	public function testSet() {
		$this->uqb
			->table( 'a' )
			->set( [ 'f' => 'g' ] )
			->andSet( [ 'd' => 'l' ] )
			->where( '1' )
			->andWhere( '2' )
			->conds( '3' );
		$this->assertSQL( "UPDATE a SET f = 'g',d = 'l' WHERE (1) AND (2) AND (3)", __METHOD__ );
	}

	public function testConflictingSet() {
		// T288882: the empty set is the right answer
		$this->uqb
			->update( 't' )
			->set( [ 'f' => 'g' ] )
			->andSet( [ 'f' => 'l' ] )
			->where( [ 'k' => 'v1' ] );
		$this->assertSQL( "UPDATE t SET f = 'l' WHERE k = 'v1'", __METHOD__ );
	}

	public function testCondsEtc() {
		$this->uqb
			->table( 'a' )
			->set( 'f' )
			->where( '1' )
			->andWhere( '2' )
			->conds( '3' );
		$this->assertSQL( 'UPDATE a SET f WHERE (1) AND (2) AND (3)', __METHOD__ );
	}

	public function testConflictingConds() {
		// T288882: the empty set is the right answer
		$this->uqb
			->update( '1' )
			->set( 'a' )
			->where( [ 'k' => 'v1' ] )
			->andWhere( [ 'k' => 'v2' ] );
		$this->assertSQL( 'UPDATE 1 SET a WHERE k = \'v1\' AND (k = \'v2\')', __METHOD__ );
	}

	public function testIgnore() {
		$this->uqb
			->update( 'f' )
			->set( 't' )
			->where( 'c' )
			->ignore();
		$this->assertSQL( 'UPDATE IGNORE f SET t WHERE (c)', __METHOD__ );
	}

	public function testOption() {
		$this->uqb
			->update( 't' )
			->set( 'f' )
			->where( 'c' )
			->option( 'IGNORE' );
		$this->assertSQL( 'UPDATE IGNORE t SET f WHERE (c)', __METHOD__ );
	}

	public function testOptions() {
		$this->uqb
			->update( 't' )
			->set( 'f' )
			->where( 'c' )
			->options( [ 'IGNORE' ] );
		$this->assertSQL( 'UPDATE IGNORE t SET f WHERE (c)', __METHOD__ );
	}

	public function testExecute() {
		$this->uqb->update( 't' )->set( 'f' )->where( 'c' )->caller( __METHOD__ );
		$res = $this->uqb->execute();
		$this->assertEquals( 'UPDATE t SET f WHERE (c)', $this->db->getLastSqls() );
		$this->assertIsBool( $res );
	}

	public function testGetQueryInfo() {
		$this->uqb
			->update( 't' )
			->ignore()
			->set( [ 'f' => 'g' ] )
			->andSet( [ 'd' => 'l' ] )
			->where( [ 'a' => 'b' ] );
		$this->assertEquals(
			[
				'table' => 't' ,
				'set' => [ 'f' => 'g', 'd' => 'l' ],
				'conds' => [ 'a' => 'b' ],
				'options' => [ 'IGNORE' ],
			],
			$this->uqb->getQueryInfo() );
	}

	public function testQueryInfo() {
		$this->uqb->queryInfo(
			[
				'table' => 't',
				'set' => [ 'f' => 'g' ],
				'conds' => [ 'a' => 'b' ],
				'options' => [ 'IGNORE' ],
			]
		);
		$this->assertSQL( "UPDATE IGNORE t SET f = 'g' WHERE a = 'b'", __METHOD__ );
	}
}
