'use strict';
const Page = require( 'wdio-mediawiki/Page' );

class EditPage extends Page {

	get content() { return $( '#content' ); }
	get edited() { return $( '*=Your edit was saved' ); }
	get notices() { return $( '.ve-ui-mwNoticesPopupTool-items' ); }
	get notification() { return $( 'div.mw-notification-content span.oo-ui-labelElement-label' ); }
	get savePage() { return $( '.ve-ui-overlay-global .oo-ui-processDialog-actions-primary' ); }
	get savePageDots() { return $( '.ve-ui-toolbar-saveButton' ); }
	get toolbar() { return $( '.ve-init-mw-desktopArticleTarget-toolbar-open' ); }
	get veBodyContent() { return $( '.mw-body-content.ve-ui-surface' ); }
	get veRootNode() { return $( '.ve-ce-rootNode[role="textbox"]' ); }

	openForEditing( title ) {
		super.openTitle( title, { veaction: 'edit', cxhidebetapopup: 1, hidewelcomedialog: 1, vehidebetadialog: 1 } );
	}

	activationComplete() {
		return browser.executeAsync( function ( done ) {
			mw.hook( 've.activationComplete' ).add( function () {
				done();
			} );
		} );
	}

	saveComplete() {
		return browser.executeAsync( function ( done ) {
			ve.init.target.on( 'save', function () {
				done();
			} );
		} );
	}

}
module.exports = new EditPage();
