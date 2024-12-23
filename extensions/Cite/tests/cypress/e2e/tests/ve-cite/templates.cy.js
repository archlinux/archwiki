import * as helper from './../../utils/functions.helper.js';
import * as veHelper from './../../utils/ve.helper.js';

const refText1 = 'This is citation #1 for reference #1';

const wikiText = `This is reference #1: <ref name="a">${ refText1 }</ref><br> ` +
	'<references />';

let usesCitoid;

describe( 'Re-using refs in Visual Editor using templates', () => {

	before( () => {
		cy.clearCookies();
		helper.loginAsAdmin();

		helper.editPage( 'MediaWiki:Cite-tool-definition.json', JSON.stringify( [
			{
				name: 'Webseite',
				icon: 'ref-cite-web',
				template: 'Internetquelle'
			},
			{
				name: 'Literatur',
				icon: 'ref-cite-book',
				template: 'Literatur'
			}
		] ) );
	} );

	beforeEach( () => {
		const title = helper.getTestString( 'CiteTest-templates' );

		cy.clearCookies();
		helper.editPage( title, wikiText );

		cy.window().then( async ( win ) => {
			usesCitoid = win.mw.loader.getModuleNames().includes( 'ext.citoid.visualEditor' );
		} );

		veHelper.setVECookiesToDisableDialogs();
		veHelper.openVEForEditingReferences( title, usesCitoid );
	} );

	it( 'should add a template reference and verify correct content in both saved and edit mode', () => {
		cy.contains( '.mw-reflink-text', '[1]' ).type( '{rightarrow}' );

		if ( usesCitoid ) {
			cy.get( '.ve-ui-toolbar-group-citoid' ).click();

			// Switch to Manual tab
			// TODO: Sometimes enabling the tab does not work right away.
			// eslint-disable-next-line cypress/no-unnecessary-waiting
			cy.wait( 500 );
			cy.get( '.oo-ui-tabSelectWidget .oo-ui-labelElement-label' ).contains( 'Manual' ).click();

			cy.get( '.oo-ui-labelElement-label' ).contains( 'Literatur' )
				.should( 'be.visible' );
			cy.get( '.oo-ui-labelElement-label' ).contains( 'Webseite' ).click();

		} else {
			cy.get( '.ve-ui-toolbar-group-cite' ).click();
			cy.get( '.oo-ui-tool-name-cite-Literatur' ).contains( 'Literatur' )
				.should( 'be.visible' );
			cy.get( '.oo-ui-tool-name-cite-Webseite' ).contains( 'Webseite' ).click();
		}

		// Tempalte dialog is displayed with correct content
		cy.get( '.ve-ui-mwTemplateDialog .oo-ui-processDialog-title' )
			.should( 'have.text', 'Webseite' );
		cy.get( '.ve-ui-mwTemplateDialog .ve-ui-mwTemplatePage .oo-ui-labelElement-label' )
			.should( 'have.text', 'Internetquelle' );

		// Add undocumented parameter
		cy.get( '.ve-ui-mwTransclusionDialog-addParameterFieldset-header' ).click();
		cy.get( '.ve-ui-mwTransclusionDialog-addParameterFieldset-input' ).type( 'test' );
		cy.get( '.ve-ui-mwTransclusionDialog-addParameterFieldset-input .oo-ui-actionFieldLayout-button .oo-ui-buttonElement-button' ).click();
		cy.get( '.ve-ui-mwParameterPage-field' ).type( 'test' );
		// Click on insert button
		cy.get( '.ve-ui-mwTemplateDialog .oo-ui-processDialog-actions-primary .oo-ui-buttonElement-button' ).click();
		cy.get( '.ve-ui-toolbar-saveButton' ).click();
		// Click save changes button
		cy.get( '.ve-ui-mwSaveDialog .oo-ui-processDialog-navigation .oo-ui-flaggedElement-primary .oo-ui-buttonElement-button' ).click();

		// Success notification should be visible
		cy.get( '.mw-notification-visible .oo-ui-icon-success' ).should( 'be.visible' );

		// Ref has been added to references section and has correct content
		helper.getRefFromReferencesSection( 2 ).find( '.reference-text' ).should( 'have.text', 'Template:Internetquelle' );
	} );
} );
