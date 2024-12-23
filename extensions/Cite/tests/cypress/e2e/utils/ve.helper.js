import * as helpers from './functions.helper.js';

export function setVECookiesToDisableDialogs() {
	cy.window().then( async ( win ) => {
		win.localStorage.setItem( 've-beta-welcome-dialog', 1 );
		// Don't show the VE education popups with the blue
		// pulsating dots (ve.ui.MWEducationPopupWidget)
		win.localStorage.setItem( 've-hideusered', 1 );
	} );
}

export function openVEForEditingReferences( title, usesCitoid ) {
	helpers.visitTitle( title, { veaction: 'edit' } );
	waitForVECiteToLoad();
	if ( usesCitoid ) {
		waitForVECitoidToLoad();
	}
}

export function openVEForSourceEditingReferences( title, usesCitoid ) {
	helpers.visitTitle( title, { veaction: 'editsource' } );
	waitForVECiteToLoad();
	if ( usesCitoid ) {
		waitForVECitoidToLoad();
	}
}

export function waitForVECiteToLoad() {
	cy.get( '.ve-init-mw-desktopArticleTarget-toolbar-open', { timeout: 20000 } )
		.should( 'be.visible' );
	helpers.waitForModuleReady( 'ext.cite.visualEditor' );
}

export function waitForVECitoidToLoad() {
	helpers.waitForModuleReady( 'ext.citoid.visualEditor' );
}

export function getVEFootnoteMarker( refName, sequenceNumber, index ) {
	return cy.get( `sup.ve-ce-mwReferenceNode#cite_ref-${ refName }_${ sequenceNumber }-${ index - 1 }` );
}

export function getVEReferenceContextItem() {
	return cy.get( '.ve-ui-context-menu .ve-ui-mwReferenceContextItem' );
}

export function getVEReferenceContextItemEdit() {
	return cy.get( '.ve-ui-context-menu .ve-ui-mwReferenceContextItem .oo-ui-buttonElement-button' );
}

export function getVEReferenceEditDialog() {
	return cy.get( '.ve-ui-mwReferenceDialog' );
}

export function openVECiteReuseDialog() {
	helpers.clickUntilVisible(
		cy.get( '.ve-ui-toolbar-group-cite' ),
		'.ve-ui-toolbar .oo-ui-tool-name-reference-existing'
	);
	cy.get( '.ve-ui-toolbar .oo-ui-tool-name-reference-existing' ).click();
}

export function openVECiteoidReuseDialog() {
	cy.get( '.ve-ui-toolbar-group-citoid' ).click();
	// TODO: Sometimes enabling the tab does not work right away.
	// eslint-disable-next-line cypress/no-unnecessary-waiting
	cy.wait( 500 );
	cy.get( '.oo-ui-tabSelectWidget .oo-ui-labelElement-label' ).contains( 'Re-use' ).click();
}

export function saveEdits() {
	// TODO: Even if the button is enabled it seems we need a delay before we can click it.
	// eslint-disable-next-line cypress/no-unnecessary-waiting
	cy.wait( 500 );
	cy.get( '.ve-ui-toolbar-saveButton' ).click();
	cy.get( '.ve-ui-mwSaveDialog .oo-ui-processDialog-actions-primary .oo-ui-buttonWidget' ).click();
	cy.get( '.mw-notification-visible .oo-ui-icon-success' ).should( 'be.visible' );
}

export function getCiteReuseDialogRefResult( rowNumber ) {
	return cy.get( '.ve-ui-mwReferenceSearchWidget .ve-ui-mwReferenceResultWidget' )
		.eq( rowNumber - 1 );
}

export function getCiteReuseDialogRefResultName( rowNumber ) {
	return cy.get( '.ve-ui-mwReferenceSearchWidget .ve-ui-mwReferenceResultWidget .ve-ui-mwReferenceResultWidget-name' )
		.eq( rowNumber - 1 );
}

export function getCiteReuseDialogRefResultCitation( rowNumber ) {
	return cy.get( '.ve-ui-mwReferenceSearchWidget .ve-ui-mwReferenceResultWidget .ve-ui-mwReferenceResultWidget-footnote' )
		.eq( rowNumber - 1 );
}

export function getCiteReuseDialogRefText( rowNumber ) {
	return cy.get( '.oo-ui-widget.oo-ui-widget-enabled .ve-ui-mwReferenceResultWidget .ve-ce-paragraphNode' )
		.eq( rowNumber - 1 );
}
