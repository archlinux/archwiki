/* eslint-disable cypress/no-unnecessary-waiting */

require( '@cypress/skip-test/support' );
import * as helper from './../../utils/functions.helper.js';
import * as veHelper from './../../utils/ve.helper.js';

const title = helper.getTestString( 'CiteTest-title' );

const wikiText = '';

let usesCitoid, usesTemplateData;

describe( 'Visual Editor Wt 2017 Cite Integration', () => {
	before( () => {
		veHelper.checkModuleDependencies().then( ( deps ) => {
			cy.skipOn( !deps.visualEditor );
			usesCitoid = deps.citoid;
			usesTemplateData = deps.templateData;
		} );

		helper.loginAsAdmin();
		helper.editPage( 'MediaWiki:Cite-tool-definition.json', JSON.stringify( [
			{
				name: 'web',
				title: 'Webseite',
				template: 'Internetquelle'
			}
		] ) );
	} );

	beforeEach( () => {
		cy.clearCookies();
		helper.editPage( title, wikiText );

		veHelper.setVECookiesToDisableDialogs();
		veHelper.openVEForSourceEditingReferences( title, usesCitoid );

	} );

	it( 'should be able to create a basic reference', () => {
		if ( usesCitoid ) {
			cy.get( '.ve-ui-toolbar-group-citoid' ).click();
			cy.wait( 500 );
			cy.get( '.oo-ui-tabSelectWidget .oo-ui-labelElement-label', { timeout: 5000 } ).should( 'be.visible' ).contains( 'Manual' ).click();
			cy.wait( 500 );
			cy.get( '.ve-ui-citeSourceSelectWidget-basic' ).click();
		} else {
			cy.get( '.ve-ui-toolbar-group-cite' ).click();
			cy.get( '.oo-ui-popupToolGroup-active-tools .oo-ui-tool-title', { timeout: 5000 } ).should( 'be.visible' ).contains( 'Basic' ).click();
		}

		cy.get( '.ve-ui-mwReferenceDialog .mw-content-ltr' ).type( 'Basic ref' );
		// Save changes
		cy.get( '.ve-ui-mwReferenceDialog .oo-ui-flaggedElement-primary' ).click();

		// Ref tag appears with correct content in edit source mode
		cy.get( '.ve-ui-mwWikitextSurface' ).should( 'contain.text', '<ref>Basic ref</ref>' );

		// Save changes
		veHelper.saveEdits();

		// Ref has been added to references section and has correct content
		helper.getRefFromReferencesSection( 1 ).find( '.reference-text' ).should( 'have.text', 'Basic ref' );

	} );

	it( 'should be able to create a VE-Cite tool template', () => {
		cy.skipOn( !usesTemplateData );

		if ( usesCitoid ) {
			cy.get( '.ve-ui-toolbar-group-citoid' ).click();
			cy.wait( 500 );
			cy.get( '.oo-ui-tabSelectWidget .oo-ui-labelElement-label', { timeout: 5000 } ).should( 'be.visible' ).contains( 'Manual' ).click();
			cy.wait( 500 );
			cy.get( '.oo-ui-labelElement-label' ).contains( 'Webseite' ).click();
		} else {
			cy.get( '.ve-ui-toolbar-group-cite' ).click();
			cy.get( '.oo-ui-popupToolGroup-active-tools .oo-ui-tool-title', { timeout: 5000 } ).should( 'be.visible' ).contains( 'Webseite' ).click();
		}

		// Add undocumented parameter
		cy.get( '.ve-ui-mwTransclusionDialog-addParameterFieldset-header' ).click();
		cy.get( '.ve-ui-mwTransclusionDialog-addParameterFieldset-input' ).type( 't' );
		cy.get( '.ve-ui-mwTransclusionDialog-addParameterFieldset-input .oo-ui-actionFieldLayout-button .oo-ui-buttonElement-button' ).click();
		cy.get( '.ve-ui-mwParameterPage-field' ).type( 't' );
		// Click on insert button
		cy.get( '.ve-ui-mwTemplateDialog .oo-ui-processDialog-actions-primary .oo-ui-buttonElement-button' ).click();

		// Ref tag with template and added parameter has been created
		cy.get( '.ve-ui-mwWikitextSurface' ).should( 'contain.text', '<ref>{{Internetquelle|t=t}}</ref>' );

		// Save changes
		veHelper.saveEdits();

		// Ref has been added to references section and has correct content
		helper.getRefFromReferencesSection( 1 ).find( '.reference-text' ).should( 'have.text', 'Template:Internetquelle' );
	} );

} );
