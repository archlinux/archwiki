'use strict';

( function () {
	QUnit.module( 've.ui.MWReferenceEditPanel (Cite)', ve.test.utils.newMwEnvironment() );

	function getSimpleNode( doc ) {
		const node = new ve.dm.MWReferenceNode( {
			type: 'mwReference',
			attributes: {
				refGroup: 'mwReference/'
			},
			originalDomElementsHash: Math.random()
		} );
		node.setDocument( doc );
		return node;
	}

	function getDocRefsMock( node, reUse ) {
		const groupRefs = {
			getRefUsages: () => ( reUse ? [ node, node ] : [] ),
			getInternalModelNode: () => ( node ),
			getTotalUsageCount: () => {
				const mainRefsCount = reUse ? 2 : 0;
				const subRefsCount = reUse ? 1 : 0;
				return mainRefsCount + subRefsCount;
			}
		};
		return {
			getAllGroupNames: () => ( [ 'mwReference/' ] ),
			getGroupRefs: () => ( groupRefs )
		};
	}

	QUnit.test( 'setting and getting a reference', ( assert ) => {
		ve.init.target.surface = { commandRegistry: { registry: {} } };
		const editPanel = new ve.ui.MWReferenceEditPanel();
		const ref = new ve.dm.MWReferenceModel( new ve.dm.Document( [] ) );
		editPanel.setDocumentReferences( getDocRefsMock() );

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
		assert.false( editPanel.extendsWarning.isVisible() );

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
		ve.init.target.surface = { commandRegistry: { registry: {} } };
		const editPanel = new ve.ui.MWReferenceEditPanel();
		const ref = new ve.dm.MWReferenceModel( new ve.dm.Document( [] ) );
		editPanel.setDocumentReferences( getDocRefsMock( null, true ) );
		editPanel.setReferenceForEditing( ref );

		// interface setup correctly
		assert.true( editPanel.reuseWarning.isVisible() );
		assert.false( editPanel.extendsWarning.isVisible() );
	} );

	QUnit.test( 'sub-references', ( assert ) => {
		ve.init.target.surface = { commandRegistry: { registry: {} } };
		const editPanel = new ve.ui.MWReferenceEditPanel();
		const doc = new ve.dm.Document( [] );
		const ref = new ve.dm.MWReferenceModel( doc );

		// does exist in the example document
		ref.extendsRef = 'literal/bar';
		editPanel.setDocumentReferences( getDocRefsMock( getSimpleNode( doc ) ) );
		editPanel.setReferenceForEditing( ref );

		assert.false( editPanel.reuseWarning.isVisible() );
		assert.true( editPanel.extendsWarning.isVisible() );
		assert.false( editPanel.extendsWarning.getLabel().text().indexOf( 'cite-ve-dialog-reference-missing-parent-ref' ) !== -1 );
		// TODO improve node mock to check content insertion for the parent
		// assert.true( editPanel.extendsWarning.getLabel().text().indexOf( 'Bar' ) !== -1 );

		// test sub ref with missing main ref
		ref.extendsRef = 'literal/notexist';
		editPanel.setDocumentReferences( getDocRefsMock() );
		editPanel.setReferenceForEditing( ref );

		assert.false( editPanel.reuseWarning.isVisible() );
		assert.true( editPanel.extendsWarning.isVisible() );
		assert.true( editPanel.extendsWarning.getLabel().text().indexOf( 'cite-ve-dialog-reference-missing-parent-ref' ) !== -1 );
	} );
}() );
