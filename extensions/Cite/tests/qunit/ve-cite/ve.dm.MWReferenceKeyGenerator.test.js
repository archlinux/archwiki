'use strict';

{
	const { MWReferenceKeyGenerator } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.dm.MWReferenceKeyGenerator (Cite)', ve.test.utils.newMwEnvironment() );

	QUnit.test( 'makeListKey', ( assert ) => {
		let i = 7;
		const internalList = {
			getNextUniqueNumber: () => i++
		};
		assert.strictEqual( MWReferenceKeyGenerator.makeListKey( internalList, 'a' ), 'literal/a' );
		assert.strictEqual( MWReferenceKeyGenerator.makeListKey( internalList ), 'auto/7' );
		assert.strictEqual( MWReferenceKeyGenerator.makeListKey( internalList, '' ), 'auto/8' );
	} );

	QUnit.test( 'deduplicateListKey', ( assert ) => {
		let i = 7;
		const internalList = {
			getNodeGroup: () => ( {
				getAllReuses: ( listKey ) => listKey === 'conflicts'
			} ),
			getNextUniqueNumber: () => i++
		};
		assert.strictEqual(
			MWReferenceKeyGenerator.deduplicateListKey( internalList, '', 'fine' ),
			'fine'
		);
		assert.strictEqual(
			MWReferenceKeyGenerator.deduplicateListKey( internalList, '', 'conflicts' ),
			'auto/7'
		);
	} );

	QUnit.test( 'generateName on a normal main reference', ( assert ) => {
		const attributes = {};
		const internalList = {
			getNodeGroup: () => new ve.dm.InternalListNodeGroup()
		};
		assert.strictEqual(
			MWReferenceKeyGenerator.generateName( attributes, internalList ),
			undefined
		);

		assert.strictEqual( MWReferenceKeyGenerator.generateName( attributes, internalList, true ), ':0' );

		attributes.listKey = 'literal/foo';
		assert.strictEqual( MWReferenceKeyGenerator.generateName( attributes, internalList, true ), 'foo' );
	} );

	QUnit.test( 'generateName on a sub-reference', ( assert ) => {
		const attributes = { mainListIndex: 0 };
		const internalList = {
			getNodeGroup: () => new ve.dm.InternalListNodeGroup()
		};
		assert.strictEqual( MWReferenceKeyGenerator.generateName( attributes, internalList ), ':0' );

		attributes.mainListKey = 'literal/foo';
		assert.strictEqual( MWReferenceKeyGenerator.generateName( attributes, internalList ), 'foo' );
	} );
}
