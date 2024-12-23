import * as helper from '../utils/functions.helper.js';

const title = helper.getTestString( 'CiteTest-title' );

describe( 'Cite backlinks test', () => {
	before( () => {
		helper.visitTitle( '' );

		const wikiText = 'This is reference #1: <ref name="a">This is citation #1 for reference #1 and #2</ref><br> ' +
			'This is reference #2: <ref name="a" /><br>' +
			'This is reference #3: <ref>This is citation #2</ref><br>' +
			'<references />';

		helper.editPage( title, wikiText );
	} );

	beforeEach( () => {
		helper.visitTitle( title );
		helper.waitForModuleReady( 'ext.cite.ux-enhancements' );
	} );

	it( 'hides clickable up arrow by default when there are multiple backlinks', () => {
		helper.getCiteMultiBacklink( 1 ).should( 'not.exist' );
	} );

	it( 'hides clickable up arrow when jumping back from multiple used references', () => {
		helper.getReference( 2 ).click();
		helper.getCiteMultiBacklink( 1 ).click();
		// The up-pointing arrow in the reference line is not linked
		helper.getCiteMultiBacklink( 1 ).should( 'not.be.visible' );
	} );

	it( 'shows clickable up arrow when jumping to multiple used references', () => {
		helper.getReference( 2 ).click();
		helper.getCiteMultiBacklink( 1 ).should( 'be.visible' );

		helper.getFragmentFromLink( helper.getCiteMultiBacklink( 1 ) ).then( ( linkFragment ) => {
			helper.getReference( 2 ).invoke( 'attr', 'id' ).should( 'eq', linkFragment );
		} );
	} );

	it( 'highlights backlink in the reference list for the clicked reference when there are multiple used references', () => {
		cy.get( '.mw-page-title-main' ).should( 'be.visible' );
		helper.getReference( 2 ).click();
		helper.getCiteSubBacklink( 2 ).should( 'have.class', 'mw-cite-targeted-backlink' );
	} );

	it( 'uses the last clicked target for the clickable up arrow on multiple used references', () => {
		helper.getReference( 2 ).click();
		helper.getReference( 1 ).click();

		helper.getFragmentFromLink( helper.getCiteMultiBacklink( 1 ) ).then( ( linkFragment ) => {
			helper.getReference( 1 ).invoke( 'attr', 'id' ).then( ( referenceId ) => {
				expect( linkFragment ).to.equal( referenceId );

			} );
		} );
	} );

	it( 'retains backlink visibility for unnamed references when interacting with other references', () => {
		helper.getReference( 3 ).click();
		helper.getCiteSingleBacklink( 2 ).click();
		// Doesn' matter what is focused next, just needs to be something else
		helper.getReference( 1 ).click();
		// The backlink of the unnamed reference is still visible
		helper.getCiteSingleBacklink( 2 ).should( 'be.visible' );
	} );
} );
