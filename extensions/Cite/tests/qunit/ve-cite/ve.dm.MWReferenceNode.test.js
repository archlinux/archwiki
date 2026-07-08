'use strict';

{
	const { MWReferenceNode } = require( 'ext.cite.visualEditor' ).test;

	QUnit.module( 've.dm.MWReferenceNode (Cite)', ve.test.utils.newMwEnvironment() );

	const fixtures = {
		shouldAvoidContentOverride: [
			{
				msg: 'Content not used in current node',
				dataElement: { attributes: { contentsUsed: false, listGroup: '', listKey: 'literal/main' } },
				nodeReuses: [],
				expected: false
			},
			{
				msg: 'Content used in current node and no other node before',
				dataElement: { attributes: { contentsUsed: true, listGroup: '', listKey: 'literal/main' } },
				nodeReuses: [ { attributes: { contentsUsed: false, listGroup: '', listKey: 'literal/main' } } ],
				expected: false
			},
			{
				msg: 'Content used in current node and another node before',
				dataElement: { attributes: { contentsUsed: true, listGroup: '', listKey: 'literal/main' } },
				nodeReuses: [
					{
						attributes: { contentsUsed: true, listGroup: '', listKey: 'literal/main' },
						originalDomElementsHash: 'foo'
					},
					{ attributes: { contentsUsed: true, listGroup: '', listKey: 'literal/main' } }
				],
				expected: true
			},
			{
				msg: 'Sub-ref defaulting to false',
				dataElement: { attributes: {
					contentsUsed: true,
					listGroup: '',
					listKey: 'literal/main',
					mainListKey: 'auto/0',
					mainListIndex: 0
				} },
				nodeReuses: [
					{
						attributes: { contentsUsed: true, listGroup: '', listKey: 'literal/main' },
						originalDomElementsHash: 'foo'
					},
					{
						attributes: {
							contentsUsed: true,
							listGroup: '',
							listKey: 'literal/main',
							mainListKey: 'auto/0',
							mainListIndex: 0
						}
					}
				],
				expected: false
			}
		],
		shouldGetMainContent: [
			{
				msg: 'If there\'s only this ref with that key it should get the content',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/main' } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/main' } }
				],
				expected: true
			},
			{
				msg: 'If this ref owned the content before it should get it',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/main', contentsUsed: true } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/main', contentsUsed: true } },
					{ attributes: { listGroup: '', listKey: 'literal/main' } }
				],
				expected: true
			},
			{
				msg: 'If there\'s another ref with that key that owned the content this ref should not get it',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/main' } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/main' } },
					{ attributes: { listGroup: '', listKey: 'literal/main', contentsUsed: true } }
				],
				expected: false
			},
			{
				msg: 'If there\'s no other ref with that key that owned the content the first node get\'s it',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/main' } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/main' } },
					{ attributes: { listGroup: '', listKey: 'literal/main', contentsUsed: false } }
				],
				expected: true
			},
			{
				msg: 'Sub-ref: If there\'s a sub-ref with that main key that owned the content this ref should not get it',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/main' } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/main' } },
					{ attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main', contentsUsed: true } }
				],
				expected: false
			},
			{
				msg: 'Sub-ref: If there\'s only this sub-ref with that main key it should get the content',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } }
				],
				expected: true
			},
			{
				msg: 'Sub-ref: If there\'s another main ref with that key that owned the content this ref should not get it',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } },
					{ attributes: { listGroup: '', listKey: 'literal/main', contentsUsed: true } }
				],
				expected: false
			},
			{
				msg: 'Sub-ref: If there\'s no other ref with that key that owned the content the first node get\'s it',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } },
					{ attributes: { listGroup: '', listKey: 'literal/main', contentsUsed: false } }
				],
				expected: true
			},
			{
				msg: 'Sub-ref: If there\'s another sub-ref with that key that owned the content this ref should not get it',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } },
					{ attributes: { listGroup: '', listKey: 'literal/subOther', mainListKey: 'literal/main', contentsUsed: true } }
				],
				expected: false
			},
			{
				msg: 'Sub-ref: If this sub-ref was holding the content before it should always get it',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main', contentsUsed: true } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } }
				],
				expected: true
			},
			{
				msg: 'Sub-ref: If no other ref his holding the content the frist node get it, also if it\'s a sub-ref',
				dataElement: { attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } },
				nodesInGroup: [
					{ attributes: { listGroup: '', listKey: 'literal/sub', mainListKey: 'literal/main' } },
					{ attributes: { listGroup: '', listKey: 'literal/subOther', mainListKey: 'literal/main' } },
					{ attributes: { listGroup: '', listKey: 'literal/main' } }
				],
				expected: true
			}
		]
	};

	QUnit.test( 'shouldAvoidContentOverride', ( assert ) => {
		fixtures.shouldAvoidContentOverride.forEach( ( caseItem ) => {
			const nodeReuses = caseItem.nodeReuses.map(
				( element ) => new ve.dm.MWReferenceNode( element )
			);
			assert.strictEqual(
				MWReferenceNode.static.shouldAvoidContentOverride(
					caseItem.dataElement,
					nodeReuses
				),
				caseItem.expected,
				caseItem.msg
			);
		} );
	} );

	QUnit.test( 'shouldGetMainContent', ( assert ) => {
		fixtures.shouldGetMainContent.forEach( ( caseItem ) => {
			const nodeGroup = new ve.dm.InternalListNodeGroup();
			caseItem.nodesInGroup.forEach( ( element ) => {
				nodeGroup.appendNode(
					element.attributes.listKey,
					new ve.dm.MWReferenceNode( element )
				);
			} );
			assert.strictEqual(
				ve.dm.MWReferenceNode.static.shouldGetMainContent(
					caseItem.dataElement,
					nodeGroup
				),
				caseItem.expected,
				caseItem.msg
			);
		} );
	} );

	QUnit.test( 'getSubRefs and getRefsWithSameMain', ( assert ) => {
		const firstSubRef = new ve.dm.Node( { attributes: { mainListKey: 'auto/0', mainListIndex: 0 } } );
		firstSubRef.getOffset = () => 10;
		const secondSubRef = new ve.dm.Node( { attributes: { mainListKey: 'auto/0', mainListIndex: 0 } } );
		secondSubRef.getOffset = () => 20;
		const firstSubRefReuse = new ve.dm.Node( { attributes: { mainListKey: 'auto/0', mainListIndex: 0 } } );
		firstSubRefReuse.getOffset = () => 30;

		// Add sub-refs to the test setup
		const nodeGroup = new ve.dm.InternalListNodeGroup();
		nodeGroup.appendNode( 'literal/second', secondSubRef );
		nodeGroup.appendNode( 'literal/first', firstSubRef );
		nodeGroup.appendNode( 'literal/first', firstSubRefReuse );

		// Add main refs inbetween the sub-refs to the test setup
		const firstMainRef = new ve.dm.Node( { attributes: { listKey: 'auto/0', listIndex: 0 } } );
		firstMainRef.getOffset = () => 5;
		const firstMainRefReuse = new ve.dm.Node( { attributes: { listKey: 'auto/0', listIndex: 0 } } );
		firstMainRefReuse.getOffset = () => 15;

		nodeGroup.appendNode( 'auto/0', firstMainRef );
		nodeGroup.appendNode( 'auto/0', firstMainRefReuse );

		// Add unrelated sub-ref to the test setup
		const unrelatedRef = new ve.dm.Node( { attributes: {
			listKey: 'auto/1',
			listIndex: 1,
			mainListKey: 'auto/3',
			mainListIndex: 3
		} } );
		unrelatedRef.getOffset = () => 25;

		nodeGroup.appendNode( 'auto/1', unrelatedRef );

		nodeGroup.sortGroupIndexes();

		const subRefs = MWReferenceNode.static.getSubRefs( 0, nodeGroup );
		assert.strictEqual( subRefs.length, 3, 'The list of sub-refs does include reuses' );
		assert.strictEqual( subRefs[ 0 ].getOffset(), 10, 'The list of sub-refs is in document order' );

		const refsWithSameMain = MWReferenceNode.static.getRefsWithSameMain( 0, nodeGroup );
		assert.strictEqual( refsWithSameMain.length, 5, 'The list of refs does only relevant include main and sub-refs' );
		assert.strictEqual( refsWithSameMain[ 1 ].getOffset(), 15, 'The list is not in document order' );
		assert.strictEqual( refsWithSameMain[ 2 ].getOffset(), 10, 'The list is not in document order' );
	} );

	QUnit.test( 'hasSubRefs', ( assert ) => {
		const attributes = { listGroup: '', listIndex: 0 };
		const nodeGroup = new ve.dm.InternalListNodeGroup();
		const internalList = { getNodeGroup: () => nodeGroup };
		assert.false( MWReferenceNode.static.hasSubRefs( attributes, internalList ) );

		nodeGroup.appendNode( 'a', new ve.dm.MWReferenceNode( { attributes: { listGroup: '', mainListIndex: 0 } } ) );
		assert.true( MWReferenceNode.static.hasSubRefs( attributes, internalList ) );

		// But when it's a sub-ref it cannot have sub-refs
		attributes.mainListIndex = 0;
		assert.false( MWReferenceNode.static.hasSubRefs( attributes, internalList ) );
	} );

	QUnit.test( 'remapInternalListIndexes', ( assert ) => {
		const dataElement = { attributes: { listIndex: 'old', listKey: 'auto/' } };
		const mapping = { old: 'new' };
		const internalList = { getNextUniqueNumber: () => 7 };
		MWReferenceNode.static.remapInternalListIndexes( dataElement, mapping, internalList );
		assert.deepEqual( dataElement.attributes, { listIndex: 'new', listKey: 'auto/7' } );
	} );

	QUnit.test( 'remapInternalListKeys', ( assert ) => {
		const dataElement = { attributes: { listKey: 'k' } };
		const internalList = {
			getNodeGroup: () => ( {
				getAllReuses: ( key ) => ( { k: [] }[ key ] )
			} )
		};
		MWReferenceNode.static.remapInternalListKeys( dataElement, internalList );
		assert.strictEqual( dataElement.attributes.listKey, 'k2' );
	} );

	QUnit.test( 'getGroup', ( assert ) => {
		const dataElement = { attributes: { refGroup: 'g' } };
		assert.deepEqual( MWReferenceNode.static.getGroup( dataElement ), 'g' );
	} );

	QUnit.test( 'cloneElement', ( assert ) => {
		const element = {
			attributes: { contentsUsed: true, mw: {}, originalMw: {} }
		};
		const store = { value: () => false };
		const clone = MWReferenceNode.static.cloneElement( element, store );
		assert.deepEqual( clone.attributes, {} );
		assert.true( isFinite( clone.originalDomElementsHash ) );
	} );

	QUnit.test( 'getHashObject', ( assert ) => {
		const dataElement = { type: 'T', attributes: { listGroup: 'L' } };
		assert.deepEqual( MWReferenceNode.static.getHashObject( dataElement ), dataElement );
		// FIXME: Shouldn't this behave different?
		assert.deepEqual( MWReferenceNode.static.getInstanceHashObject( dataElement ),
			dataElement );
	} );

	QUnit.test( 'describeChange', ( assert ) => {
		for ( const [ key, change, expected ] of [
			[ 'refGroup', { to: 'b' }, 'cite-ve-changedesc-ref-group-to,<ins>b</ins>' ],
			[ 'refGroup', { from: 'a' }, 'cite-ve-changedesc-ref-group-from,<del>a</del>' ],
			[ 'refGroup', { from: 'a', to: 'b' }, 'cite-ve-changedesc-ref-group-both,<del>a</del>,<ins>b</ins>' ],
			[ '', {}, undefined ]
		] ) {
			let msg = MWReferenceNode.static.describeChange( key, change );
			if ( Array.isArray( msg ) ) {
				msg = $( '<span>' ).append( msg ).html();
			}
			assert.strictEqual( msg, expected );
		}
	} );

	QUnit.test( 'copySyntheticRefIntoReferencesList', ( assert ) => {
		const doc = ve.dm.citeExample.createExampleDocument( 'references' );
		const nodeGroup = doc.getInternalList().getNodeGroup( 'mwReference/' );
		const key = 'literal/bar';
		const ref = nodeGroup.getFirstNode( key );
		const surface = new ve.dm.Surface( doc );

		assert.strictEqual( nodeGroup.getAllReuses( key ).length, 2 );

		ref.copySyntheticRefIntoReferencesList( surface );
		assert.strictEqual( nodeGroup.getAllReuses( key ).length, 3 );
		const newRef = nodeGroup.getAllReuses( key )[ 2 ];
		assert.strictEqual( ve.getProp( newRef.getAttributes(), 'mw', 'isSyntheticMainRef' ), true );
	} );
}
