<?php

namespace Cite\Tests\Unit;

use Cite\Cite;
use Cite\Parsoid\ReferencesData;
use Cite\Parsoid\RefGroupItem;
use MediaWikiUnitTestCase;

/**
 * @covers \Cite\Parsoid\ReferencesData
 * @license GPL-2.0-or-later
 */
class ReferencesDataTest extends MediaWikiUnitTestCase {

	public function testMinimalSetup() {
		$data = new ReferencesData();
		$this->assertSame( [], $data->embeddedErrors );
		$this->assertNull( $data->referencesGroup );
		$this->assertFalse( $data->inReferenceList() );
		$this->assertNull( $data->referenceListGroup() );
		$this->assertFalse( $data->inEmbeddedContent() );
		$this->assertNull( $data->lookupRefGroup( Cite::DEFAULT_GROUP ) );
		$this->assertSame( [], $data->getRefGroups() );
	}

	public function testGetOrCreateRefGroup() {
		$data = new ReferencesData();
		$group = $data->getOrCreateRefGroup( 'note' );
		$this->assertSame( 'note', $group->name );
		$data->removeRefGroup( 'note' );
		$this->assertNull( $data->lookupRefGroup( 'note' ) );
	}

	public function testEmbeddedInAnyContent() {
		$data = new ReferencesData();
		$data->pushEmbeddedContentFlag();
		$this->assertTrue( $data->inEmbeddedContent() );
		$this->assertFalse( $data->inReferenceList() );
		$this->assertNull( $data->referenceListGroup() );
		$data->popEmbeddedContentFlag();
		$this->assertFalse( $data->inEmbeddedContent() );
	}

	public function testEmbeddedInReferencesContent() {
		$data = new ReferencesData();
		$data->pushEmbeddedContentFlag();
		$this->assertTrue( $data->inEmbeddedContent() );
		$this->assertFalse( $data->inReferenceList() );
		$this->assertNull( $data->referenceListGroup() );
		$data->popEmbeddedContentFlag();
		$this->assertFalse( $data->inReferenceList() );
		$this->assertNull( $data->referenceListGroup() );
	}

	public function testAddUnnamedRef() {
		$data = new ReferencesData();
		$group = $data->getOrCreateRefGroup( Cite::DEFAULT_GROUP );
		$ref = $data->addRef(
			$group,
			'',
			''
		);

		$expected = new RefGroupItem();
		$expected->globalId = 1;
		$this->assertEquals( $expected, $ref );

		$this->assertEquals( [ $expected ], $group->refs );
		$this->assertSame( [], $group->indexByName );
	}

	public function testAddNamedRef() {
		$data = new ReferencesData();
		$group = $data->getOrCreateRefGroup( 'note' );
		$ref = $data->addRef(
			$group,
			'wales',
			'rtl'
		);

		$expected = new RefGroupItem();
		$expected->dir = 'rtl';
		$expected->group = 'note';
		$expected->globalId = 1;
		$expected->name = 'wales';
		$this->assertEquals( $expected, $ref );

		$this->assertEquals( [ $expected ], $group->refs );
		$this->assertEquals( [ 'wales' => $expected ], $group->indexByName );
	}

	public function testSubref() {
		$data = new ReferencesData();
		$group = $data->getOrCreateRefGroup( 'note' );
		$ref = $data->addRef(
			$group,
			'wales',
			'rtl',
			'detail'
		);

		$expectedParent = new RefGroupItem();
		$expectedParent->dir = 'rtl';
		$expectedParent->group = 'note';
		$expectedParent->globalId = 1;
		$expectedParent->name = 'wales';

		$expected = new RefGroupItem();
		$expected->dir = 'rtl';
		$expected->group = 'note';
		$expected->globalId = 2;
		$expected->subrefIndex = 1;
		$this->assertEquals( $expected, $ref );

		$this->assertEquals( [ $expectedParent, $expected ], $group->refs );
		$this->assertEquals( [ 'wales' => $expectedParent ], $group->indexByName );
	}

}
