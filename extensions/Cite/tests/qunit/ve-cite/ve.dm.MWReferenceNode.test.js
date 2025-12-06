'use strict';

QUnit.module( 've.dm.MWReferenceNode (Cite)', ve.test.utils.newMwEnvironment() );

QUnit.test( 'makeListKey', ( assert ) => {
	const internalList = { getNextUniqueNumber: () => 7 };
	assert.strictEqual( ve.dm.MWReferenceNode.static.makeListKey( internalList, 'a' ), 'literal/a' );
	assert.strictEqual( ve.dm.MWReferenceNode.static.makeListKey( internalList ), 'auto/7' );
} );

QUnit.test( 'isBodyContentSet', ( assert ) => {
	const dataElement = { attributes: { contentsUsed: true, listGroup: 'same' } };
	const element = { attributes: { contentsUsed: false } };
	const nodesWithSameKey = [ new ve.dm.Model( element ) ];
	assert.false( ve.dm.MWReferenceNode.static.isBodyContentSet( dataElement, nodesWithSameKey ) );

	// One of the other ref with the same name already holds the content
	element.attributes.contentsUsed = true;
	assert.true( ve.dm.MWReferenceNode.static.isBodyContentSet( dataElement, nodesWithSameKey ) );

	// The other ref is actually the same as the current one
	element.attributes.listGroup = 'same';
	assert.false( ve.dm.MWReferenceNode.static.isBodyContentSet( dataElement, nodesWithSameKey ) );

	element.attributes.listGroup = 'different';
	assert.true( ve.dm.MWReferenceNode.static.isBodyContentSet( dataElement, nodesWithSameKey ) );

	// Nothing matters when the current ref doesn't hold content
	dataElement.attributes.contentsUsed = false;
	assert.false( ve.dm.MWReferenceNode.static.isBodyContentSet( dataElement, nodesWithSameKey ) );
} );

QUnit.test( 'shouldGetMainContent on a normal main reference', ( assert ) => {
	const dataElement = { attributes: { listGroup: 'same', listKey: 'foo' } };
	const ownRef = { attributes: { listGroup: 'same', listKey: 'foo' } };

	const nodeGroup = new ve.dm.InternalListNodeGroup();
	nodeGroup.appendNode( 'foo', new ve.dm.Model( ownRef ) );
	assert.true(
		ve.dm.MWReferenceNode.static.shouldGetMainContent( dataElement, nodeGroup ),
		'There is no other ref, only this one'
	);

	const otherRef = { attributes: { contentsUsed: true } };
	nodeGroup.appendNode( 'foo', new ve.dm.Model( otherRef ) );
	assert.false(
		ve.dm.MWReferenceNode.static.shouldGetMainContent( dataElement, nodeGroup ),
		'Another ref was holding the content before'
	);

	otherRef.attributes.contentsUsed = false;
	assert.true(
		ve.dm.MWReferenceNode.static.shouldGetMainContent( dataElement, nodeGroup ),
		'No other ref was holding the content before'
	);

	ownRef.attributes.listGroup = 'different';
	assert.false(
		ve.dm.MWReferenceNode.static.shouldGetMainContent( dataElement, nodeGroup ),
		'The current ref is not the same as the first in the list'
	);

	dataElement.attributes.contentsUsed = true;
	assert.true(
		ve.dm.MWReferenceNode.static.shouldGetMainContent( dataElement, nodeGroup ),
		'This ref was holding the content before'
	);
} );

QUnit.test( 'generateName on a normal main reference', ( assert ) => {
	const attributes = {};
	const internalList = {
		getNodeGroup: () => new ve.dm.InternalListNodeGroup()
	};
	const nodesWithSameKey = [ 'dummy1' ];
	assert.strictEqual(
		ve.dm.MWReferenceNode.static.generateName( attributes, internalList, nodesWithSameKey ),
		undefined
	);

	nodesWithSameKey.push( 'dummy2' );
	assert.strictEqual( ve.dm.MWReferenceNode.static.generateName( attributes, internalList, nodesWithSameKey ), ':0' );

	attributes.listKey = 'literal/foo';
	assert.strictEqual( ve.dm.MWReferenceNode.static.generateName( attributes, internalList, nodesWithSameKey ), 'foo' );
} );

QUnit.test( 'generateName on a sub-reference', ( assert ) => {
	const attributes = { mainRefKey: 'x' };
	const internalList = {
		getNodeGroup: () => new ve.dm.InternalListNodeGroup()
	};
	assert.strictEqual( ve.dm.MWReferenceNode.static.generateName( attributes, internalList, [] ), ':0' );

	attributes.mainRefKey = 'literal/foo';
	assert.strictEqual( ve.dm.MWReferenceNode.static.generateName( attributes, internalList, [] ), 'foo' );
} );

QUnit.test( 'getSubRefs', ( assert ) => {
	const node1 = new ve.dm.Node( { attributes: { mainRefKey: 'a' } } );
	node1.getOffset = () => 98;
	const node2 = new ve.dm.Node( { attributes: { mainRefKey: 'a' } } );
	node2.getOffset = () => 99;

	const nodeGroup = new ve.dm.InternalListNodeGroup();
	nodeGroup.appendNode( 'subref2', node2 );
	nodeGroup.appendNode( 'subref1', node1 );
	nodeGroup.sortGroupIndexes();

	const subRefs = ve.dm.MWReferenceNode.static.getSubRefs( 'a', nodeGroup );
	assert.strictEqual( subRefs.length, 2 );
	// The returned sub-references should be in document order
	assert.strictEqual( subRefs[ 0 ].getOffset(), 98 );
} );

QUnit.test( 'hasSubRefs', ( assert ) => {
	const attributes = { listKey: 'a' };
	const nodeGroup = new ve.dm.InternalListNodeGroup();
	const internalList = { getNodeGroup: () => nodeGroup };
	assert.false( ve.dm.MWReferenceNode.static.hasSubRefs( attributes, internalList ) );

	nodeGroup.appendNode( '', new ve.dm.Model( { attributes: { mainRefKey: 'a' } } ) );
	assert.true( ve.dm.MWReferenceNode.static.hasSubRefs( attributes, internalList ) );

	// But when it's a sub-ref it cannot have sub-refs
	attributes.mainRefKey = 'x';
	assert.false( ve.dm.MWReferenceNode.static.hasSubRefs( attributes, internalList ) );
} );

QUnit.test( 'remapInternalListIndexes', ( assert ) => {
	const dataElement = { attributes: { listIndex: 'old', listKey: 'auto/' } };
	const mapping = { old: 'new' };
	const internalList = { getNextUniqueNumber: () => 7 };
	ve.dm.MWReferenceNode.static.remapInternalListIndexes( dataElement, mapping, internalList );
	assert.deepEqual( dataElement.attributes, { listIndex: 'new', listKey: 'auto/7' } );
} );

QUnit.test( 'remapInternalListKeys', ( assert ) => {
	const dataElement = { attributes: { listKey: 'k' } };
	const internalList = { keys: [ 'k' ] };
	ve.dm.MWReferenceNode.static.remapInternalListKeys( dataElement, internalList );
	assert.strictEqual( dataElement.attributes.listKey, 'k2' );
} );

QUnit.test( 'getGroup', ( assert ) => {
	const dataElement = { attributes: { refGroup: 'g' } };
	assert.deepEqual( ve.dm.MWReferenceNode.static.getGroup( dataElement ), 'g' );
} );

QUnit.test( 'cloneElement', ( assert ) => {
	const element = {
		attributes: { contentsUsed: true, mw: {}, originalMw: {} }
	};
	const store = { value: () => false };
	const clone = ve.dm.MWReferenceNode.static.cloneElement( element, store );
	assert.deepEqual( clone.attributes, {} );
	assert.true( isFinite( clone.originalDomElementsHash ) );
} );

QUnit.test( 'getHashObject', ( assert ) => {
	const dataElement = { type: 'T', attributes: { listGroup: 'L' } };
	assert.deepEqual( ve.dm.MWReferenceNode.static.getHashObject( dataElement ), dataElement );
	// FIXME: Shouldn't this behave different?
	assert.deepEqual( ve.dm.MWReferenceNode.static.getInstanceHashObject( dataElement ),
		dataElement );
} );

QUnit.test( 'describeChange', ( assert ) => {
	for ( const [ key, change, expected ] of [
		[ 'refGroup', { to: 'b' }, 'cite-ve-changedesc-ref-group-to,<ins>b</ins>' ],
		[ 'refGroup', { from: 'a' }, 'cite-ve-changedesc-ref-group-from,<del>a</del>' ],
		[ 'refGroup', { from: 'a', to: 'b' }, 'cite-ve-changedesc-ref-group-both,<del>a</del>,<ins>b</ins>' ],
		[ '', {}, undefined ]
	] ) {
		let msg = ve.dm.MWReferenceNode.static.describeChange( key, change );
		if ( Array.isArray( msg ) ) {
			msg = $( '<span>' ).append( msg ).html();
		}
		assert.strictEqual( msg, expected );
	}
} );

QUnit.test( 'copySyntheticRefIntoReferencesList', ( assert ) => {
	const doc = ve.dm.citeExample.createExampleDocument( 'references' );
	const nodeGroup = doc.getInternalList().getNodeGroup( 'mwReference/' );
	const key = 'literal/bar';
	const ref = nodeGroup.getFirstNode( key );
	const surface = new ve.dm.Surface( doc );

	assert.strictEqual( nodeGroup.getAllReuses( key ).length, 2 );

	ref.copySyntheticRefIntoReferencesList( surface );
	assert.strictEqual( nodeGroup.getAllReuses( key ).length, 3 );
	const newRef = nodeGroup.getAllReuses( key )[ 2 ];
	assert.strictEqual( ve.getProp( newRef.getAttributes(), 'mw', 'isSyntheticMainRef' ), true );
} );
