'use strict';

{
	const {
		MWReferenceEditPanel,
		MWReferenceModel
	} = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.ui.MWReferenceEditPanel (Cite)', ve.test.utils.newMwEnvironment() );

	QUnit.test( 'setting and getting a reference', ( assert ) => {
		ve.init.target.surface = { commandRegistry: { getNames: () => [] } };
		const editPanel = new MWReferenceEditPanel();
		const ref = new MWReferenceModel( new ve.dm.Document( [] ) );
		const doc = ve.dm.citeExample.createExampleDocument( 'references' );
		editPanel.setInternalList( doc.getInternalList() );

		const changeHandlerSpy = sinon.spy();
		editPanel.connect( null, { change: changeHandlerSpy } );

		ref.setGroup( 'group' );
		editPanel.setReferenceForEditing( ref );

		// values setup correctly
		assert.strictEqual( editPanel.originalGroup, 'group' );
		assert.strictEqual( editPanel.referenceGroupInput.getValue(), 'group' );

		// interface setup correctly
		assert.false( editPanel.referenceGroupInput.isDisabled() );
		assert.false( editPanel.reuseWarning.isVisible() );
		assert.false( editPanel.previewPanel.isVisible() );

		// change handler triggered
		const expectedChange = {
			isModified: false,
			hasContent: false
		};
		assert.true( changeHandlerSpy.calledWith( expectedChange ) );

		// reference getter
		editPanel.referenceGroupInput.setValue( '' );
		assert.strictEqual( editPanel.getReferenceFromEditing().getGroup(), '' );
	} );

	QUnit.test( 're-used references', ( assert ) => {
		ve.init.target.surface = { commandRegistry: { getNames: () => [] } };
		const editPanel = new MWReferenceEditPanel();

		// re-used in the example doc
		const ref = new MWReferenceModel( new ve.dm.Document( [] ) );
		ref.listIndex = 1;
		ref.listKey = 'literal/bar';

		const doc = ve.dm.citeExample.createExampleDocument( 'references' );
		editPanel.setInternalList( doc.getInternalList() );
		// editPanel.setInternalList( getDocumentReferencesMock( null, true ) );
		editPanel.setReferenceForEditing( ref );

		// interface setup correctly
		assert.true( editPanel.reuseWarning.isVisible() );
		assert.false( editPanel.previewPanel.isVisible() );
	} );

	QUnit.test( 'sub-references', ( assert ) => {
		ve.init.target.surface = { commandRegistry: { getNames: () => [] } };
		const editPanel = new MWReferenceEditPanel();
		const doc = ve.dm.citeExample.createExampleDocument( 'subReferencing' );

		// sub-ref
		const ref = new MWReferenceModel( new ve.dm.Document( [] ) );
		ref.mainListKey = 'literal/ldr';
		ref.mainListIndex = 1;
		ref.listIndex = 0;
		ref.listKey = 'auto/0';

		// does exist in the example document
		ref.mainListIndex = 0;
		editPanel.setInternalList( doc.getInternalList() );
		editPanel.setReferenceForEditing( ref );

		assert.false( editPanel.reuseWarning.isVisible() );
		assert.true( editPanel.previewPanel.isVisible() );
		assert.false( editPanel.referenceListPreview.$element.text().includes( 'cite-ve-dialog-reference-missing-parent-ref' ) );
		// TODO improve node mock to check content insertion for the parent
		// assert.true( editPanel.referenceListPreview.$element.text().indexOf( 'Bar' ) !== -1 );

		// test sub ref with missing main ref
		ref.mainListKey = 'literal/notexist';
		ref.mainListIndex = '6';
		editPanel.setInternalList( doc.getInternalList() );
		editPanel.setReferenceForEditing( ref );

		assert.false( editPanel.reuseWarning.isVisible() );
		assert.true( editPanel.previewPanel.isVisible() );
		assert.true( editPanel.referenceListPreview.$element.text().includes( 'cite-ve-dialog-reference-missing-parent-ref' ) );
	} );
}
