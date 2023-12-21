const portlets = require( '../../../resources/skins.vector.js/portlets.js' );
const mustache = require( 'mustache' );
const fs = require( 'fs' );
const menuTemplate = fs.readFileSync( 'includes/templates/Menu.mustache', 'utf8' );
const menuContentsTemplate = fs.readFileSync( 'includes/templates/MenuContents.mustache', 'utf8' );

describe( 'portlets', () => {
	test( 'portlets that go through the hook method should match the menu template HTML', () => {
		const id = 'foo';
		const label = 'label text';
		const portletHTML = mustache.render( menuTemplate, {
			id, class: '', label: label
		}, {
			MenuContents: menuContentsTemplate
		} );

		const element = document.createElement( 'div' );
		element.id = id;
		if ( label ) {
			const labelElement = document.createElement( 'label' );
			labelElement.innerText = label;
			element.appendChild( labelElement );
		}
		element.appendChild( document.createElement( 'ul' ) );
		expect( portlets.addPortletHandler( element, label ).outerHTML.replace( /[\s\n]/gi, '' ) )
			.toBe( portletHTML.replace( /[\s\n]/gi, '' ) );
	} );
} );
