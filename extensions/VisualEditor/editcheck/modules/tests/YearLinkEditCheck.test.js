QUnit.module( 'mw.editcheck.YearLinkEditCheck', ve.test.utils.newEditCheckEnvironment() );

/**
 * Create a surface with an internal link whose label is the inserted text.
 *
 * @ignore
 * @param {string} targetTitle
 * @param {string} labelText
 * @return {ve.dm.Surface}
 */
function createSurfaceWithInternalLink( targetTitle, labelText ) {
	const doc = ve.dm.example.createExampleDocumentFromData( [
		{ type: 'paragraph' },
		{ type: '/paragraph' },
		{ type: 'internalList' },
		{ type: '/internalList' }
	] );

	const surface = new ve.dm.Surface( doc );
	const title = mw.Title.newFromText( targetTitle );
	const link = ve.dm.MWInternalLinkAnnotation.static.newFromTitle( title );

	surface.getLinearFragment( new ve.Range( 1, 1 ) ).insertContent( labelText ).annotateContent( 'set', link );

	return surface;
}

QUnit.test( 'checkNode', ( assert ) => {
	const cases = [
		{
			name: 'Simple mismatched year',
			targetTitle: '1999',
			labelText: '2003',
			expectedActions: 1,
			expectedChoices: [ '1999', '2003' ],
			expectedTargetLabelAfterUseTarget: [ '1999', '1999' ],
			expectedTargetLabelAfterUseLabel: [ '2003', '2003' ]
		},
		{
			name: '3-digit mismatched year',
			targetTitle: '854',
			labelText: '855',
			expectedActions: 1,
			expectedChoices: [ '854', '855' ],
			expectedTargetLabelAfterUseTarget: [ '854', '854' ],
			expectedTargetLabelAfterUseLabel: [ '855', '855' ]
		},
		{
			name: 'Simple matched year',
			targetTitle: '1999',
			labelText: '1999',
			expectedActions: 0
		},
		{
			name: 'Mismatch with surrounding text in label',
			targetTitle: '1999',
			labelText: 'films of 2003',
			expectedActions: 1,
			expectedChoices: [ '1999', '2003' ],
			expectedTargetLabelAfterUseTarget: [ '1999', 'films of 1999' ],
			expectedTargetLabelAfterUseLabel: [ '2003', 'films of 2003' ]
		},
		{
			// This used to produce an action, but was disabled in T422274
			// due to false positives like [[Series 5000|5030]].
			name: 'Mismatch with surrounding text in target',
			targetTitle: '1999 in film',
			labelText: 'films of 2003',
			expectedActions: 0
		},
		{
			name: 'Multiple years range in target, single year in label',
			targetTitle: 'Season 1999-2000',
			labelText: '2003',
			expectedActions: 0
		},
		{
			name: 'No year in label',
			targetTitle: '2003',
			labelText: 'the year',
			expectedActions: 0
		},
		{
			name: 'No year in target',
			targetTitle: 'page',
			labelText: '2003',
			expectedActions: 0
		},
		{
			name: 'Multiple years in label, single year in target',
			targetTitle: '2003',
			labelText: '2003 and 2004',
			expectedActions: 0
		},
		{
			name: 'Label year only 2 digits',
			targetTitle: '2003',
			labelText: '99',
			expectedActions: 0
		},
		{
			name: 'Target year only 2 digits',
			targetTitle: 'Euro 99',
			labelText: '2003',
			expectedActions: 0
		}
	];

	cases.forEach( ( caseItem ) => {
		const surfaceModel = createSurfaceWithInternalLink( caseItem.targetTitle, caseItem.labelText );
		const node = surfaceModel.getDocument().getDocumentNode().children[ 0 ];
		const check = new mw.editcheck.YearLinkEditCheck( ve.test.utils.EditCheck.dummyController, {}, true );
		const actions = check.checkNode( node, surfaceModel ).filter( Boolean );

        const wikilink = '[[' + caseItem.targetTitle + ( caseItem.labelText !== caseItem.targetTitle ? '|' + caseItem.labelText : '' ) + ']]';
        const msg = caseItem.name + ' (' + wikilink + ')';
		assert.strictEqual( actions.length, caseItem.expectedActions, msg );

		if ( caseItem.expectedActions ) {
			const action = actions[ 0 ];
			assert.deepEqual(
				action.getChoices().slice( 0, 2 ).map( ( c ) => String( c.label ).split( ',' ).pop() ),
				caseItem.expectedChoices,
				msg + ': choices'
			);

			const fragment = action.fragments[ 0 ];
			const dummySurface = {
				getModel: () => surfaceModel,
				getView: () => ( { focus: () => {}, activate: () => {}, selectAnnotation: () => {} } )
			};

			surfaceModel.breakpoint();

			check.act( 'useTarget', action, dummySurface );
			assert.strictEqual( fragment.getText(), caseItem.expectedTargetLabelAfterUseTarget[ 1 ], msg + ': label after useTarget' );
			assert.strictEqual(
				fragment.getAnnotations().getAnnotationsByName( ve.dm.MWInternalLinkAnnotation.static.name ).get( 0 ).getAttribute( 'normalizedTitle' ),
				caseItem.expectedTargetLabelAfterUseTarget[ 0 ],
				msg + ': target after useTarget'
			);

			surfaceModel.undo();

			check.act( 'useLabel', action, dummySurface );
			assert.strictEqual( fragment.getText(), caseItem.expectedTargetLabelAfterUseLabel[ 1 ], msg + ': label after useLabel' );
			assert.strictEqual(
				fragment.getAnnotations().getAnnotationsByName( ve.dm.MWInternalLinkAnnotation.static.name ).get( 0 ).getAttribute( 'normalizedTitle' ),
				caseItem.expectedTargetLabelAfterUseLabel[ 0 ],
				msg + ': target after useLabel'
			);
		}
	} );
} );
