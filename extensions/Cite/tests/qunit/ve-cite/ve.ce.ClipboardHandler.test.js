/*!
 * VisualEditor Cite-specific ContentEditable ClipboardHandler tests.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

QUnit.module( 've.ce.ClipboardHandler (Cite)', ve.test.utils.newMwEnvironment() );

/* Tests */

QUnit.test( 'beforePaste/afterPaste', ( assert ) => {
	const cases = [
		{
			documentHtml: '<p></p>',
			rangeOrSelection: new ve.Range( 1 ),
			pasteHtml: 'a<sup id="cite_ref-1" class="reference"><a href="./Article#cite_note-1">[1]</a></sup>b',
			expectedRangeOrSelection: new ve.Range( 3 ),
			expectedHtml: '<p>ab</p>',
			msg: 'Leagcy parser read mode references stripped'
		},
		{
			documentHtml: '<p></p>',
			rangeOrSelection: new ve.Range( 1 ),
			pasteHtml: ve.dm.example.singleLine`
				a
				<sup typeof="mw:Extension/ref" data-mw='{"name":"ref","attrs":{},"body":{"id":"mw-reference-text-cite_note-1"}}' class="mw-ref reference" about="#mwt1" id="cite_ref-foo-0" rel="dc:references">
					<a href="./Article#cite_note-foo-0"><span class="mw-reflink-text">[1]</span></a>
				</sup>
				b
			`,
			expectedRangeOrSelection: new ve.Range( 3 ),
			expectedHtml: '<p>ab</p>',
			msg: 'Parsoid read mode references stripped'
		},
		{
			documentHtml: '<p></p>',
			rangeOrSelection: new ve.Range( 1 ),
			pasteHtml: ve.dm.example.singleLine`
				a
					<sup typeof="mw:Extension/ref" data-mw='{"name":"ref","body":{"html":"...some reference HTML..."}}' class="mw-ref reference" about="#mwt1" id="cite_ref-foo-0" rel="dc:references">
						<a href="./Article#cite_note-foo-0"><span class="mw-reflink-text ve-pasteProtect">[1]</span></a>
					</sup>
				b
			`,
			expectedRangeOrSelection: new ve.Range( 5 ),
			expectedHtml: ve.dm.example.singleLine`
				<p>
					a
					<sup typeof="mw:Extension/ref" data-mw='{"name":"ref","body":{"html":"...some reference HTML..."}}' class="mw-ref reference">
						<a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a>
					</sup>
					b
				</p>
			`,
			msg: 'VE references not stripped'
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
