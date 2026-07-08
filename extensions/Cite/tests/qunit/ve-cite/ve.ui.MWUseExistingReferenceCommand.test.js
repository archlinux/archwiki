'use strict';

{
	const { MWUseExistingReferenceCommand } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.ui.MWUseExistingReferenceCommand (Cite)', ve.test.utils.newMwEnvironment() );

	const getFragmentMock = ( hasRefs ) => ( {
		getDocument: () => ( {
			getInternalList: () => ( {
				getItemNodeCount: () => hasRefs ? 1 : 0
			} )
		} ),
		getSelection: () => ( {
			getName: () => 'linear'
		} )
	} );

	QUnit.test( 'Constructor', ( assert ) => {
		const command = new MWUseExistingReferenceCommand();
		assert.strictEqual( command.name, 'reference/existing' );
		assert.strictEqual( command.action, 'window' );
		assert.strictEqual( command.method, 'open' );
	} );

	QUnit.test( 'isExecutable', ( assert ) => {
		const command = new MWUseExistingReferenceCommand();

		assert.false( command.isExecutable( getFragmentMock( false ) ) );
		assert.true( command.isExecutable( getFragmentMock( true ) ) );
	} );
}
