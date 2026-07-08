QUnit.module( 'mw.editcheck.AddReferenceEditCheck', ve.test.utils.newEditCheckEnvironment() );

QUnit.test( 'onBeforeSave', ( assert ) => {
	const longText = 'a'.repeat( 60 );
	const shortText = 'a'.repeat( 49 );

	const createRef = ( attributes = {} ) => [
		{
			type: 'mwReference',
			attributes: Object.assign( {
				about: '#mwt1',
				listIndex: 0,
				listGroup: 'mwReference/',
				listKey: 'auto/0',
				refGroup: ''
			}, attributes )
		},
		{ type: '/mwReference' }
	];

	const cases = [
		{
			msg: 'Long paragraph without reference',
			data: [
				{ type: 'paragraph' },
				...longText,
				{ type: '/paragraph' }
			],
			expectedActions: 1
		},
		{
			msg: 'Short paragraph without reference',
			data: [
				{ type: 'paragraph' },
				...shortText,
				{ type: '/paragraph' }
			],
			expectedActions: 0
		},
		{
			msg: 'Long paragraph with reference',
			data: [
				{ type: 'paragraph' },
				...longText.slice( 0, 30 ),
				...createRef(),
				...longText.slice( 30 ),
				{ type: '/paragraph' }
			],
			expectedActions: 0
		},
		{
			msg: 'Long paragraph with placeholder reference',
			data: [
				{ type: 'paragraph' },
				...longText.slice( 0, 30 ),
				...createRef( { placeholder: true } ),
				...longText.slice( 30 ),
				{ type: '/paragraph' }
			],
			expectedActions: 1
		},
		{
			msg: 'Long heading without reference',
			data: [
				{ type: 'mwHeading', attributes: { level: 2 } },
				...longText,
				{ type: '/mwHeading' }
			],
			expectedActions: 0
		},
		{
			msg: 'Long paragraph followed by list with reference',
			data: [
				{ type: 'paragraph' },
				...longText,
				{ type: '/paragraph' },
				{ type: 'list', attributes: { style: 'bullet' } },
				{ type: 'listItem' },
				{ type: 'paragraph' },
				...'Item',
				...createRef(),
				{ type: '/paragraph' },
				{ type: '/listItem' },
				{ type: '/list' }
			],
			expectedActions: 0
		},
		{
			msg: 'Long paragraph followed by definition list with reference',
			data: [
				{ type: 'paragraph' },
				...longText,
				{ type: '/paragraph' },
				{ type: 'definitionList' },
				{ type: 'definitionListItem', attributes: { style: 'term' } },
				{ type: 'paragraph' },
				...'Item',
				...createRef(),
				{ type: '/paragraph' },
				{ type: '/definitionListItem' },
				{ type: '/definitionList' }
			],
			expectedActions: 0
		},
		{
			msg: 'Long paragraph followed by blockquote with reference',
			data: [
				{ type: 'paragraph' },
				...longText,
				{ type: '/paragraph' },
				{ type: 'blockquote' },
				{ type: 'paragraph' },
				...'Item',
				...createRef(),
				{ type: '/paragraph' },
				{ type: '/blockquote' }
			],
			expectedActions: 0
		},
		{
			msg: 'Long paragraph followed by table with reference',
			data: [
				{ type: 'paragraph' },
				...longText,
				{ type: '/paragraph' },
				{ type: 'table' },
				{ type: 'tableSection', attributes: { style: 'body' } },
				{ type: 'tableRow' },
				{ type: 'tableCell' },
				{ type: 'paragraph' },
				...'Item',
				...createRef(),
				{ type: '/paragraph' },
				{ type: '/tableCell' },
				{ type: '/tableRow' },
				{ type: '/tableSection' },
				{ type: '/table' }
			],
			expectedActions: 0
		}
	];

	cases.forEach( ( caseItem ) => {
		const doc = ve.dm.mwExample.createExampleDocumentFromData( [
			...caseItem.data,
			{ type: 'internalList' },
			{ type: '/internalList' }
		] );
		const surface = new ve.dm.Surface( doc );

		const check = new mw.editcheck.AddReferenceEditCheck( ve.test.utils.EditCheck.dummyController, {}, true );
		const actions = check.onBeforeSave( surface );
		assert.strictEqual( actions.length, caseItem.expectedActions, caseItem.msg );
	} );
} );
