/*!
 * VisualEditor MW-specific DiffElement tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ui.DiffElement (MW)', ve.test.utils.newMwEnvironment() );

QUnit.test( 'Diffing', ( assert ) => {
	const fixBase = ( body ) =>
			'<html><head><base href="' + ve.dm.example.baseUri + '"></head><body>' + body + '</body></html>',
		cases = [
			{
				msg: 'Change template param',
				oldDoc: fixBase( ve.dm.mwExample.MWTransclusion.blockOpen + ve.dm.mwExample.MWTransclusion.blockContent ),
				newDoc: fixBase( ve.dm.mwExample.MWTransclusion.blockOpenModified + ve.dm.mwExample.MWTransclusion.blockContent ),
				expected:
					( ve.dm.mwExample.MWTransclusion.blockOpenModified + ve.dm.mwExample.MWTransclusion.blockContent )
						// FIXME: Use DOM modification instead of string replaces
						.replace( /#mwt1"/g, '#mwt1" data-diff-action="structural-change" data-diff-id="0"' ),
				expectedDescriptions: [
					'<div>visualeditor-changedesc-mwtransclusion</div>' +
					'<div><ul><li>visualeditor-changedesc-changed-diff,1,<span>Hello, <del>world</del><ins>globe</ins>!</span></li></ul></div>'
				]
			},
			{
				msg: 'Changed width of block image',
				oldDoc: fixBase( ve.dm.mwExample.MWBlockImage.html ),
				newDoc: fixBase( ve.dm.mwExample.MWBlockImage.html.replace( 'width="1"', 'width="3"' ) ),
				expected:
					ve.dm.mwExample.MWBlockImage.html
						// FIXME: Use DOM modification instead of string replaces
						.replace( 'width="1"', 'width="3"' )
						.replace( 'href="./Foo"', 'href="' + ve.resolveUrl( './Foo', ve.dm.example.base ) + '"' )
						.replace( 'foobar"', 'foobar" data-diff-action="structural-change" data-diff-id="0"' ),
				expectedDescriptions: [
					'<div>visualeditor-changedesc-image-size,' +
					'<del>1visualeditor-dimensionswidget-times2visualeditor-dimensionswidget-px</del>,' +
					'<ins>3visualeditor-dimensionswidget-times2visualeditor-dimensionswidget-px</ins></div>'
				]
			}
		];

	for ( let i = 0; i < cases.length; i++ ) {
		ve.test.utils.runDiffElementTest( assert, cases[ i ] );
	}

} );
