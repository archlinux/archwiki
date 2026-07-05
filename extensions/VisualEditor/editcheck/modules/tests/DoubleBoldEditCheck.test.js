QUnit.module( 'mw.editcheck.DoubleBoldEditCheck', ve.test.utils.newEditCheckEnvironment() );

QUnit.test( 'onDocumentChange', ( assert ) => {
	const cases = [
		{
			msg: 'Bold heading level 3',
			data: [
				{ type: 'mwHeading', attributes: { level: 3 } },
				...ve.dm.example.annotateText( 'Heading', ve.dm.example.bold ),
				{ type: '/mwHeading' }
			],
			expectedActions: 1
		},
		{
			msg: 'Bold heading level 2',
			data: [
				{ type: 'mwHeading', attributes: { level: 2 } },
				...ve.dm.example.annotateText( 'Heading', ve.dm.example.bold ),
				{ type: '/mwHeading' }
			],
			expectedActions: 0
		},
		{
			msg: 'Plain heading level 3',
			data: [
				{ type: 'mwHeading', attributes: { level: 3 } },
				...'Heading',
				{ type: '/mwHeading' }
			],
			expectedActions: 0
		},
		{
			msg: 'Bold table header',
			data: [
				{ type: 'table' },
				{ type: 'tableSection', attributes: { style: 'body' } },
				{ type: 'tableRow' },
				{ type: 'tableCell', attributes: { style: 'header' } },
				{ type: 'paragraph' },
				...ve.dm.example.annotateText( 'Header', ve.dm.example.bold ),
				{ type: '/paragraph' },
				{ type: '/tableCell' },
				{ type: '/tableRow' },
				{ type: '/tableSection' },
				{ type: '/table' }
			],
			expectedActions: 1
		},
		{
			msg: 'Bold definition list term',
			data: [
				{ type: 'definitionList' },
				{ type: 'definitionListItem', attributes: { style: 'term' } },
				{ type: 'paragraph' },
				...ve.dm.example.annotateText( 'Term', ve.dm.example.bold ),
				{ type: '/paragraph' },
				{ type: '/definitionListItem' },
				{ type: '/definitionList' }
			],
			expectedActions: 1
		},
		{
			msg: 'Bold text either side of plain heading level 3',
			data: [
				{ type: 'paragraph' },
				...ve.dm.example.annotateText( 'Bold text', ve.dm.example.bold ),
				{ type: '/paragraph' },
				{ type: 'mwHeading', attributes: { level: 3 } },
				...'Heading',
				{ type: '/mwHeading' },
				{ type: 'paragraph' },
				...ve.dm.example.annotateText( 'Bold text', ve.dm.example.bold ),
				{ type: '/paragraph' }
			],
			expectedActions: 0
		}
	];

	cases.forEach( ( caseItem ) => {
		const doc = ve.dm.example.createExampleDocumentFromData( [
			...caseItem.data,
			{ type: 'internalList' },
			{ type: '/internalList' }
		] );
		const surface = new ve.dm.Surface( doc );
		const check = new mw.editcheck.DoubleBoldEditCheck( ve.test.utils.EditCheck.dummyController, {}, true );
		const actions = check.onDocumentChange( surface );
		assert.strictEqual( actions.length, caseItem.expectedActions, caseItem.msg );
	} );
} );
