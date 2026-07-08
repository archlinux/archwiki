QUnit.module( 'mw.editcheck.HeadingLevelEditCheck', ve.test.utils.newEditCheckEnvironment() );

QUnit.test( 'onDocumentChange', ( assert ) => {
	const cases = [
		{
			msg: 'h2, h4',
			headingLevels: [ 2, 4 ],
			expectedActions: 1
		},
		{
			msg: 'h2, h3',
			headingLevels: [ 2, 3 ],
			expectedActions: 0
		},
		{
			msg: 'h2, h2',
			headingLevels: [ 2, 2 ],
			expectedActions: 0
		}
	];
	cases.forEach( ( caseItem ) => {
		const doc = ve.dm.example.createExampleDocumentFromData( [
			// eslint-disable-next-line es-x/no-array-prototype-flat
			...caseItem.headingLevels.flatMap( ( level ) => ( [
				{ type: 'mwHeading', attributes: { level } },
				...'Heading',
				{ type: '/mwHeading' }
			] ) ),
			{ type: 'internalList' },
			{ type: '/internalList' }
		] );
		const surface = new ve.dm.Surface( doc );

		const check = new mw.editcheck.HeadingLevelEditCheck( ve.test.utils.EditCheck.dummyController, {}, true );
		const actions = check.onDocumentChange( surface );

		assert.strictEqual( actions.length, caseItem.expectedActions, caseItem.msg );
		if ( actions.length > 0 ) {
			assert.strictEqual( actions[ 0 ].getName(), 'headingLevel', 'Action name' );
		}
	} );
} );
