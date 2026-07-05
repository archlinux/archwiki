'use strict';

QUnit.module( 've.ui.MWReferenceContextItem (Cite)', ve.test.utils.newMwEnvironment() );

QUnit.test( 'getInternalItemNode', ( assert ) => {
	// XXX: This is a regression test with a fragile setup. Please feel free to delete this test
	// when you feel like it doesn't make sense to update it.
	const context = {
		isMobile: () => false,
		getSurface: () => ( {
			isReadOnly: () => false
		} )
	};
	const doc = ve.dm.citeExample.createExampleDocument( 'references' );

	const node = new ve.dm.MWReferenceNode();
	node.setDocument( doc );
	const item = new ve.ui.MWReferenceContextItem( context, node );
	item.internalList = doc.getInternalList();

	assert.strictEqual( item.getInternalItemNode(), null );
} );

QUnit.test( 'getInternalItemNode: make sure we get a node', ( assert ) => {
	const context = {
		isMobile: () => false,
		getSurface: () => ( {
			isReadOnly: () => false
		} )
	};
	const doc = ve.dm.citeExample.createExampleDocument( 'references' );

	const node = new ve.dm.MWReferenceNode( {
		attributes: { listIndex: 0 }
	} );
	node.setDocument( doc );
	const item = new ve.ui.MWReferenceContextItem( context, node );
	item.internalList = doc.getInternalList();

	assert.strictEqual( item.getInternalItemNode().getType(), 'internalItem' );
} );
