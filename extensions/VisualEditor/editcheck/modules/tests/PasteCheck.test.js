QUnit.module( 'mw.editcheck.PasteCheck', ve.test.utils.newEditCheckEnvironment() );

QUnit.test( 'onDocumentChange', ( assert ) => {
	const importedText = ( length ) => ve.dm.example.annotateText( 'x'.repeat( length ), ve.dm.example.getImportedAnnotation() );
	const noChange = () => {};
	const cases = [
		{
			msg: 'External paste above minimumCharacters triggers action',
			getData: () => [
				{ type: 'paragraph' },
				...importedText( 60 ),
				{ type: '/paragraph' }
			],
			expectedData: ( data ) => {
				// Only paragraph in the doc is not removed, just content
				data.splice( 1, 60 );
			},
			config: { minimumCharacters: 50 },
			expectedActions: 1,
			expectedFragments: 1
		},
		{
			msg: 'Whole paragraph removed after paste',
			getData: () => [
				{ type: 'paragraph' },
				...importedText( 60 ),
				{ type: '/paragraph' },
				{ type: 'paragraph' },
				...'Foo',
				{ type: '/paragraph' }
			],
			expectedData: ( data ) => {
				data.splice( 0, 62 );
			},
			config: { minimumCharacters: 50 },
			expectedActions: 1,
			expectedFragments: 1
		},
		{
			msg: 'Multi-line paste of short lines',
			getData: () => {
				// Re-use the same annotated text to ensure the same eventId is used for both ranges;
				const shortText = importedText( 30 );
				return [
					{ type: 'paragraph' },
					...shortText,
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					...shortText,
					{ type: '/paragraph' }
				];
			},
			expectedData: ( data ) => {
				data.splice( 1, 62 );
			},
			// Each annotation range is 30 chars, but combined is 60
			config: { minimumCharacters: 50 },
			expectedActions: 1,
			expectedFragments: 2
		},
		{
			msg: 'Pastes with different IDs are treated separately',
			getData: () => [
				{ type: 'paragraph' },
				...importedText( 25 ),
				{ type: '/paragraph' },
				{ type: 'paragraph' },
				...importedText( 25 ),
				{ type: '/paragraph' }
			],
			expectedData: noChange,
			config: { minimumCharacters: 50 },
			expectedActions: 0
		},
		{
			msg: 'External paste below minimumCharacters produces no action',
			getData: () => [
				{ type: 'paragraph' },
				...importedText( 20 ),
				{ type: '/paragraph' }
			],
			expectedData: noChange,
			config: { minimumCharacters: 50 },
			expectedActions: 0
		},
		{
			msg: 'Quoted content ignored when ignoreQuotedContent=true',
			getData: () => [
				{ type: 'paragraph' },
				'"',
				...importedText( 60 ),
				'"',
				{ type: '/paragraph' }
			],
			expectedData: noChange,
			config: { minimumCharacters: 50, ignoreQuotedContent: true },
			expectedActions: 0
		},
		{
			msg: 'Dismissed IDs are ignored',
			getData: () => [
				{ type: 'paragraph' },
				...importedText( 60 ),
				{ type: '/paragraph' }
			],
			expectedData: noChange,
			config: { minimumCharacters: 50 },
			dismissedIds: [ 'test1' ],
			expectedActions: 0
		}
	];

	cases.forEach( ( caseItem ) => {
		mw.editcheck.PasteCheck.static.originalPasteLengths = {};
		ve.init.platform.resetUniqueIdCounter();

		const doc = ve.dm.example.createExampleDocumentFromData( [
			...caseItem.getData(),
			{ type: 'internalList' },
			{ type: '/internalList' }
		] );
		const surfaceModel = new ve.dm.Surface( doc );
		const dummyController = Object.assign( {}, ve.test.utils.EditCheck.dummyController, {
			taggedIds: {
				paste: {
					dismissed: new Set( caseItem.dismissedIds || [] )
				}
			}
		} );
		const check = new mw.editcheck.PasteCheck( dummyController, caseItem.config, true );
		const actions = check.onDocumentChange( surfaceModel ).filter( ( action ) => action.getName() === 'paste' );

		assert.strictEqual( actions.length, caseItem.expectedActions, caseItem.name );
		if ( caseItem.expectedFragments !== undefined ) {
			assert.strictEqual(
				actions[ 0 ].fragments.length,
				caseItem.expectedFragments,
				caseItem.msg + ': fragments'
			);
		}
		if ( caseItem.expectedData ) {
			const data = ve.copy( surfaceModel.getDocument().getFullData() );
			caseItem.expectedData( data );
			const dummySurface = {
				getModel: () => surfaceModel,
				getView: () => ( { focus: () => {} } )
			};
			actions.forEach( ( action ) => {
				check.act( 'remove', action, dummySurface );
			} );
			assert.equalLinearData(
				surfaceModel.getDocument().getFullData(),
				data,
				caseItem.msg + ': data'
			);
		}
	} );
} );
