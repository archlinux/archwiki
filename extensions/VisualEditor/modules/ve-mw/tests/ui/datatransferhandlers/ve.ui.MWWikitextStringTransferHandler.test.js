/*!
 * VisualEditor UserInterface MWWikitextStringTransferHandler tests.
 *
 * @copyright See AUTHORS.txt
 */

QUnit.module( 've.ui.MWWikitextStringTransferHandler', ve.test.utils.newMwEnvironment( {
	beforeEach() {
		// Mock XHR for mw.Api()
		this.server = this.sandbox.useFakeServer();
		// Random number, chosen by a fair dice roll.
		// Used to make #mwt ID deterministic
		this.randomStub = sinon.stub( Math, 'random' ).returns( 0.04 );
	},
	afterEach() {
		this.randomStub.restore();
	}
} ) );

/**
 * @param {QUnit.Assert} assert
 * @param {Object} caseItem
 * @param {Object} caseItem.server
 * @param {string} caseItem.pasteString
 * @param {string} caseItem.pasteType
 * @param {string} caseItem.parsoidResponse
 * @param {Array} caseItem.expectedData
 * @param {boolean} caseItem.assertDom
 * @param {string} caseItem.base
 * @param {string} caseItem.msg
 */
ve.test.utils.runWikitextStringHandlerTest = function ( assert, caseItem ) {
	if ( arguments.length > 2 ) {
		caseItem = {
			server: arguments[ 1 ],
			pasteString: arguments[ 2 ],
			pasteType: arguments[ 3 ],
			parsoidResponse: arguments[ 4 ],
			expectedData: arguments[ 5 ],
			// annotations
			assertDom: arguments[ 7 ],
			base: arguments[ 8 ],
			msg: arguments[ 9 ]
		};
	}
	const done = assert.async(),
		item = ve.ui.DataTransferItem.static.newFromString( caseItem.pasteString, caseItem.pasteType ),
		doc = ve.dm.Document.static.newBlankDocument(),
		mockSurface = {
			getModel: () => ( {
					getDocument: () => doc
				} ),
			createProgress: () => ve.createDeferred().promise()
		};

	ve.fixBase( doc.getHtmlDocument(), doc.getHtmlDocument(), caseItem.base );

	// Check we match the wikitext string handler
	const name = ve.ui.dataTransferHandlerFactory.getHandlerNameForItem( item );
	assert.strictEqual( name, 'wikitextString', caseItem.msg + ': triggers match function' );

	// Invoke the handler
	const handler = ve.ui.dataTransferHandlerFactory.create( 'wikitextString', mockSurface, item );

	handler.getInsertableData().then( ( docOrData ) => {
		let actualData, store;
		if ( docOrData instanceof ve.dm.Document ) {
			actualData = docOrData.getData();
			store = docOrData.getStore();
		} else {
			actualData = docOrData;
			store = new ve.dm.HashValueStore();
		}
		ve.dm.example.postprocessAnnotations( actualData, store );
		if ( caseItem.assertDom ) {
			assert.equalLinearDataWithDom( store, actualData, caseItem.expectedData, caseItem.msg + ': data match (with DOM)' );
		} else {
			assert.equalLinearData( actualData, caseItem.expectedData, caseItem.msg + ': data match' );
		}
		done();
	} );

	if ( caseItem.server && caseItem.parsoidResponse ) {
		caseItem.server.respond( [ 200, { 'Content-Type': 'application/json' }, JSON.stringify( {
			visualeditor: {
				result: 'success',
				content: caseItem.parsoidResponse
			}
		} ) ] );
	}
};

QUnit.test.each( 'convert', [
	{
		msg: 'Simple link',
		// Put link in the middle of text to verify that the
		// start-of-line and end-of-line anchors on the heading
		// identification pattern don't affect link identification
		pasteString: 'some [[Foo]] text',
		pasteType: 'text/plain',
		parsoidResponse: '<p>some <a rel="mw:WikiLink" href="./Foo" title="Foo">Foo</a> text</p>',
		expectedData: [
			{ type: 'paragraph' },
			...'some ',
			...ve.dm.example.annotateText( 'Foo', {
				type: 'link/mwInternal',
				attributes: {
					lookupTitle: 'Foo',
					normalizedTitle: 'Foo',
					title: 'Foo'
				}
			} ),
			...' text',
			{ type: '/paragraph' },
			{ type: 'internalList' },
			{ type: '/internalList' }
		]
	},
	{
		msg: 'Simple link with no p-wrapping',
		pasteString: '*[[Foo]]',
		pasteType: 'text/plain',
		parsoidResponse: '<ul><li><a rel="mw:WikiLink" href="./Foo" title="Foo">Foo</a></li></ul>',
		expectedData: [
			{
				type: 'list',
				attributes: { style: 'bullet' }
			},
			{ type: 'listItem' },
			{
				type: 'paragraph',
				internal: { generated: 'wrapper' }
			},
			...ve.dm.example.annotateText( 'Foo', {
				type: 'link/mwInternal',
				attributes: {
					lookupTitle: 'Foo',
					normalizedTitle: 'Foo',
					title: 'Foo'
				}
			} ),
			{ type: '/paragraph' },
			{ type: '/listItem' },
			{ type: '/list' },
			{ type: 'internalList' },
			{ type: '/internalList' }
		]
	},
	{
		msg: 'Simple template',
		pasteString: '{{Template}}',
		pasteType: 'text/plain',
		parsoidResponse: '<div typeof="mw:Transclusion" about="#mwt1">Template</div>',
		assertDom: true,
		expectedData: [
			{
				type: 'mwTransclusionBlock',
				attributes: {
					mw: {}
				},
				originalDomElements: $.parseHTML( '<div typeof="mw:Transclusion" about="#mwt40000000">Template</div>' )
			},
			{ type: '/mwTransclusionBlock' },
			{ type: 'internalList' },
			{ type: '/internalList' }
		]
	},
	{
		msg: 'Headings, only RESTBase IDs stripped',
		pasteString: '==heading==',
		pasteType: 'text/plain',
		parsoidResponse: '<h2 id="mwAB">foo</h2><h2 id="mw-meaningful-id">bar</h2>',
		assertDom: true,
		expectedData: [
			{ type: 'mwHeading', attributes: { level: 2 }, originalDomElements: $.parseHTML( '<h2>foo</h2>' ) },
			...'foo',
			{ type: '/mwHeading' },
			{ type: 'mwHeading', attributes: { level: 2 }, originalDomElements: $.parseHTML( '<h2 id="mw-meaningful-id">bar</h2>' ) },
			...'bar',
			{ type: '/mwHeading' },
			{ type: 'internalList' },
			{ type: '/internalList' }
		]
	},
	{
		msg: 'Headings, parsoid fallback ids don\'t interfere with whitespace stripping',
		pasteString: '== Tudnivalók ==',
		pasteType: 'text/plain',
		parsoidResponse: '<h2 id="Tudnivalók"><span id="Tudnival.C3.B3k" typeof="mw:FallbackId"></span> Tudnivalók </h2>',
		assertDom: true,
		expectedData: [
			{ type: 'mwHeading', attributes: { level: 2 }, originalDomElements: $.parseHTML( '<h2 id="Tudnivalók"> Tudnivalók </h2>' ) },
			...'Tudnivalók',
			{ type: '/mwHeading' },
			{ type: 'internalList' },
			{ type: '/internalList' }
		]
	},
	{
		msg: 'Magic link (RFC)',
		pasteString: 'RFC 1234',
		pasteType: 'text/plain',
		parsoidResponse: false,
		expectedData: [
			{
				type: 'link/mwMagic',
				attributes: {
					content: 'RFC 1234'
				}
			},
			{
				type: '/link/mwMagic'
			}
		]
	},
	{
		msg: 'Magic link (PMID)',
		pasteString: 'PMID 1234',
		pasteType: 'text/plain',
		parsoidResponse: false,
		expectedData: [
			{
				type: 'link/mwMagic',
				attributes: {
					content: 'PMID 1234'
				}
			},
			{
				type: '/link/mwMagic'
			}
		]
	},
	{
		msg: 'Magic link (ISBN)',
		pasteString: 'ISBN 123456789X',
		pasteType: 'text/plain',
		parsoidResponse: false,
		expectedData: [
			{
				type: 'link/mwMagic',
				attributes: {
					content: 'ISBN 123456789X'
				}
			},
			{
				type: '/link/mwMagic'
			}
		]
	}
], function ( assert, caseItem ) {
	mw.config.set( {
		wgArticlePath: '/wiki/$1'
	} );

	ve.test.utils.runWikitextStringHandlerTest(
		assert,
		{
			server: this.server,
			base: ve.dm.mwExample.baseUri,
			...caseItem
		}
	);
} );
