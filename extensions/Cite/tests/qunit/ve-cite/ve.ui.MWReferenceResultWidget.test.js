'use strict';

{
	const { MWReferenceModel, MWReferenceResultWidget } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.ui.MWReferenceResultWidget (Cite)', ve.test.utils.newMwEnvironment() );

	const getConfigMock = () => ( {
		item: {
			$refContent: '',
			reference: new MWReferenceModel(),
			footnoteLabel: '',
			name: ''
		}
	} );

	QUnit.test( 'Initialization', ( assert ) => {
		const widget = new MWReferenceResultWidget( getConfigMock() );
		assert.true( widget instanceof OO.ui.OptionWidget );
		assert.strictEqual( widget.$element.children( '.ve-ui-mwReferenceResultWidget-shield' ).length, 0 );
	} );
}
