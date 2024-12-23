/*
 * This file is part of the MediaWiki extension MediaViewer.
 *
 * MediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

const { HtmlUtils } = require( 'mmv' );

( function () {
	/* eslint-disable no-jquery/no-parse-html-literal */

	QUnit.module( 'mmv.HtmlUtils', QUnit.newMwEnvironment() );

	QUnit.test( 'wrapAndJquerify() for single node', ( assert ) => {
		const $el = $( '<span>' );
		const el = $( '<span>' ).get( 0 );
		const html = '<span></span>';
		const invalid = {};

		assert.strictEqual( HtmlUtils.wrapAndJquerify( $el ).html(), '<span></span>', 'jQuery' );
		assert.strictEqual( HtmlUtils.wrapAndJquerify( el ).html(), '<span></span>', 'HTMLElement' );
		assert.strictEqual( HtmlUtils.wrapAndJquerify( html ).html(), '<span></span>', 'HTML string' );

		assert.throws( () => {
			HtmlUtils.wrapAndJquerify( invalid );
		}, 'throws exception for invalid type' );
	} );

	QUnit.test( 'wrapAndJquerify() for multiple nodes', ( assert ) => {
		const $el = $( '<span></span><span></span>' );
		const html = '<span></span><span></span>';

		assert.strictEqual( HtmlUtils.wrapAndJquerify( $el ).html(), '<span></span><span></span>', 'jQuery' );
		assert.strictEqual( HtmlUtils.wrapAndJquerify( html ).html(), '<span></span><span></span>', 'HTML string' );
	} );

	QUnit.test( 'wrapAndJquerify() for text', ( assert ) => {
		const $el = $( document.createTextNode( 'foo' ) );
		const html = 'foo';

		assert.strictEqual( HtmlUtils.wrapAndJquerify( $el ).html(), 'foo', 'jQuery' );
		assert.strictEqual( HtmlUtils.wrapAndJquerify( html ).html(), 'foo', 'HTML string' );
	} );

	QUnit.test( 'wrapAndJquerify() does not change original', ( assert ) => {
		const $el = $( '<span>' );
		const el = $( '<span>' ).get( 0 );

		HtmlUtils.wrapAndJquerify( $el ).find( 'span' ).data( 'x', 1 );
		HtmlUtils.wrapAndJquerify( el ).find( 'span' ).data( 'x', 1 );
		assert.strictEqual( $el.data( 'x' ), undefined, 'wrapped jQuery element is not the same as original' );
		assert.strictEqual( $( el ).data( 'x' ), undefined, 'wrapped HTMLElement is not the same as original' );
	} );

	QUnit.test( 'filterInvisible()', ( assert ) => {
		const $visibleChild = $( '<div><span></span></div>' );
		const $invisibleChild = $( '<div><span style="display: none"></span></div>' );
		const $styleChild = $( '<div><style></style></div>' );
		const $invisibleChildInVisibleChild = $( '<div><span><abbr style="display: none"></abbr></span></div>' );
		const $visibleChildInInvisibleChild = $( '<div><span style="display: none"><abbr></abbr></span></div>' );
		const $invisibleChildWithVisibleSiblings = $( '<div><span></span><abbr style="display: none"></abbr><b></b></div>' );

		HtmlUtils.filterInvisible( $visibleChild );
		HtmlUtils.filterInvisible( $invisibleChild );
		HtmlUtils.filterInvisible( $styleChild );
		HtmlUtils.filterInvisible( $invisibleChildInVisibleChild );
		HtmlUtils.filterInvisible( $visibleChildInInvisibleChild );
		HtmlUtils.filterInvisible( $invisibleChildWithVisibleSiblings );

		assert.strictEqual( $visibleChild.has( 'span' ).length, 1, 'visible child is not filtered' );
		assert.strictEqual( $invisibleChild.has( 'span' ).length, 0, 'invisible child is filtered' );
		assert.strictEqual( $styleChild.has( 'style' ).length, 0, '<style> child is filtered' );
		assert.strictEqual( $invisibleChildInVisibleChild.has( 'span' ).length, 1, 'visible child is not filtered...' );
		assert.strictEqual( $invisibleChildInVisibleChild.has( 'abbr' ).length, 0, '... but its invisible child is' );
		assert.strictEqual( $visibleChildInInvisibleChild.has( 'span' ).length, 0, 'invisible child is filtered...' );
		assert.strictEqual( $visibleChildInInvisibleChild.has( 'abbr' ).length, 0, '...and its children too' );
		assert.strictEqual( $visibleChild.has( 'span' ).length, 1, 'visible child is not filtered' );
		assert.strictEqual( $invisibleChildWithVisibleSiblings.has( 'abbr' ).length, 0, 'invisible sibling is filtered...' );
		assert.strictEqual( $invisibleChildWithVisibleSiblings.has( 'span' ).length, 1, '...but its visible siblings are not' );
		assert.strictEqual( $invisibleChildWithVisibleSiblings.has( 'b' ).length, 1, '...but its visible siblings are not' );
	} );

	QUnit.test( 'allowlistHtml()', ( assert ) => {
		const $allowlisted = $( '<div>abc<a>def</a>ghi</div>' );
		const $nonAllowlisted = $( '<div>abc<span>def</span>ghi</div>' );
		const $nonAllowlistedInAllowlisted = $( '<div>abc<a>d<span>e</span>f</a>ghi</div>' );
		const $allowlistedInNonAllowlisted = $( '<div>abc<span>d<a>e</a>f</span>ghi</div>' );
		const $siblings = $( '<div>ab<span>c</span>d<a>e</a>f<span>g</span>hi</div>' );

		HtmlUtils.allowlistHtml( $allowlisted, 'a' );
		HtmlUtils.allowlistHtml( $nonAllowlisted, 'a' );
		HtmlUtils.allowlistHtml( $nonAllowlistedInAllowlisted, 'a' );
		HtmlUtils.allowlistHtml( $allowlistedInNonAllowlisted, 'a' );
		HtmlUtils.allowlistHtml( $siblings, 'a' );

		assert.strictEqual( $allowlisted.has( 'a' ).length, 1, 'Allowlisted elements are kept.' );
		assert.strictEqual( $nonAllowlisted.has( 'span' ).length, 0, 'Non-allowlisted elements are removed.' );
		assert.strictEqual( $nonAllowlistedInAllowlisted.has( 'a' ).length, 1, 'Allowlisted parents are kept.' );
		assert.strictEqual( $nonAllowlistedInAllowlisted.has( 'span' ).length, 0, 'Non-allowlisted children are removed.' );
		assert.strictEqual( $allowlistedInNonAllowlisted.has( 'span' ).length, 0, 'Non-allowlisted parents are removed.' );
		assert.strictEqual( $allowlistedInNonAllowlisted.has( 'a' ).length, 1, 'Allowlisted children are kept.' );
		assert.strictEqual( $siblings.has( 'span' ).length, 0, 'Non-allowlisted siblings are removed.' );
		assert.strictEqual( $siblings.has( 'a' ).length, 1, 'Allowlisted siblings are kept.' );
	} );

	QUnit.test( 'appendWhitespaceToBlockElements()', ( assert ) => {
		const $noBlockElement = $( '<div>abc<i>def</i>ghi</div>' );
		const $blockElement = $( '<div>abc<p>def</p>ghi</div>' );
		const $linebreak = $( '<div>abc<br>def</div>' );

		HtmlUtils.appendWhitespaceToBlockElements( $noBlockElement );
		HtmlUtils.appendWhitespaceToBlockElements( $blockElement );
		HtmlUtils.appendWhitespaceToBlockElements( $linebreak );

		assert.true( /abcdefghi/.test( $noBlockElement.text() ), 'Non-block elemens are not whitespaced.' );
		assert.true( /abc\s+def\s+ghi/.test( $blockElement.text() ), 'Block elemens are whitespaced.' );
		assert.true( /abc\s+def/.test( $linebreak.text() ), 'Linebreaks are whitespaced.' );
	} );

	QUnit.test( 'jqueryToHtml()', ( assert ) => {

		assert.strictEqual( HtmlUtils.jqueryToHtml( $( '<a>' ) ), '<a></a>',
			'works for single element' );
		assert.strictEqual( HtmlUtils.jqueryToHtml( $( '<b><a>foo</a></b>' ) ), '<b><a>foo</a></b>',
			'works for complex element' );
		assert.strictEqual( HtmlUtils.jqueryToHtml( $( '<a>foo</a>' ).contents() ), 'foo',
			'works for text nodes' );
	} );

	QUnit.test( 'mergeWhitespace()', ( assert ) => {
		assert.strictEqual( HtmlUtils.mergeWhitespace( ' x \n' ), 'x',
			'leading/trainling whitespace is trimmed' );
		assert.strictEqual( HtmlUtils.mergeWhitespace( 'x \n\n \n y' ), 'x\ny',
			'whitespace containing a newline is collapsed into a single newline' );
		assert.strictEqual( HtmlUtils.mergeWhitespace( 'x   y' ), 'x y',
			'multiple spaces are collapsed into a single one' );
	} );

	QUnit.test( 'htmlToText()', ( assert ) => {
		const html = '<table><tr><td>Foo</td><td><a>bar</a></td><td style="display: none">baz</td></tr></table>';

		assert.strictEqual( HtmlUtils.htmlToText( html ), 'Foo bar', 'works' );
	} );

	QUnit.test( 'htmlToTextWithLinks()', ( assert ) => {
		const html = '<table><tr><td><b>F</b>o<i>o</i></td><td><a>bar</a></td><td style="display: none">baz</td></tr></table>';

		assert.strictEqual( HtmlUtils.htmlToTextWithLinks( html ), 'Foo <a>bar</a>', 'works' );
	} );

	QUnit.test( 'htmlToTextWithTags()', ( assert ) => {
		const html = '<table><tr><td><b>F</b>o<i>o</i><sub>o</sub><sup>o</sup></td><td><a>bar</a></td><td style="display: none">baz</td></tr></table>';

		assert.strictEqual( HtmlUtils.htmlToTextWithTags( html ), '<b>F</b>o<i>o</i><sub>o</sub><sup>o</sup> <a>bar</a>', 'works' );
	} );

	QUnit.test( 'isJQueryOrHTMLElement()', ( assert ) => {
		assert.strictEqual( HtmlUtils.isJQueryOrHTMLElement( $( '<span>' ) ), true, 'Recognizes jQuery objects correctly' );
		assert.strictEqual( HtmlUtils.isJQueryOrHTMLElement( $( '<span>' ).get( 0 ) ), true, 'Recognizes HTMLElements correctly' );
		assert.strictEqual( HtmlUtils.isJQueryOrHTMLElement( '<span></span>' ), false, 'Doesn\'t recognize HTML string' );
	} );

	QUnit.test( 'makeLinkText()', ( assert ) => {
		assert.strictEqual( HtmlUtils.makeLinkText( 'foo', {
			href: 'http://example.com',
			title: 'h<b>t</b><i>m</i>l'
		} ), '<a href="http://example.com" title="html">foo</a>', 'works' );
	} );

	/* eslint-enable no-jquery/no-parse-html-literal */
}() );
