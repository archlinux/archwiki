<?php

/**
 * Holds tests for ResultWrapper MediaWiki class.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use Wikimedia\Rdbms\IMaintainableDatabase;

/**
 * @group Database
 * @covers \Wikimedia\Rdbms\ResultWrapper
 * @covers \Wikimedia\Rdbms\MysqliResultWrapper
 * @covers \Wikimedia\Rdbms\PostgresResultWrapper
 * @covers \Wikimedia\Rdbms\SqliteResultWrapper
 */
class ResultWrapperTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		$this->tablesUsed[] = 'ResultWrapperTest';
	}

	public function getSchemaOverrides( IMaintainableDatabase $db ) {
		return [
			'create' => [ 'ResultWrapperTest' ],
			'scripts' => [ __DIR__ . '/ResultWrapperTest.sql' ]
		];
	}

	public function testIteration() {
		$this->db->insert(
			'ResultWrapperTest', [
				[ 'col_a' => '1', 'col_b' => 'a' ],
				[ 'col_a' => '2', 'col_b' => 'b' ],
				[ 'col_a' => '3', 'col_b' => 'c' ],
				[ 'col_a' => '4', 'col_b' => 'd' ],
				[ 'col_a' => '5', 'col_b' => 'e' ],
				[ 'col_a' => '6', 'col_b' => 'f' ],
				[ 'col_a' => '7', 'col_b' => 'g' ],
				[ 'col_a' => '8', 'col_b' => 'h' ]
			],
			__METHOD__
		);

		$expectedRows = [
			0 => (object)[ 'col_a' => '1', 'col_b' => 'a' ],
			1 => (object)[ 'col_a' => '2', 'col_b' => 'b' ],
			2 => (object)[ 'col_a' => '3', 'col_b' => 'c' ],
			3 => (object)[ 'col_a' => '4', 'col_b' => 'd' ],
			4 => (object)[ 'col_a' => '5', 'col_b' => 'e' ],
			5 => (object)[ 'col_a' => '6', 'col_b' => 'f' ],
			6 => (object)[ 'col_a' => '7', 'col_b' => 'g' ],
			7 => (object)[ 'col_a' => '8', 'col_b' => 'h' ]
		];

		$res = $this->db->select( 'ResultWrapperTest', [ 'col_a', 'col_b' ], '1 = 1', __METHOD__ );
		$this->assertSame( 8, $res->numRows() );
		$this->assertTrue( $res->valid() );

		$res->seek( 7 );
		$this->assertSame( 7, $res->key() );
		$this->assertArrayEquals( [ 'col_a' => '8', 0 => '8', 'col_b' => 'h', 1 => 'h', ],
			$res->fetchRow(), false, true );
		$this->assertSame( 7, $res->key() );

		$res->seek( 7 );
		$this->assertSame( 7, $res->key() );
		$this->assertEquals( (object)[ 'col_a' => '8', 'col_b' => 'h' ], $res->fetchObject() );
		$this->assertEquals( (object)[ 'col_a' => '8', 'col_b' => 'h' ], $res->current() );
		$this->assertSame( 7, $res->key() );

		$res->seek( 6 );
		$this->assertTrue( $res->valid() );
		$this->assertSame( 6, $res->key() );
		$this->assertEquals( (object)[ 'col_a' => '7', 'col_b' => 'g' ], $res->fetchObject() );
		$this->assertTrue( $res->valid() );
		$this->assertSame( 6, $res->key() );
		$this->assertEquals( (object)[ 'col_a' => '8', 'col_b' => 'h' ], $res->fetchObject() );
		$this->assertSame( 7, $res->key() );
		$this->assertFalse( $res->fetchObject() );
		$this->assertFalse( $res->current() );
		$this->assertFalse( $res->valid() );

		$this->assertArrayEquals( $expectedRows, iterator_to_array( $res, true ),
			false, true );

		$rows = [];
		foreach ( $res as $i => $row ) {
			$rows[$i] = $row;
		}
		$this->assertEquals( $expectedRows, $rows );
	}

	public function testCurrentNoResults() {
		$res = $this->db->select( 'ResultWrapperTest',
			[ 'col_a', 'col_b' ],
			'1 = 0',
			__METHOD__ );
		$this->assertFalse( $res->current() );
	}

	public function testValidNoResults() {
		$res = $this->db->select( 'ResultWrapperTest',
			[ 'col_a', 'col_b' ],
			'1 = 0',
			__METHOD__ );
		$this->assertFalse( $res->valid() );
	}

	public function testSeekNoResults() {
		$res = $this->db->select( 'ResultWrapperTest',
			[ 'col_a', 'col_b' ],
			'1 = 0',
			__METHOD__ );
		$res->seek( 0 );
		$this->assertTrue( true ); // no error
	}

	public function provideSeekOutOfBounds() {
		return [ [ 0, 1 ], [ 1, 1 ], [ 1, 2 ], [ 1, -1 ] ];
	}

	/** @dataProvider provideSeekOutOfBounds */
	public function testSeekOutOfBounds( $numRows, $seekPos ) {
		for ( $i = 0; $i < $numRows; $i++ ) {
			$this->db->insert( 'ResultWrapperTest',
				[ [ 'col_a' => $i, 'col_b' => $i ] ],
				__METHOD__ );
		}
		$res = $this->db->select( 'ResultWrapperTest',
			[ 'col_a', 'col_b' ],
			'1 = 0',
			__METHOD__ );
		$this->expectException( OutOfBoundsException::class );
		$res->seek( $seekPos );
	}
}
