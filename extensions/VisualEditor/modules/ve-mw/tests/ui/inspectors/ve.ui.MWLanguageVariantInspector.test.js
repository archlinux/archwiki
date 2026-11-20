/*!
 * VisualEditor UserInterface MWLanguageVariantInspector tests.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

QUnit.module( 've.ui.MWLanguageVariantInspector', ve.test.utils.newMwEnvironment() );

QUnit.test( 'setupTextTargetDoc sanitizes variant text', ( assert ) => {
	const surface = ve.test.utils.createSurfaceFromHtml( '<p></p>' ),
		// Unrecognized elements become alien nodes, which are rendered as-is
		html = '<b onmouseover="alert(1)">Bar</b><img onerror="alert(1)"><span typeof="mw:Foo" onclick="alert(1)">Baz</span>',
		inspector = { variantNode: { getDocument: () => surface.getModel().getDocument() } },
		textTarget = { setDocument: () => {} };

	const doc = ve.ui.MWLanguageVariantInspector.prototype.setupTextTargetDoc.call( inspector, textTarget, html );
	const view = new ve.ce.Surface( new ve.dm.Surface( doc ), surface );
	view.initialize();

	assert.notStrictEqual( view.$element.find( 'b' ).length, 0, 'variant text is rendered' );
	assert.strictEqual( view.$element.find( '[onmouseover],[onerror],[onclick]' ).length, 0, 'event handlers removed' );
	view.destroy();
	surface.destroy();
} );
