'use strict';

{
	const { MWReferencesListNode } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.dm.MWReferencesListNode (Cite)', ve.test.utils.newMwEnvironment() );

	QUnit.test( 'isEditable', ( assert ) => {
		let model = new MWReferencesListNode();
		assert.true( model.isEditable() );

		model = new MWReferencesListNode( { attributes: { templateGenerated: true } } );
		assert.false( model.isEditable() );
	} );

	QUnit.test( 'matchFunction', ( assert ) => {
		const el = document.createElement( 'div' );
		assert.false( MWReferencesListNode.static.matchFunction( el ) );
	} );

	QUnit.test( 'describeChange', ( assert ) => {
		for ( const [ key, change, expected ] of [
			[ 'refGroup', { to: 'b' }, 'cite-ve-changedesc-reflist-group-to,<ins>b</ins>' ],
			[ 'refGroup', { from: 'a' }, 'cite-ve-changedesc-reflist-group-from,<del>a</del>' ],
			[ 'refGroup', { from: 'a', to: 'b' }, 'cite-ve-changedesc-reflist-group-both,<del>a</del>,<ins>b</ins>' ],
			[ 'isResponsive', { from: 'a' }, 'cite-ve-changedesc-reflist-responsive-unset' ],
			[ 'isResponsive', {}, 'cite-ve-changedesc-reflist-responsive-set' ],
			[ 'originalMw', {}, null ],
			[ '', {}, null ]
		] ) {
			let msg = MWReferencesListNode.static.describeChange( key, change );
			if ( Array.isArray( msg ) ) {
				msg = $( '<span>' ).append( msg ).html();
			}
			assert.strictEqual( msg, expected );
		}
	} );

	QUnit.test( 'getHashObject', ( assert ) => {
		const dataElement = {
			type: 'T',
			attributes: {
				refGroup: 'R',
				listGroup: 'L',
				isResponsive: true,
				templateGenerated: true
			}
		};
		assert.deepEqual( MWReferencesListNode.static.getHashObject( dataElement ), dataElement );
	} );
}
