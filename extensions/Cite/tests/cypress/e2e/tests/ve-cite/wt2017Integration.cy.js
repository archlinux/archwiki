/* eslint-disable cypress/no-unnecessary-waiting */

import * as helper from './../../utils/functions.helper.js';
import * as veHelper from './../../utils/ve.helper.js';

const title = helper.getTestString( 'CiteTest-title' );

const wikiText = '';

let usesCitoid;

describe( 'Visual Editor Wt 2017 Cite Integration', () => {
	before( () => {
		helper.loginAsAdmin();

		helper.editPage( 'MediaWiki:Cite-tool-definition.json', JSON.stringify( [
			{
				name: 'Webseite',
				icon: 'ref-cite-web',
				template: 'Internetquelle'
			}
		] ) );
	} );

	beforeEach( () => {
		cy.clearCookies();
		helper.editPage( title, wikiText );

		cy.window().then( async ( win ) => {
			usesCitoid = win.mw.loader.getModuleNames().includes( 'ext.citoid.visualEditor' );
		} );

		veHelper.setVECookiesToDisableDialogs();
		veHelper.openVEForSourceEditingReferences( title, usesCitoid );

	} );

	it( 'should be able to create a basic reference', () => {
		// FIXME: Fix application logic to only render once fully initialized.
		cy.wait( 1000 );
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
		cy.get( '.ve-ui-toolbar-saveButton' ).click();
		cy.wait( 500 );
		cy.get( '.oo-ui-labelElement-label' ).contains( 'Save changes' ).click( { force: true } );

		// Success notification should be visible
		cy.get( '.mw-notification-visible .oo-ui-icon-success' ).should( 'be.visible' );

		// Ref has been added to references section and has correct content
		helper.getRefFromReferencesSection( 1 ).find( '.reference-text' ).should( 'have.text', 'Basic ref' );

	} );

	it( 'should be able to create a VE-Cite tool template', () => {
		// FIXME: Replace this wait with a trigger when VE is fully initialized.
		cy.wait( 1000 );
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
		cy.get( '.ve-ui-toolbar-saveButton' ).click();

		// Ref tag with template and added parameter has been created
		cy.get( '.ve-ui-mwWikitextSurface' ).should( 'contain.text', '<ref>{{Internetquelle|t=t}}</ref>' );

		// Save changes
		cy.get( '.ve-ui-toolbar-saveButton' ).click();
		cy.wait( 500 );
		cy.get( '.oo-ui-labelElement-label' ).contains( 'Save changes' ).click( { force: true } );

		// Success notification should be visible
		cy.get( '.mw-notification-visible .oo-ui-icon-success' ).should( 'be.visible' );

		// Ref has been added to references section and has correct content
		helper.getRefFromReferencesSection( 1 ).find( '.reference-text' ).should( 'have.text', 'Template:Internetquelle' );
	} );

} );
