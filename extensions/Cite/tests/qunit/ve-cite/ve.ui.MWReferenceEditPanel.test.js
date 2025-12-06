'use strict';

{
	QUnit.module( 've.ui.MWReferenceEditPanel (Cite)', ve.test.utils.newMwEnvironment() );

	/**
	 * @param {ve.dm.Document} doc
	 * @return {ve.dm.MWReferenceNode}
	 */
	const getSimpleNode = ( doc ) => {
		const node = new ve.dm.MWReferenceNode( {
			type: 'mwReference',
			attributes: {
				refGroup: 'mwReference/'
			},
			originalDomElementsHash: Math.random()
		} );
		node.setDocument( doc );
		return node;
	};

	/**
	 * @param {ve.dm.MWReferenceNode|null} [node]
	 * @param {boolean} [reUse=false]
	 * @return {ve.dm.MWDocumentReferences}
	 */
	const getDocumentReferencesMock = ( node, reUse ) => ( {
		getAllGroupNames: () => [ 'mwReference/' ],
		getGroupRefs: () => ( {
			getRefUsages: () => ( reUse ? [ node, node ] : [] ),
			getInternalModelNode: () => ( node ),
			getTotalUsageCount: () => {
				const mainRefsCount = reUse ? 2 : 0;
				const subRefsCount = reUse ? 1 : 0;
				return mainRefsCount + subRefsCount;
			}
		} )
	} );

	QUnit.test( 'setting and getting a reference', ( assert ) => {
		ve.init.target.surface = { commandRegistry: { getNames: () => [] } };
		const editPanel = new ve.ui.MWReferenceEditPanel();
		const ref = new ve.dm.MWReferenceModel( new ve.dm.Document( [] ) );
		editPanel.setDocumentReferences( getDocumentReferencesMock() );

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
		const editPanel = new ve.ui.MWReferenceEditPanel();
		const ref = new ve.dm.MWReferenceModel( new ve.dm.Document( [] ) );
		editPanel.setDocumentReferences( getDocumentReferencesMock( null, true ) );
		editPanel.setReferenceForEditing( ref );

		// interface setup correctly
		assert.true( editPanel.reuseWarning.isVisible() );
		assert.false( editPanel.previewPanel.isVisible() );
	} );

	QUnit.test( 'sub-references', ( assert ) => {
		ve.init.target.surface = { commandRegistry: { getNames: () => [] } };
		const editPanel = new ve.ui.MWReferenceEditPanel();
		const doc = new ve.dm.Document( [] );
		const ref = new ve.dm.MWReferenceModel( doc );

		// does exist in the example document
		ref.mainRefKey = 'literal/bar';
		editPanel.setDocumentReferences( getDocumentReferencesMock( getSimpleNode( doc ) ) );
		editPanel.setReferenceForEditing( ref );

		assert.false( editPanel.reuseWarning.isVisible() );
		assert.true( editPanel.previewPanel.isVisible() );
		assert.false( editPanel.referenceListPreview.$element.text().includes( 'cite-ve-dialog-reference-missing-parent-ref' ) );
		// TODO improve node mock to check content insertion for the parent
		// assert.true( editPanel.referenceListPreview.$element.text().indexOf( 'Bar' ) !== -1 );

		// test sub ref with missing main ref
		ref.mainRefKey = 'literal/notexist';
		editPanel.setDocumentReferences( getDocumentReferencesMock() );
		editPanel.setReferenceForEditing( ref );

		assert.false( editPanel.reuseWarning.isVisible() );
		assert.true( editPanel.previewPanel.isVisible() );
		assert.true( editPanel.referenceListPreview.$element.text().includes( 'cite-ve-dialog-reference-missing-parent-ref' ) );
	} );
}
