QUnit.module( 'mw.editcheck.TextMatchEditCheck', ve.test.utils.newEditCheckEnvironment() );

/**
 * @ignore
 */
function resetStaticState( matchItems ) {
	mw.editcheck.TextMatchEditCheck.static.matchItems = matchItems;
	mw.editcheck.TextMatchEditCheck.static.matchItemsPromise = null;
	mw.editcheck.TextMatchEditCheck.static.matchCache = {
		matchItems: null,
		memoizedFinders: {}
	};
}

QUnit.test( 'onDocumentChange', ( assert ) => {
	const cases = [
		{
			msg: 'Basic match',
			matchItems: {
				bad: {
					title: 'Bad',
					message: 'Avoid this term',
					query: 'Foo'
				}
			},
			data: [
				{ type: 'paragraph' },
				...'Foo',
				{ type: '/paragraph' }
			],
			expectedActions: 1,
			expectedTerms: [ 'Foo' ]
		},
		{
			msg: 'Basic replacement',
			matchItems: {
				bad: {
					title: 'Bad',
					message: 'Avoid this term',
					mode: 'replace',
					query: {
						Foo: 'Bar'
					}
				}
			},
			data: [
				{ type: 'paragraph' },
				...'Foo',
				{ type: '/paragraph' }
			],
			expectedActions: 1,
			expectedTerms: [ 'Foo' ],
			expectedData: ( data ) => {
				data.splice( 1, 3, ...'Bar' );
			}
		},
		{
			msg: 'Text replacement preserves formatting',
			matchItems: {
				bad: {
					title: 'Bad',
					message: 'Avoid this term',
					mode: 'replace',
					query: {
						Foo: 'Bar'
					}
				}
			},
			data: [
				{ type: 'paragraph' },
				...ve.dm.example.annotateText( 'Foo baz', ve.dm.example.bold ),
				{ type: '/paragraph' }
			],
			expectedActions: 1,
			expectedTerms: [ 'Foo' ],
			expectedData: ( data ) => {
				const newData = ve.dm.example.preprocessAnnotations(
					ve.dm.example.annotateText( 'Bar', ve.dm.example.bold )
				).data;
				data.splice( 1, 3, ...newData );
			}
		},
		{
			msg: 'Whole word only (substring ignored)',
			matchItems: {
				bad: {
					title: 'Bad',
					message: 'Avoid this term',
					query: 'Foo'
				}
			},
			data: [
				{ type: 'paragraph' },
				...'Foobar',
				{ type: '/paragraph' }
			],
			expectedActions: 0
		},
		{
			msg: 'Case sensitive',
			matchItems: {
				bad: {
					title: 'Bad',
					message: 'Avoid this term',
					query: 'Foo',
					config: { caseSensitive: true }
				}
			},
			data: [
				{ type: 'paragraph' },
				...'foo Foo',
				{ type: '/paragraph' }
			],
			expectedActions: 1,
			expectedTerm: 'Foo'
		},
		{
			msg: 'minOccurrences (paragraph) with enough occurrences',
			matchItems: {
				bad: {
					title: 'Bad',
					message: 'Avoid this term',
					query: 'Foo',
					expand: 'paragraph',
					config: { minOccurrences: 2 }
				}
			},
			data: [
				{ type: 'paragraph' },
				...'Foo bar Foo',
				{ type: '/paragraph' }
			],
			expectedActions: 1,
			expectedTerms: [ ' ' ]
		},
		{
			msg: 'minOccurrences (paragraph) without enough occurrences',
			matchItems: {
				bad: {
					title: 'Bad',
					message: 'Avoid this term',
					query: 'Foo',
					expand: 'paragraph',
					config: { minOccurrences: 2 }
				}
			},
			data: [
				{ type: 'paragraph' },
				...'Foo',
				{ type: '/paragraph' }
			],
			expectedActions: 0
		},
		{
			msg: 'Dismissed ranges ignored',
			controller: { taggedFragments: {}, taggedIds: {} },
			matchItems: {
				bad: {
					title: 'Bad',
					message: 'Avoid this term',
					query: 'Foo'
				}
			},
			data: [
				{ type: 'paragraph' },
				...'Foo',
				{ type: '/paragraph' }
			],
			expectedActions: 1,
			dismissAndRerun: true,
			expectedActionsAfterDismiss: 0
		},
		{
			msg: 'inNode filters (outside heading ignored)',
			matchItems: {
				'standard-headings': {
					title: 'Non-standard heading',
					message: 'Use standard headings',
					query: {
						Life: 'Biography'
					},
					inNode: 'mwHeading'
				}
			},
			data: [
				{ type: 'paragraph' },
				...'Life',
				{ type: '/paragraph' }
			],
			expectedActions: 0
		},
		{
			msg: 'inNode filters (inside heading matches)',
			matchItems: {
				bad: {
					title: 'Bad',
					message: 'Avoid this term',
					query: {
						Life: 'Biography'
					},
					inNode: 'mwHeading'
				}
			},
			data: [
				{ type: 'mwHeading', attributes: { level: 2 } },
				...'Life',
				{ type: '/mwHeading' }
			],
			expectedActions: 1,
			expectedTerms: [ 'Life' ],
			expectedData: ( data ) => {
				data.splice( 1, 4, ...'Biography' );
			}
		}
	];

	function runCase( caseItem ) {
		resetStaticState( caseItem.matchItems );

		const controller = caseItem.controller || ve.test.utils.EditCheck.dummyController;
		const doc = ve.dm.example.createExampleDocumentFromData( [
			...caseItem.data,
			{ type: 'internalList' },
			{ type: '/internalList' }
		] );
		const surfaceModel = new ve.dm.Surface( doc );
		const check = new mw.editcheck.TextMatchEditCheck( controller, caseItem.checkConfig || {}, true );

		return check.onDocumentChange( surfaceModel ).then( ( actions ) => {
			assert.strictEqual( actions.length, caseItem.expectedActions, caseItem.msg );
			if ( caseItem.expectedTerms !== undefined ) {
				assert.deepEqual( actions.map( ( action ) => action.term ), caseItem.expectedTerms, caseItem.msg + ': terms' );
			}

			let promise = Promise.resolve();
			if ( caseItem.dismissAndRerun ) {
				actions.forEach( ( action ) => {
					check.tag( 'dismissed', action );
				} );
				promise = check.onDocumentChange( surfaceModel ).then( ( actions2 ) => {
					assert.strictEqual( actions2.length, caseItem.expectedActionsAfterDismiss, caseItem.msg + ': after dismiss' );
				} );
			} else if ( caseItem.expectedData ) {
				const data = ve.copy( surfaceModel.getDocument().getFullData() );
				caseItem.expectedData( data );
				const dummySurface = {
					getModel: () => surfaceModel,
					getView: () => ( {
						focus: () => {},
						activate: () => {}
					} )
				};
				actions.forEach( ( action ) => {
					check.act( 'accept', action, dummySurface );
				} );
				assert.equalLinearData(
					surfaceModel.getDocument().getFullData(),
					data,
					caseItem.msg + ': data'
				);
			}
			return promise.then( () => {
				resetStaticState( {} );
			} );
		} );
	}

	const done = assert.async();
	( async function () {
		for ( const caseItem of cases ) {
			await runCase( caseItem );
		}
		done();
	}() );
} );
