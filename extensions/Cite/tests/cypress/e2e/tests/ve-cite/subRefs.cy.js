require( '@cypress/skip-test/support' );
import * as helper from './../../utils/functions.helper.js';
import * as veHelper from './../../utils/ve.helper.js';

const wikiText =
	'MainRef1<ref name="MainRef1">Content of MainRef1</ref> ' +
	'Reused MainRef1<ref name="MainRef1" /><br>' +

	'MainRef2<ref name="MainRef2">Content of MainRef2</ref> ' +
	'Subref of MainRef2<ref name="MainRef2" details="Details of MainRef2"/><br>' +

	'MainPlusDetailsRef <ref name="MainPlusDetails" details="DetailsContent of MainPlusDetails">' +
	'Content of MainPlusDetails</ref>';

let usesCitoid;

describe( 'Re-using refs in Visual Editor', () => {

	before( () => {
		veHelper.checkModuleDependencies().then( ( deps ) => {
			cy.skipOn( !deps.visualEditor );
			usesCitoid = deps.citoid;
		} );

		cy.clearCookies();
		helper.loginAsAdmin();
		helper.editPage( 'MediaWiki:Cite-tool-definition.json', JSON.stringify( [
			{
				name: 'web',
				title: 'Webseite',
				template: 'Internetquelle'
			},
			{
				name: 'book',
				title: 'Literatur',
				template: 'Literatur'
			}
		] ) );
	} );

	beforeEach( () => {
		const title = helper.getTestString( 'CiteTest-subRefs' );

		cy.clearCookies();
		helper.editPage( title, wikiText );

		veHelper.setVECookiesToDisableDialogs();
		veHelper.openVEForEditingReferences( title, usesCitoid );
	} );

	it( 'should convert references into sub-references', () => {
		// Currently there are 5 ref footnote markers in the article
		helper.getRefsFromArticleSection().should( 'have.length', 5 );

		veHelper.getVEFootnoteMarker( 'MainRef1', 1, 2 ).click();
		cy.contains( '.ve-ui-mwReferenceContextItem-addDetailsButton a', 'Add details' ).click();

		cy.get( '[contenteditable="true"][role="textbox"][aria-label^="Write or paste your additional details"]' )
			.type( 'Details of MainRef1' );

		// Assert the checkbox is initially unchecked
		cy.get( '.oo-ui-checkboxInputWidget input[type="checkbox"]' )
			.should( 'not.be.checked' );
		cy.get( '.oo-ui-checkboxInputWidget input[type="checkbox"]' )
			.check();
		cy.get( '.oo-ui-checkboxInputWidget input[type="checkbox"]' )
			.should( 'be.checked' );

		// Click the "Insert" button in the add-details dialog
		cy.get( '.oo-ui-processDialog-actions-primary > span > a.oo-ui-buttonElement-button' )
			.contains( 'Insert' )
			.click();
		// Close context item popup
		cy.get( '#bodyContent' ).click();

		// MainRef1 and ReusedMainRef1 are now sub-references and appear with corresponding IDs
		// matching the backlinks in the references section
		helper.backlinksIdShouldMatchFootnoteId( 0, 0, 1 );
		helper.backlinksIdShouldMatchFootnoteId( 1, 1, 1 );

		// ARTICLE SECTION
		// Both references have the same footnote number
		cy.get( 'p.ve-ce-paragraphNode > sup.ve-ce-mwReferenceNode span.mw-reflink-text' )
			.eq( 0 )
			.should( 'have.text', '[1.1]' );

		cy.get( 'p.ve-ce-paragraphNode > sup.ve-ce-mwReferenceNode span.mw-reflink-text' )
			.eq( 1 )
			.should( 'have.text', '[1.1]' );

		// REFERENCE SECTION
		helper.getRefFromReferencesSection( 1 ).find( '.reference-text' )
			.should( 'have.text', 'Content of MainRef1' );

		// Assert content of subrefs 1.1
		cy.get( '.ve-ui-surface .mw-references > li > ol > li' )
			.eq( 0 )
			.find( '.reference-text' )
			.should( 'contain.text', 'Details of MainRef1' );

		veHelper.saveEdits();

		// Ref has been added to references section and has correct content
		helper.getRefFromReferencesSection( 1 ).find( '.reference-text' ).eq( 0 )
			.should( 'have.text', 'Content of MainRef1' );
		helper.getRefFromReferencesSection( 2 ).find( '.reference-text' )
			.should( 'have.text', 'Details of MainRef1' );

		// Switch to WikiText Editor
		cy.get( '#p-views #ca-edit' ).click();

		// Assert that the Wikitext contains the updated main and sub-references
		cy.get( 'textarea[name="wpTextbox1"]' )
			.should( 'contain.value',
				'MainRef1<ref name="MainRef1" details="Details of MainRef1">Content of MainRef1</ref> Reused MainRef1<ref name="MainRef1" details="Details of MainRef1" />'
			);
	} );

	it( 'should update edited Main+Details ref content', () => {
		// Main + Details Ref exists in article section
		cy.get( '#mw-content-text p sup' ).eq( 4 );
		helper.getRefsFromArticleSection().eq( 4 ).should( 'contain', '3.1' );

		// And in reference section
		cy.get( '.mw-subreference-list li#cite_note-5' )
			.should( 'contain.text', 'DetailsContent of MainPlusDetails' );

		// Open context item
		cy.get( '.ve-ce-surface sup.ve-ce-mwReferenceNode span.mw-reflink-text' )
			.eq( 4 )
			.should( 'have.text', '[3.1]' ).click();

		cy.get( '.ve-ui-context-menu .ve-ui-mwReferenceContextItem-detailsPreview p' )
			.should( 'have.text', 'DetailsContent of MainPlusDetails' );

		// Open edit details dialog
		cy.get( '.ve-ui-mwReferenceContextItem-editButton' ).click();

		cy.get( '.ve-ui-mwTargetWidget [contenteditable="true"]' ).should( 'have.text', 'DetailsContent of MainPlusDetails' );
		// Modify the sub-reference content of Main + Details Ref
		cy.get( '[contenteditable="true"][role="textbox"][aria-label^="Write or paste your additional details"]' ).type( ' Edited' );

		// Save changes
		cy.contains( '.oo-ui-processDialog-actions-primary a.oo-ui-buttonElement-button', 'Apply changes' )
			.click();

		// Context item has updated details content
		cy.get( '.ve-ui-mwReferenceContextItem-detailsPreview p' )
			.should( 'have.text', 'DetailsContent of MainPlusDetails Edited' );

		// Close context item popup
		cy.get( '#bodyContent' ).click();

		// Reference section displays updated details content and backlinks
		cy.get( '.mw-references ol li .reference-text p' ).last().should( 'have.text', 'DetailsContent of MainPlusDetails Edited' );
		helper.backlinksIdShouldMatchFootnoteId( 4, 0, 5 );

		veHelper.saveEdits();

		// Ref has been edited and has correct content
		helper.getRefFromReferencesSection( 5 ).find( '.reference-text' ).eq( 0 )
			.should( 'have.text', 'DetailsContent of MainPlusDetails Edited' );

		// Switch to WikiText Editor
		cy.get( '#p-views #ca-edit' ).click();

		// Assert that the Wikitext contains Main+Details Ref with updated details content
		cy.get( 'textarea[name="wpTextbox1"]' )
			.should( 'contain.value', '<ref name="MainPlusDetails" details="DetailsContent of MainPlusDetails Edited">Content of MainPlusDetails</ref>' );
	} );

	it( 'should move main ref content to the subRef when the main ref is removed', () => {
		// Assert for main ref with its subRef
		// In article section
		veHelper.getVEFootnoteMarker( 'MainRef2', 2, 1 ).should( 'be.visible' );
		cy.get( 'p.ve-ce-paragraphNode > sup.ve-ce-mwReferenceNode span.mw-reflink-text' )
			.eq( 2 )
			.should( 'have.text', '[2]' );

		// Delete main ref linked by sub-ref
		cy.get( '.ve-ce-surface sup.ve-ce-mwReferenceNode span.mw-reflink-text' )
			.eq( 2 )
			.should( 'have.text', '[2]' )
			.type( '{del}' );

		// Open 2.1 ref context item
		cy.get( '.ve-ce-surface sup.ve-ce-mwReferenceNode span.mw-reflink-text' )
			.eq( 2 )
			.click();

		// Main and details content are present in the context item
		cy.get( '.ve-ui-linearContextItem-body > .ve-ui-previewElement p' )
			.should( 'have.text', 'Content of MainRef2' );
		cy.get( '.ve-ui-mwReferenceContextItem-detailsPreview .ve-ui-previewElement p' )
			.should( 'have.text', 'Details of MainRef2' );

		// Close context item popup
		cy.get( '#bodyContent' ).click();

		// Main and details content are in Reflist
		helper.getRefFromReferencesSection( 2 ).find( '> .reference-text' )
			.should( 'have.text', 'Content of MainRef2' );
		helper.getRefFromReferencesSection( 2 ).find( 'ol > li .reference-text' )
			.should( 'have.text', 'Details of MainRef2' );

		veHelper.saveEdits();

		// Ref has been edited and has correct content
		helper.getRefFromReferencesSection( 2 ).find( '.reference-text' ).eq( 0 )
			.should( 'have.text', 'Content of MainRef2' );

		// Switch to WikiText Editor
		cy.get( '#p-views #ca-edit' ).click();

		// Assert that the Wikitext contains Main+Details Ref with updated details content
		cy.get( 'textarea[name="wpTextbox1"]' )
			.should( 'contain.value', 'Subref of MainRef2<ref name="MainRef2" details="Details of MainRef2">Content of MainRef2</ref>' );
	} );

} );
