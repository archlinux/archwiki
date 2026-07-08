QUnit.module( 'mw.editcheck.DuplicateLinkEditCheck', ve.test.utils.newEditCheckEnvironment() );

function getLinksData( links ) {
	// eslint-disable-next-line es-x/no-array-prototype-flat
	return links.flatMap( ( linkItem ) => [
		...ve.dm.example.annotateText(
			linkItem.labelText,
			ve.dm.MWInternalLinkAnnotation.static.dataElementFromTitle( mw.Title.newFromText( linkItem.targetTitle ) )
		),
		'-'
	] );
}

QUnit.test( 'onDocumentChange', ( assert ) => {
	const cases = [
		{
			msg: 'Two identical links in separate paragraphs (paragraph scope)',
			scope: 'paragraph',
			data: [
				{ type: 'paragraph' },
				...getLinksData( [
					{ targetTitle: 'Foo', labelText: 'alpha' }
				] ),
				{ type: '/paragraph' },
				{ type: 'paragraph' },
				...getLinksData( [
					{ targetTitle: 'Foo', labelText: 'beta' }
				] ),
				{ type: '/paragraph' }
			],
			expectedActions: 0
		},
		{
			msg: 'Two identical links in separate paragraphs (section scope)',
			scope: 'section',
			data: [
				{ type: 'paragraph' },
				...getLinksData( [
					{ targetTitle: 'Foo', labelText: 'alpha' }
				] ),
				{ type: '/paragraph' },
				{ type: 'paragraph' },
				...getLinksData( [
					{ targetTitle: 'Foo', labelText: 'beta' }
				] ),
				{ type: '/paragraph' }
			],
			expectedActions: 1,
			expectedHighlights: 2
		},
		{
			msg: 'Two identical links in the same paragraph (paragraph scope)',
			scope: 'paragraph',
			data: [
				{ type: 'paragraph' },
				...getLinksData( [
					{ targetTitle: 'Foo', labelText: 'alpha' },
					{ targetTitle: 'Foo', labelText: 'beta' }
				] ),
				{ type: '/paragraph' }
			],
			expectedActions: 1,
			expectedHighlights: 2
		},
		{
			msg: 'Four identical links in the same paragraph (paragraph scope)',
			scope: 'paragraph',
			data: [
				{ type: 'paragraph' },
				...getLinksData( [
					{ targetTitle: 'Foo', labelText: 'alpha' },
					{ targetTitle: 'Foo', labelText: 'beta' },
					{ targetTitle: 'Foo', labelText: 'gamma' },
					{ targetTitle: 'Foo', labelText: 'delta' }
				] ),
				{ type: '/paragraph' }
			],
			expectedActions: 3,
			expectedHighlights: 4
		}
	];

	cases.forEach( ( caseItem ) => {
		const doc = ve.dm.example.createExampleDocumentFromData( [
			...caseItem.data,
			{ type: 'internalList' },
			{ type: '/internalList' }
		] );
		const surface = new ve.dm.Surface( doc );

		const check = new mw.editcheck.DuplicateLinkEditCheck( ve.test.utils.EditCheck.dummyController, { scope: caseItem.scope }, true );
		const actions = check.onDocumentChange( surface );

		assert.strictEqual( actions.length, caseItem.expectedActions, caseItem.msg );
		if ( actions.length > 0 ) {
			assert.strictEqual( actions[ 0 ].getName(), 'duplicateLink', 'Action name' );
			assert.strictEqual( actions[ 0 ].fragments.length, caseItem.expectedHighlights, 'Highlight' );
		}
		if ( actions.length > 1 ) {
			// Assert that all pairs of actions are inequal
			for ( let i = 0; i < actions.length; i++ ) {
				for ( let j = i + 1; j < actions.length; j++ ) {
					assert.false( actions[ i ].equals( actions[ j ] ), 'Actions are not equal to each other, despite having the same fragments (but in different orders)' );
				}
			}
		}
	} );
} );
