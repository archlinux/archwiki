QUnit.module( 'mw.editcheck.EditCheckAction', ve.test.utils.newEditCheckEnvironment() );

QUnit.test( 'equals', ( assert ) => {
	const doc = new ve.dm.Document( [ { type: 'paragraph' }, ...'abcdef', { type: '/paragraph' } ] ),
		surface = new ve.dm.Surface( doc ),
		check1 = new mw.editcheck.BaseEditCheck( ve.test.utils.EditCheck.dummyController, {}, false ),
		check2 = new mw.editcheck.BaseEditCheck( ve.test.utils.EditCheck.dummyController, {}, false );

	const cases = [
		{
			name: 'Exact range match',
			allowsOverlap: false,
			actionConfig: {
				check: check1,
				range: [ 1, 2 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 1, 2 ]
			},
			equals: true
		},
		{
			name: 'Complete range miss',
			allowsOverlap: false,
			actionConfig: {
				check: check1,
				range: [ 1, 2 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 4, 5 ]
			},
			equals: false
		},
		{
			name: 'Overlapping ranges',
			allowsOverlap: false,
			actionConfig: {
				check: check1,
				range: [ 1, 3 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 2, 5 ]
			},
			equals: false
		},
		{
			name: 'Overlapping ranges, allowsOverlap',
			allowsOverlap: true,
			actionConfig: {
				check: check1,
				range: [ 1, 3 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 2, 5 ]
			},
			equals: true
		},
		{
			name: 'Touching ranges',
			allowsOverlap: false,
			actionConfig: {
				check: check1,
				range: [ 1, 2 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 2, 3 ]
			},
			equals: false
		},
		{
			name: 'Touching ranges, allowsOverlap',
			allowsOverlap: false,
			actionConfig: {
				check: check1,
				range: [ 1, 2 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 2, 3 ]
			},
			equals: false
		},
		{
			name: 'Complete range miss',
			allowsOverlap: false,
			actionConfig: {
				check: check1,
				range: [ 1, 2 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 4, 5 ]
			},
			equals: false
		},
		{
			name: 'Range match with id mismatch',
			allowsOverlap: false,
			actionConfig: {
				check: check1,
				range: [ 1, 2 ],
				id: 1
			},
			otherActionConfig: {
				check: check2,
				range: [ 1, 2 ],
				id: 2
			},
			equals: false
		},
		{
			name: 'Exact range match with zero-width ranges',
			allowsOverlap: false,
			actionConfig: {
				check: check1,
				range: [ 1, 1 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 1, 1 ]
			},
			equals: true
		},
		{
			name: 'Exact range match with zero-width ranges, allowsOverlap',
			allowsOverlap: true,
			actionConfig: {
				check: check1,
				range: [ 1, 1 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 1, 1 ]
			},
			equals: true
		},
		{
			name: 'Inexact range match with one zero-width range',
			allowsOverlap: false,
			actionConfig: {
				check: check1,
				range: [ 1, 1 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 1, 2 ]
			},
			equals: false
		},
		{
			name: 'Inexact range match with one zero-width range, allowsOverlap',
			allowsOverlap: true,
			actionConfig: {
				check: check1,
				range: [ 1, 1 ]
			},
			otherActionConfig: {
				check: check2,
				range: [ 1, 2 ]
			},
			equals: true
		}
	];

	const makeFragments = ( start, end ) => [
		surface.getFragment( new ve.dm.LinearSelection( new ve.Range( start, end ) ) )
	];

	cases.forEach( ( caseItem ) => {
		caseItem.actionConfig.fragments = makeFragments( ...caseItem.actionConfig.range );
		caseItem.otherActionConfig.fragments = makeFragments( ...caseItem.otherActionConfig.range );

		const action = new mw.editcheck.EditCheckAction( caseItem.actionConfig ),
			otherAction = new mw.editcheck.EditCheckAction( caseItem.otherActionConfig );

		assert.strictEqual(
			action.equals( otherAction, caseItem.allowsOverlap ),
			caseItem.equals,
			caseItem.name
		);
	} );
} );

QUnit.test( 'overlapsRanges', ( assert ) => {
	const doc = new ve.dm.Document( [ { type: 'paragraph' }, ...'abcdef', { type: '/paragraph' } ] ),
		surface = new ve.dm.Surface( doc ),
		fragments = [ surface.getFragment( new ve.dm.LinearSelection( new ve.Range( 1, 4 ) ) ) ];

	const action = new mw.editcheck.EditCheckAction( { fragments, choices: [] } );
	assert.strictEqual(
		action.overlapsRanges( [ new ve.Range( 5, 6 ), new ve.Range( 2, 3 ) ] ),
		true,
		'Overlapping ranges'
	);
	assert.strictEqual(
		action.overlapsRanges( [ new ve.Range( 5, 6 ), new ve.Range( 5, 7 ) ] ),
		false,
		'Nonoverlapping ranges'
	);
} );
