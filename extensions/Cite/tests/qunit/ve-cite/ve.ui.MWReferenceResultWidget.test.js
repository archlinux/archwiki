'use strict';

( function () {
	QUnit.module( 've.ui.MWReferenceResultWidget (Cite)', ve.test.utils.newMwEnvironment() );

	function getConfigMock() {
		return {
			item: {
				$refContent: '',
				reference: {},
				footnoteLabel: '',
				name: ''
			}
		};
	}

	QUnit.test( 'Initialization', ( assert ) => {
		const widget = new ve.ui.MWReferenceResultWidget( getConfigMock() );
		assert.true( widget instanceof OO.ui.OptionWidget );
		assert.strictEqual( widget.$element.children( '.ve-ui-mwReferenceResultWidget-shield' ).length, 0 );
	} );
}() );
