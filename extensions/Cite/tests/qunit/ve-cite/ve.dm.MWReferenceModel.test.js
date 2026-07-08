'use strict';

/*!
 * @license MIT
 */
{
	const { MWReferenceModel, MWReferenceNode } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.dm.MWReferenceModel (Cite)', ve.test.utils.newMwEnvironment() );

	QUnit.test( 'find an unknown ref', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'references' );
		const surface = new ve.dm.Surface( doc );

		const refNode = new MWReferenceNode( {
			type: 'mwReference',
			attributes: {},
			originalDomElementsHash: Math.random()
		} );
		refNode.setDocument( doc );
		const refModel = MWReferenceModel.static.newFromReferenceNode( refNode );
		// TODO: Callers might be surprised, the docs hint that a missing entry results in `null`.
		assert.strictEqual( refModel.findInternalItem( surface ), undefined );
	} );

	QUnit.test( 'find a known ref', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'references' );
		const surface = new ve.dm.Surface( doc );

		// We know exactly where the third ref node is, grab it from the document.
		const refNode = doc.getDocumentNode().children[ 1 ].children[ 3 ];
		const refModel = MWReferenceModel.static.newFromReferenceNode( refNode );
		const found = refModel.findInternalItem( surface );
		assert.strictEqual( found.type, 'internalItem' );
	} );

	QUnit.test( 'insert new ref', ( assert ) => {
		const doc = new ve.dm.Document( [
			{ type: 'paragraph', internal: { generated: 'empty' } },
			{ type: '/paragraph' },
			{ type: 'internalList' },
			{ type: '/internalList' }
		] );
		const surface = new ve.dm.Surface( doc );
		const internalList = doc.getInternalList();

		// Create a new, blank reference model linked to the doc.
		const refModel = new MWReferenceModel( doc );
		assert.strictEqual( refModel.getListGroup(), 'mwReference/' );

		const oldNodeCount = internalList.getItemNodeCount();
		const oldDocLength = doc.getLength();

		refModel.insertInternalItem( surface );
		assert.strictEqual( internalList.getItemNodeCount(), oldNodeCount + 1, 'internalItem added' );

		surface.setLinearSelection( new ve.Range( 1 ) );
		refModel.insertReferenceNode( surface.getFragment().collapseToEnd() );
		assert.strictEqual( doc.getLength(), oldDocLength + 6, 'mwReference added to document' );

		refModel.updateInternalItem( surface );
		assert.strictEqual(
			internalList.getNodeGroup( 'mwReference/' ).getAllReuses( 'auto/0' ).length,
			1,
			'keyedNodes tracks the ref after insertion'
		);
	} );

	QUnit.test( 'insert ref reuse', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'simpleRef' );
		const surface = new ve.dm.Surface( doc );
		const internalList = doc.getInternalList();

		// Get a ref model from the existing reference node
		const refNode = doc.getDocumentNode().children[ 0 ].children[ 1 ];
		const refModel = MWReferenceModel.static.newFromReferenceNode( refNode );

		const oldNodeCount = internalList.getItemNodeCount();
		assert.strictEqual(
			internalList.getNodeGroup( 'mwReference/' ).getAllReuses( 'auto/0' ).length,
			1,
			'Initial document does only count one use of the ref'
		);

		surface.setLinearSelection( new ve.Range( 1 ) );
		// Insert a new node using the ref model ( creating a reuse )
		refModel.insertReferenceNode( surface.getFragment().collapseToEnd() );

		assert.strictEqual(
			internalList.getItemNodeCount(),
			oldNodeCount,
			'itemNodeCount does not change on reuse'
		);
		assert.strictEqual(
			doc.getDocumentNode().children[ 0 ].children[ 0 ].getAttribute( 'contentsUsed' ),
			undefined,
			'Reuse was added at the beginning of the doc'
		);
		assert.true(
			doc.getDocumentNode().children[ 0 ].children[ 2 ].getAttribute( 'contentsUsed' ),
			'Original node is now 2nd'
		);
		assert.strictEqual(
			internalList.getNodeGroup( 'mwReference/' ).getAllReuses( 'auto/0' ).length,
			2,
			'Reuse count of the node increases'
		);
	} );

	QUnit.test( 'updateInternalItem with new content', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'simpleRef' );
		const surface = new ve.dm.Surface( doc );
		const internalList = doc.getInternalList();

		// Get a ref model from the existing reference node
		const refNode = doc.getDocumentNode().children[ 0 ].children[ 1 ];
		const refModel = MWReferenceModel.static.newFromReferenceNode( refNode );

		// Change the content on the ref's document
		const refDoc = refModel.getDocument();
		const fragment = new ve.dm.Surface( refDoc ).getLinearFragment( new ve.Range( 1, 4 ) );
		fragment.insertContent( 'Quux' );

		refModel.updateInternalItem( surface );

		// Retrieve the new content from the internalItem
		const itemNode = internalList.getItemNode( refModel.getListIndex() );
		assert.deepEqual(
			doc.getData( itemNode.children[ 0 ].getRange() ),
			[ 'Q', 'u', 'u', 'x' ],
			'Content in internal item updated'
		);
	} );

	QUnit.test( 'updateGroup', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'simpleRefsWithGroup' );
		const surface = new ve.dm.Surface( doc );
		const internalList = doc.getInternalList();

		// Get a ref model from the existing reference node
		const refNode = doc.getDocumentNode().children[ 0 ].children[ 1 ];
		const refModel = MWReferenceModel.static.newFromReferenceNode( refNode );

		const oldNodeCount = internalList.getItemNodeCount();
		assert.strictEqual(
			internalList.getNodeGroup( 'mwReference/' ).getAllReuses( 'literal/book' ).length,
			1,
			'Initial list has one ref with the key in the default group'
		);
		assert.strictEqual(
			internalList.getNodeGroup( 'mwReference/g1' ).getAllReuses( 'literal/book' ).length,
			1,
			'Initial list has one ref with the key in the g1 group'
		);

		// Change the group model and update the internal item accroding to the changed model
		refModel.group = 'g1';
		refModel.updateGroup( surface );

		assert.strictEqual(
			internalList.getItemNodeCount(),
			oldNodeCount,
			'itemNodeCount does not change on update'
		);
		assert.strictEqual(
			internalList.getNodeGroup( 'mwReference/' ).getAllReuses( 'literal/book' ),
			undefined,
			'Updated list has no ref with the key in the default group'
		);
		const nodeGroup = internalList.getNodeGroup( 'mwReference/g1' );
		assert.strictEqual(
			nodeGroup.getAllReuses( 'literal/book' ).length,
			1,
			'Updated list has one ref with the key in the g1 group'
		);
		assert.strictEqual(
			nodeGroup.getAllReuses( 'auto/0' ).length,
			1,
			'Updated list has one ref with a new key in the g1 group'
		);

		// Move into a new group
		assert.strictEqual( internalList.getNodeGroup( 'mwReference/newGroup' ), undefined );

		refModel.group = 'newGroup';
		refModel.updateGroup( surface );
		assert.false( internalList.getNodeGroup( 'mwReference/newGroup' ).isEmpty() );
	} );

	QUnit.test( 'newFromMainNodeAttributes', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'subReferencing' );

		const ref = MWReferenceModel.static.newFromMainNodeAttributes(
			doc,
			'mwReference/',
			'auto/1',
			2
		);

		assert.strictEqual( ref.getListGroup(), 'mwReference/' );
		assert.strictEqual( ref.getListKey(), 'auto/1' );
		assert.strictEqual( ref.getGroup(), '' );
		assert.strictEqual( ref.getListIndex(), 2 );
		assert.strictEqual( typeof ref.doc, 'function' );
		assert.true( ref.getDocument() instanceof ve.dm.Document );
		assert.false( ref.isSubRef() );

		// omiting the listKey
		const refTwo = MWReferenceModel.static.newFromMainNodeAttributes(
			doc,
			'mwReference/',
			null,
			2
		);

		assert.strictEqual( refTwo.getListGroup(), 'mwReference/' );
		assert.strictEqual( refTwo.getListKey(), 'auto/1' );
		assert.strictEqual( refTwo.getGroup(), '' );
		assert.strictEqual( refTwo.getListIndex(), 2 );
		assert.strictEqual( typeof refTwo.doc, 'function' );
		assert.true( refTwo.getDocument() instanceof ve.dm.Document );
		assert.false( refTwo.isSubRef() );
	} );

	QUnit.test( 'newFromReferenceNode', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'subReferencing' );

		// Normal reference node
		const normalRefNode = doc.getDocumentNode().children[ 0 ].children[ 1 ];
		const normalRefModel = MWReferenceModel.static.newFromReferenceNode( normalRefNode );

		assert.strictEqual( normalRefModel.getListGroup(), 'mwReference/' );
		assert.strictEqual( normalRefModel.getListKey(), 'auto/1' );
		assert.strictEqual( normalRefModel.getGroup(), '' );
		assert.strictEqual( normalRefModel.getListIndex(), 2 );
		assert.strictEqual( normalRefModel.mainListKey, undefined );
		assert.strictEqual( normalRefModel.mainListIndex, undefined );
		assert.strictEqual( typeof normalRefModel.doc, 'function' );
		assert.true( normalRefModel.getDocument() instanceof ve.dm.Document );
		assert.false( normalRefModel.isSubRef() );

		// Sub-reference node
		const subRefNode = doc.getDocumentNode().children[ 0 ].children[ 0 ];
		const subRefModel = MWReferenceModel.static.newFromReferenceNode( subRefNode );

		assert.strictEqual( subRefModel.getListKey(), 'auto/0' );
		assert.strictEqual( subRefModel.getListIndex(), 0 );
		assert.strictEqual( subRefModel.getGroup(), '' );
		assert.strictEqual( subRefModel.mainListKey, 'literal/ldr' );
		assert.strictEqual( subRefModel.mainListIndex, 1 );
		assert.strictEqual( typeof subRefModel.doc, 'function' );
		assert.true( subRefModel.getDocument() instanceof ve.dm.Document );
		assert.true( subRefModel.isSubRef() );
	} );

	QUnit.test( 'copySubReference', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'subReferencing' );
		const subRefNode = doc.getDocumentNode().children[ 0 ].children[ 0 ];

		const oldSubRef = MWReferenceModel.static.newFromReferenceNode( subRefNode );
		const newSubRef = MWReferenceModel.static.copySubReference( oldSubRef, doc );

		assert.strictEqual( newSubRef.getListKey(), '', 'ListKey is not set yet, should be assigned on insert' );
		assert.strictEqual( newSubRef.getListIndex(), undefined, 'ListIndex is not set yet, should be assigned on insert' );
		assert.strictEqual( newSubRef.getGroup(), '' );
		assert.strictEqual( newSubRef.mainListKey, 'literal/ldr' );
		assert.strictEqual( newSubRef.mainListIndex, 1 );

		// Check content of the sub-ref
		const oldData = oldSubRef.getDocument().getData();
		const newData = newSubRef.getDocument().getData();

		const internalListRange = oldSubRef.getDocument().internalList.getListNode().getRange();
		oldData.splice( internalListRange.start, internalListRange.end - internalListRange.start );

		assert.deepEqual( newData, oldData, 'data matches' );
	} );
}
