/*!
 * VisualEditor MediaWiki-specific ContentEditable ClipboardHandler tests.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

QUnit.module( 've.ce.ClipboardHandler (MW)', ve.test.utils.newMwEnvironment() );

/* Tests */

QUnit.test( 'beforePaste/afterPaste', ( assert ) => {
	const extLink = {
		type: 'link/mwExternal',
		attributes: {
			href: 'https://example.com/'
		}
	};

	const cases = [
		{
			documentHtml: '<p></p>',
			rangeOrSelection: new ve.Range( 1 ),
			pasteHtml: '<span typeof="mw:Entity" id="mwAB">-</span><span typeof="mw:Entity" id="mw-reference-cite">-</span>',
			fromVe: true,
			expectedRangeOrSelection: new ve.Range( 5 ),
			expectedHtml: '<p><span typeof="mw:Entity">-</span><span typeof="mw:Entity" id="mw-reference-cite">-</span></p>',
			msg: 'RESTBase IDs stripped'
		},
		{
			documentHtml: '<p></p>',
			rangeOrSelection: new ve.Range( 1 ),
			pasteHtml: '<span typeof="mw:Entity" id="mwAB">-</span><span typeof="mw:Entity" id="mw-reference-cite">-</span>',
			clipboardHandlerHtml: '<span>-</span><span>-</span>',
			fromVe: true,
			expectedRangeOrSelection: new ve.Range( 5 ),
			expectedHtml: '<p><span typeof="mw:Entity">-</span><span typeof="mw:Entity" id="mw-reference-cite">-</span></p>',
			msg: 'RESTBase IDs still stripped if used when important attributes dropped'
		},
		{
			documentHtml: '<p></p>',
			rangeOrSelection: new ve.Range( 1 ),
			pasteHtml: '<a href="https://example.net/">Lorem</a> <a href="not-a-protocol:Some%20text">ipsum</a> <a href="mailto:example@example.net">dolor</a> <a href="javascript:alert()">sit amet</a>',
			expectedRangeOrSelection: new ve.Range( 27 ),
			// hrefs with invalid protocols get removed by DOMPurify, and these links become spans in
			// ve.dm.LinkAnnotation.static.toDataElement (usually the span is stripped later)
			expectedHtml: '<p>Lorem <span>ipsum</span> dolor <span>sit amet</span></p>',
			config: {
				importRules: {
					external: {
						blacklist: {
							'link/mwExternal': true
						}
					}
				}
			},
			msg: 'External links stripped'
		},
		{
			documentHtml: '<p></p>',
			rangeOrSelection: new ve.Range( 1 ),
			pasteHtml: '<a href="https://example.net/">Lorem</a> <a href="not-a-protocol:Some%20text">ipsum</a> <a href="mailto:example@example.net">dolor</a> <a href="javascript:alert()">sit amet</a>',
			expectedRangeOrSelection: new ve.Range( 27 ),
			// hrefs with invalid protocols get removed by DOMPurify, and these links become spans in
			// ve.dm.LinkAnnotation.static.toDataElement (usually the span is stripped later)
			expectedHtml: '<p><a href="https://example.net/" rel="mw:ExtLink">Lorem</a> <span>ipsum</span> <a href="mailto:example@example.net" rel="mw:ExtLink">dolor</a> <span>sit amet</span></p>',
			config: {
				importRules: {
					external: {
						blacklist: {
							'link/mwExternal': false
						}
					}
				}
			},
			msg: 'External links not stripped, but only some protocols allowed'
		},
		{
			rangeOrSelection: new ve.Range( 1 ),
			pasteText: 'https://example.com/',
			expectedRangeOrSelection: new ve.Range( 21 ),
			expectedDefaultPrevented: true,
			expectedOps: [
				[
					{ type: 'retain', length: 1 },
					{
						type: 'replace',
						insert: [
							...ve.dm.example.annotateText( 'https://example.com/', extLink )
						],
						remove: []
					},
					{ type: 'retain', length: 29 }
				]
			],
			config: {
				mode: 'visual'
			},
			msg: 'URL pasted as plain text, visual mode'
		},
		{
			rangeOrSelection: new ve.Range( 1 ),
			pasteText: 'https://example.com/',
			expectedRangeOrSelection: new ve.Range( 21 ),
			expectedDefaultPrevented: true,
			expectedOps: [
				[
					{ type: 'retain', length: 1 },
					{
						type: 'replace',
						insert: [
							...'https://example.com/'
						],
						remove: []
					},
					{ type: 'retain', length: 29 }
				]
			],
			config: {
				mode: 'source'
			},
			msg: 'URL pasted as plain text, source mode'
		},
		{
			rangeOrSelection: new ve.Range( 1 ),
			pasteData: {
				'text/link-preview': '{"description":"","domain":"example.com","filtered_terms":["exampl","exampl","domain"],"image_url":"","keywords":"","preferred_format":"text/html;content=titled-hyperlink","title":"Example Domain","type":"website","url":"https://example.com/"}'
			},
			expectedRangeOrSelection: new ve.Range( 15 ),
			expectedDefaultPrevented: true,
			expectedOps: [
				[
					{ type: 'retain', length: 1 },
					{
						type: 'replace',
						insert: [
							...ve.dm.example.annotateText( 'Example Domain', extLink )
						],
						remove: []
					},
					{ type: 'retain', length: 29 }
				]
			],
			config: {
				mode: 'visual'
			},
			msg: 'URL pasted as text/link-preview, visual mode'
		},
		{
			rangeOrSelection: new ve.Range( 1 ),
			pasteData: {
				'text/link-preview': '{"description":"","domain":"example.com","filtered_terms":["exampl","exampl","domain"],"image_url":"","keywords":"","preferred_format":"text/html;content=titled-hyperlink","title":"Example Domain","type":"website","url":"https://example.com/"}'
			},
			expectedRangeOrSelection: new ve.Range( 21 ),
			expectedDefaultPrevented: true,
			expectedOps: [
				[
					{ type: 'retain', length: 1 },
					{
						type: 'replace',
						insert: [
							...'https://example.com/'
						],
						remove: []
					},
					{ type: 'retain', length: 29 }
				]
			],
			config: {
				mode: 'source'
			},
			msg: 'URL pasted as text/link-preview, source mode'
		}
	];

	const done = assert.async();
	( async function () {
		for ( const caseItem of cases ) {
			await ve.test.utils.runSurfacePasteTest( assert, caseItem );
		}
		done();
	}() );
} );
