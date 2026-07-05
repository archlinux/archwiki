'use strict';

{
	const { MWReferencesListDialog } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.ui.MWReferencesListDialog (Cite)', ve.test.utils.newMwEnvironment() );

	QUnit.test( 'isModified', ( assert ) => {
		const dialog = new MWReferencesListDialog();
		assert.true( dialog.isModified() );
	} );
}
