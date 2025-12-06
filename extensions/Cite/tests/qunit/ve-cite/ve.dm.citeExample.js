'use strict';

/*!
 * VisualEditor DataModel Cite-specific example data sets.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

ve.dm.citeExample = {};

ve.dm.citeExample.baseUri = 'http://example.com/wiki/';

ve.dm.citeExample.createExampleDocument = function ( name, store, base ) {
	return ve.dm.example.createExampleDocumentFromObject(
		name, store, ve.dm.citeExample, base || ve.dm.citeExample.baseUri );
};

ve.dm.citeExample.references = [
	{ type: 'paragraph' },
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 0,
			listKey: 'auto/0',
			mw: {
				attrs: {},
				body: { html: 'No name 1' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"No name 1"},"attrs":{}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	{ type: '/paragraph' },
	{ type: 'paragraph' },
	'F', 'o', 'o',
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 1,
			listKey: 'literal/bar',
			mw: {
				attrs: { name: 'bar' },
				body: { html: 'Bar' },
				name: 'ref'
			},
			originalMw: '{"body":{"html":""},"attrs":{"name":"bar"}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	' ', 'B', 'a', 'z',
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 2,
			listKey: 'literal/:3',
			mw: {
				attrs: { name: ':3' },
				body: { html: 'Quux' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"Quux"},"attrs":{"name":":3"}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	' ', 'W', 'h', 'e', 'e',
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: false,
			listGroup: 'mwReference/',
			listIndex: 1,
			listKey: 'literal/bar',
			mw: {
				attrs: { name: 'bar' },
				name: 'ref'
			},
			originalMw: '{"body":{"html":""},"attrs":{"name":"bar"}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	' ', 'Y', 'a', 'y',
	{ type: '/paragraph' },
	{ type: 'paragraph' },
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 3,
			listKey: 'auto/1',
			mw: {
				attrs: {},
				body: { html: 'No name 2' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"No name 2"},"attrs":{}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/foo',
			listIndex: 4,
			listKey: 'auto/2',
			mw: {
				attrs: { group: 'foo' },
				body: { html: 'No name 3' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"No name 3"},"attrs":{"group":"foo"}}',
			refGroup: 'foo'
		}
	},
	{ type: '/mwReference' },
	{ type: '/paragraph' },
	{
		type: 'mwReferencesList',
		// originalDomElements: HTML,
		attributes: {
			mw: {
				name: 'references',
				attrs: { group: 'g1' }
			},
			originalMw: '{"name":"references","attrs":{"group":"g1"}"}',
			listGroup: 'mwReference/',
			refGroup: '',
			isResponsive: true,
			templateGenerated: false
		}
	},
	{ type: '/mwReferencesList' },
	{ type: 'internalList' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'N', 'o', ' ', 'n', 'a', 'm', 'e', ' ', '1',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'B', 'a', 'r',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'Q', 'u', 'u', 'x',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'N', 'o', ' ', 'n', 'a', 'm', 'e', ' ', '2',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'N', 'o', ' ', 'n', 'a', 'm', 'e', ' ', '3',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: '/internalList' }
];

ve.dm.citeExample.complexInternalData = [
	// 0
	{ type: 'paragraph' },
	'F', [ 'o', [ ve.dm.example.bold ] ], [ 'o', [ ve.dm.example.italic ] ],
	// 4
	{ type: 'mwReference', attributes: {
		about: '#mwt1',
		listIndex: 0,
		listGroup: 'mwReference/',
		listKey: 'auto/0',
		refGroup: ''
	} },
	// 5
	{ type: '/mwReference' },
	// 6
	{ type: '/paragraph' },
	// 7
	{ type: 'internalList' },
	// 8
	{ type: 'internalItem' },
	// 9
	{ type: 'paragraph', internal: { generated: 'wrapper' } },
	'R', [ 'e', [ ve.dm.example.bold ] ], 'f',
	// 13
	'e', [ 'r', [ ve.dm.example.italic ] ], [ 'e', [ ve.dm.example.italic ] ],
	// 16
	{ type: 'mwReference', attributes: {
		mw: {},
		about: '#mwt2',
		listIndex: 1,
		listGroup: 'mwReference/',
		listKey: 'foo',
		refGroup: '',
		contentsUsed: true
	} },
	// 17
	{ type: '/mwReference' },
	'n', 'c', 'e',
	// 21
	{ type: '/paragraph' },
	// 22
	{ type: '/internalItem' },
	// 23
	{ type: 'internalItem' },
	// 24
	{ type: 'preformatted' },
	// 25
	{ type: 'mwEntity', attributes: { character: '€' } },
	// 26
	{ type: '/mwEntity' },
	'2', '5', '0',
	// 30
	{ type: '/preformatted' },
	// 31
	{ type: '/internalItem' },
	// 32
	{ type: '/internalList' }
	// 33
];

ve.dm.citeExample.complexInternalData.internalItems = [
	{ group: 'mwReference', key: null, body: 'First reference' },
	{ group: 'mwReference', key: 'foo', body: 'Table in ref: <table><tr><td>because I can</td></tr></table>' }
];

ve.dm.citeExample.complexInternalData.internalListNextUniqueNumber = 1;

// TODO: Rewrite for details syntax
ve.dm.citeExample.subReferencing = [
	{ type: 'paragraph' },
	{ type: 'mwReference', attributes: {
		mainRefKey: 'literal/ldr',
		listIndex: 0,
		listGroup: 'mwReference/',
		listKey: 'auto/0',
		refGroup: ''
	} },
	{ type: '/mwReference' },
	{ type: 'mwReference', attributes: {
		listIndex: 1,
		listGroup: 'mwReference/',
		listKey: 'auto/1',
		refGroup: ''
	} },
	{ type: '/mwReference' },
	{ type: 'mwReference', attributes: {
		mainRefKey: 'literal/nonexistent',
		listIndex: 2,
		listGroup: 'mwReference/',
		listKey: 'literal/orphaned',
		refGroup: ''
	} },
	{ type: '/mwReference' },
	{ type: '/paragraph' },
	{ type: 'mwReferencesList', attributes: {
		listGroup: 'mwReference/',
		refGroup: ''
	} },
	{ type: 'paragraph' },
	{ type: 'mwReference', attributes: {
		listIndex: 3,
		listGroup: 'mwReference/',
		listKey: 'literal/ldr',
		refGroup: ''
	} },
	{ type: '/mwReference' },
	{ type: '/paragraph' },
	{ type: '/mwReferencesList' },
	{ type: 'internalList' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'S', 'u', 'b', 'r', 'e', 'f',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'O', 't', 'h', 'e', 'r',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'L', 'i', 's', 't', '-', 'd', 'e', 'f', 'i', 'n', 'e', 'd',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: '/internalList' }
];
