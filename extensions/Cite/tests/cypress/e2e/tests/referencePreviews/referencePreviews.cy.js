require( '@cypress/skip-test/support' );
import * as helper from '../../utils/functions.helper.js';

const title = helper.getTestString( 'CiteTest-title' );

describe( 'Cite popups integration', () => {
	before( () => {
		cy.visit( '/index.php' );
		helper.waitForMWLoader();

		// Skip tests when Popups extension is not availible
		cy.window().then( async ( win ) => {
			cy.skipOn( !win.mw.loader.getModuleNames().includes( 'ext.popups.main' ) );
		} );

		// Create a new page containing a reference
		const wikiText = 'Lorem ipsum dolor.<ref>small reference</ref>' +
			'Reference with lots of text.<ref>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</ref>' +
			'Lorem ipsum dolor.<ref>reference inception{{#tag:ref|body}}</ref>';

		helper.editPage( title, wikiText );
	} );

	beforeEach( () => {
		helper.visitTitle( title );
		helper.waitForModuleReady( 'ext.cite.referencePreviews' );
	} );

	it( 'simple popup on hover and hide on leave', () => {
		helper.abandonReference( 'cite_ref-1' );
		helper.dwellReference( 'cite_ref-1' );
		cy.get( '.mwe-popups-type-reference', { timeout: 1000 } )
			.should( 'be.visible' );
		helper.assertPreviewIsScrollable( false );
		cy.get( '.mwe-popups-fade-out' ).should( 'not.exist' );

		helper.abandonReference( 'cite_ref-1' );
		cy.get( '.mwe-popups-type-reference' )
			.should( 'not.exist' );
	} );

	it( 'includes scrollbar and fadeout on long previews', () => {
		helper.abandonReference( 'cite_ref-2' );
		helper.dwellReference( 'cite_ref-2' );
		cy.get( '.mwe-popups-type-reference', { timeout: 1000 } )
			.should( 'be.visible' );
		helper.assertPreviewIsScrollable( true );
		cy.get( '.mwe-popups-fade-out' ).should( 'be.visible' );
	} );

	it( 'hovering nested reference', () => {
		helper.abandonReference( 'cite_ref-3' );
		helper.dwellReference( 'cite_ref-3' );
		cy.get( '.mwe-popups-type-reference', { timeout: 1000 } )
			.should( 'be.visible' );
		helper.dwellReference( 'cite_ref-4' );
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait( 1000 );
		cy.get( '.mwe-popups-type-reference' )
			.should( 'include.text', 'reference inception' );
	} );

} );
