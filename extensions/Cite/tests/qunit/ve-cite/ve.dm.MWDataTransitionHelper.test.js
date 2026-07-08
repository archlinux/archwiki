'use strict';

{
	const { MWDataTransitionHelper } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.dm.MWDataTransitionHelper (Cite)', ve.test.utils.newMwEnvironment() );

	QUnit.test( 'buildReflistNumbering', ( assert ) => {
		// Pull the test fixture from `ve.dm.citeExample.subReferencing`
		const doc = ve.dm.citeExample.createExampleDocument( 'subReferencing' );
		const groupName = 'mwReference/';
		const nodeGroup = doc.getInternalList().getNodeGroup( groupName );

		const dataTransitionHelper = new MWDataTransitionHelper();

		const result = dataTransitionHelper.buildReflistNumbering( nodeGroup );

		assert.deepEqual( result,

			{
				0: {
					internalListIndex: 0,
					label: '1.1',
					mainListIndex: 1,
					subrefNumber: 1,
					topLevelNumber: 1
				},
				1: {
					internalListKey: 'literal/ldr',
					internalListIndex: 1,
					label: '1',
					topLevelNumber: 1
				},
				2: {
					internalListKey: 'auto/1',
					internalListIndex: 2,
					label: '2',
					topLevelNumber: 2
				},
				3: {
					internalListIndex: 3,
					label: '3.1',
					mainListIndex: 4,
					subrefNumber: 1,
					topLevelNumber: 3
				},
				4: {
					internalListKey: 'literal/nonexistent',
					internalListIndex: 4,
					label: '3',
					topLevelNumber: 3
				}
			}
		);
	} );

	QUnit.test( 'buildReflistStructure', ( assert ) => {
		// Pull the test fixture from `ve.dm.citeExample.subReferencing`
		const doc = ve.dm.citeExample.createExampleDocument( 'subReferencing' );
		const groupName = 'mwReference/';
		const nodeGroup = doc.getInternalList().getNodeGroup( groupName );

		const dataTransitionHelper = new MWDataTransitionHelper();

		const result = dataTransitionHelper.buildReflistStructure( nodeGroup );

		assert.deepEqual( result,
			[
				{
					internalListKey: 'literal/ldr',
					internalListIndex: 1,
					label: '1',
					subrefs: [
						{
							internalListIndex: 0,
							label: '1.1',
							mainListIndex: 1,
							subrefNumber: 1,
							topLevelNumber: 1
						}
					],
					topLevelNumber: 1
				},
				{
					internalListKey: 'auto/1',
					internalListIndex: 2,
					label: '2',
					subrefs: [],
					topLevelNumber: 2
				},
				{
					internalListKey: 'literal/nonexistent',
					internalListIndex: 4,
					label: '3',
					subrefs: [
						{
							internalListIndex: 3,
							label: '3.1',
							mainListIndex: 4,
							subrefNumber: 1,
							topLevelNumber: 3
						}
					],
					topLevelNumber: 3
				}
			]
		);
	} );
}
