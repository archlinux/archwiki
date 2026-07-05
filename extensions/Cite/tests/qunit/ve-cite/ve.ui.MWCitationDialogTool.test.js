'use strict';

{
	const { MWReferenceNode } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.ui.MWCitationDialogTool (Cite)', ve.test.utils.newMwEnvironment() );

	QUnit.test( 'isCompatibleWith', ( assert ) => {
		const model = new MWReferenceNode();
		assert.false(
			ve.ui.MWCitationDialogTool.static.isCompatibleWith( model ),
			'CitationDialogTools are not compatible with plain ReferenceNodes'
		);
	} );
}
