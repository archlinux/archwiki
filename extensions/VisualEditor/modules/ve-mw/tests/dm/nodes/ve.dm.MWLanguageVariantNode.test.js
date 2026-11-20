/*!
 * VisualEditor DataModel MWLanguageVariantNode tests.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

QUnit.module( 've.dm.MWLanguageVariantNode', ve.test.utils.newMwEnvironment() );

QUnit.test.each( 'getPreviewHtml escapes language codes', {
	name: {
		variantInfo: { name: { t: '<img src=x onerror=alert(1)>' } },
		expected: '&lt;img src=x onerror=alert(1)&gt;'
	},
	twoway: {
		variantInfo: {
			describe: true,
			twoway: [ { l: '<img src=x onerror=alert(1)>', t: '<b>foo</b>' } ]
		},
		expected: '&lt;img src=x onerror=alert(1)&gt;:<b>foo</b>;'
	},
	oneway: {
		variantInfo: {
			describe: true,
			oneway: [ { f: '<i>a</i>', l: '"><img src=x>', t: 'b' } ]
		},
		expected: '<i>a</i>⇒&quot;&gt;&lt;img src=x&gt;:b;'
	}
}, function ( assert, data ) {
	// Make all codes unknown, so that the raw code is returned.
	this.sandbox.stub( ve.init.platform, 'getLanguageName' ).callsFake( ( code ) => code );

	assert.strictEqual(
		ve.dm.MWLanguageVariantNode.static.getPreviewHtml( data.variantInfo ),
		data.expected
	);
} );

QUnit.test( 'insertPreviewElements does not create elements from language codes', function ( assert ) {
	this.sandbox.stub( ve.init.platform, 'getLanguageName' ).callsFake( ( code ) => code );
	// Use an inert document, so that a failure does not run the payload.
	const element = document.implementation.createHTMLDocument( '' ).createElement( 'span' );

	ve.dm.MWLanguageVariantNode.static.insertPreviewElements( element, {
		describe: true,
		twoway: [ { l: '<img src=x onerror=alert(1)>', t: 'foo' } ]
	} );

	assert.strictEqual( element.querySelector( 'img' ), null );
	assert.strictEqual( element.textContent, '<img src=x onerror=alert(1)>:foo;' );
} );

QUnit.test( 'language codes entered in the editor are stored unescaped', function ( assert ) {
	this.sandbox.stub( ve.init.platform, 'getLanguageName' ).callsFake( ( code ) => code );
	// Use an inert document, so that a failure does not run the payload.
	const doc = document.implementation.createHTMLDocument( '' ),
		// As if typed into the language input of the language variant inspector
		variantInfo = {
			describe: true,
			twoway: [ { l: '<img src=x onerror=alert(1)>', t: 'foo' } ]
		},
		dataElement = { type: 'mwLanguageVariantInline', attributes: { variantInfo } },
		converter = {
			doesModeNeedRendering: () => true,
			isExpectingContent: () => true,
			isInWrapper: () => false
		};

	const domElement = ve.dm.MWLanguageVariantInlineNode.static.toDomElements( dataElement, doc, converter )[ 0 ];
	assert.deepEqual( JSON.parse( domElement.getAttribute( 'data-mw-variant' ) ), variantInfo, 'data-mw-variant' );
	assert.strictEqual( domElement.querySelector( 'img' ), null, 'preview has no elements from the code' );
	assert.strictEqual( domElement.textContent, '<img src=x onerror=alert(1)>:foo;', 'preview is escaped once' );

	assert.deepEqual(
		ve.dm.MWLanguageVariantInlineNode.static.toDataElement( [ domElement ], converter ).attributes.variantInfo,
		variantInfo,
		'round trip'
	);
	assert.strictEqual(
		ve.ce.MWLanguageVariantNode.static.getDescription( new ve.dm.MWLanguageVariantInlineNode( dataElement ) ),
		ve.msg( 'visualeditor-mwlanguagevariant-twoway', '<img src=x onerror=alert(1)>' ),
		'plain-text description is not escaped'
	);
} );

// Variant text which didn't come from Parsoid, e.g. when pasted (T441027)
const unsafeText = '<b onmouseover="alert(1)">Bar</b><script>alert(1)</script>';

QUnit.test.each( 'insertPreviewElements sanitizes variant text', {
	disabled: { disabled: { t: unsafeText } },
	filter: { filter: { l: [ 'en' ], t: unsafeText } },
	twoway: { describe: true, twoway: [ { l: 'en', t: unsafeText } ] },
	'oneway, from': { describe: true, oneway: [ { f: unsafeText, l: 'en', t: 'foo' } ] },
	'oneway, to': { describe: true, oneway: [ { f: 'foo', l: 'en', t: unsafeText } ] },
	nested: { disabled: { t: '<span typeof="mw:LanguageVariant" data-mw-variant="' +
		ve.escapeHtml( JSON.stringify( { disabled: { t: unsafeText } } ) ) + '"></span>' } }
}, ( assert, variantInfo ) => {
	// Use an inert document, so that a failure does not run the payload.
	const element = document.implementation.createHTMLDocument( '' ).createElement( 'span' );

	ve.dm.MWLanguageVariantNode.static.insertPreviewElements( element, variantInfo );

	assert.notStrictEqual( element.querySelector( 'b' ), null, 'variant text is rendered as HTML' );
	assert.strictEqual( element.querySelector( '[onmouseover]' ), null, 'event handler removed' );
	assert.strictEqual( element.querySelector( 'script' ), null, 'script removed' );
} );

QUnit.test( 'unsafe variant text is kept in the model', ( assert ) => {
	const doc = document.implementation.createHTMLDocument( '' ),
		variantInfo = { disabled: { t: unsafeText } },
		dataElement = { type: 'mwLanguageVariantInline', attributes: { variantInfo } },
		converter = { doesModeNeedRendering: () => true };

	const domElement = ve.dm.MWLanguageVariantInlineNode.static.toDomElements( dataElement, doc, converter )[ 0 ];
	assert.deepEqual( JSON.parse( domElement.getAttribute( 'data-mw-variant' ) ), variantInfo, 'data-mw-variant is unchanged' );
	assert.strictEqual( domElement.querySelector( '[onmouseover]' ), null, 'preview is sanitized' );
} );
