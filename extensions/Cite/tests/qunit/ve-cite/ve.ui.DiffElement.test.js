'use strict';

/*!
 * VisualEditor Cite-specific DiffElement tests.
 *
 * @copyright 2011-2018 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ui.DiffElement (Cite)' );

QUnit.test( 'Diffing', ( assert ) => {
	const spacer = '<div class="ve-ui-diffElement-spacer">⋮</div>',
		ref = function ( text, num ) {
			const dataMw = {
				name: 'ref',
				body: { html: text }
				// attrs doesn't get set in preview mode
			};

			return '<sup typeof="mw:Extension/ref" data-mw="' + JSON.stringify( dataMw ).replace( /"/g, '&quot;' ) + '" class="mw-ref reference">' +
						'<a><span class="mw-reflink-text"><span class="cite-bracket">[</span>' + num + '<span class="cite-bracket">]</span></span></a>' +
					'</sup>';
		},
		cases = [
			{
				msg: 'Simple ref change',
				oldDoc: ve.dm.example.singleLine`
					<p>${ ref( 'Foo' ) }${ ref( 'Bar' ) }${ ref( 'Baz' ) }</p>
					<h2>Notes</h2>
					<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;}"></div>
				`,
				newDoc: ve.dm.example.singleLine`
					<p>${ ref( 'Foo' ) }${ ref( 'Bar ish' ) }${ ref( 'Baz' ) }</p>
					<h2>Notes</h2>
					<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;}"></div>
				`,
				expected: ve.dm.example.singleLine`
					${ spacer }
					<h2 data-diff-action="none">Notes</h2>
					<ol>
						<li value="1"><p data-diff-action="none">Foo</p></li>
						<li value="2">Bar<ins data-diff-action="insert"> ish</ins></li>
						<li value="3"><p data-diff-action="none">Baz</p></li>
					</ol>
				`
			},
			{
				msg: 'Renumber ref',
				oldDoc: ve.dm.example.singleLine`
					<p>A ${ ref( 'Foo' ) }</p>
					<p>B ${ ref( 'Bar' ) }</p>
					<p>C ${ ref( 'Baz' ) }</p>
				`,
				newDoc: ve.dm.example.singleLine`
					<p>A ${ ref( 'Foo' ) }</p>
					<p>C ${ ref( 'Baz' ) }</p>
				`,
				expected: ve.dm.example.singleLine`
					<p data-diff-action=\"none\">A ${ ref( 'Foo', 1 ) }</p>
					<p data-diff-action=\"remove\">B ${ ref( 'Bar', 2 ) }</p>
					<p data-diff-action=\"none\">C ${ ref( 'Baz', 2 ) }</p>
					<ol>
						<li value=\"1\"><p data-diff-action=\"none\">Foo</p></li>
						<li value=\"2\"><p data-diff-action=\"remove\">Bar</p></li>
						<li value=\"2\"><p data-diff-action=\"none\">Baz</p></li>
					</ol>
				`
			}
		];

	cases.forEach( ( caseItem ) => {
		ve.test.utils.runDiffElementTest( assert, caseItem );
	} );

} );
