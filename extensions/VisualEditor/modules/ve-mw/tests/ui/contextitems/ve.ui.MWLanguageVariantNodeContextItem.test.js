/*!
 * VisualEditor UserInterface MWLanguageVariantNodeContextItem tests.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

QUnit.module( 've.ui.MWLanguageVariantNodeContextItem', ve.test.utils.newMwEnvironment() );

QUnit.test.each( 'renderBody sanitizes variant text', {
	twoway: { twoway: [ { l: 'en', t: '<b onmouseover="alert(1)">Bar</b><script>alert(1)</script>' } ] },
	oneway: { oneway: [ {
		f: '<b onmouseover="alert(1)">Bar</b><script>alert(1)</script>',
		l: 'en',
		t: '<b onmouseover="alert(1)">Baz</b><script>alert(1)</script>'
	} ] }
}, ( assert, variantInfo ) => {
	const surface = ve.test.utils.createSurfaceFromHtml( '<p></p>' ),
		model = new ve.dm.MWLanguageVariantInlineNode( { type: 'mwLanguageVariantInline', attributes: { variantInfo } } ),
		contextItem = new ve.ui.MWLanguageVariantNodeContextItem( surface.getContext(), model );

	contextItem.renderBody();

	assert.notStrictEqual( contextItem.$body.find( 'b' ).length, 0, 'variant text is rendered as HTML' );
	assert.strictEqual( contextItem.$body.find( '[onmouseover]' ).length, 0, 'event handler removed' );
	assert.strictEqual( contextItem.$body.find( 'script' ).length, 0, 'script removed' );
	surface.destroy();
} );
