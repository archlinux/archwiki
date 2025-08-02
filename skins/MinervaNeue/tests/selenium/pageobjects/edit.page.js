'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class EditPage extends Page {
	get content() {
		return $( '#wikitext-editor' );
	}

	get displayedContent() {
		return $( '#mw-content-text .mw-parser-output' );
	}

	get heading() {
		return $( 'h1.mw-first-heading' );
	}

	// Sync with src/mobile.editor.overlay/EditorOverlayBase.js in MobileFrontend
	get next() {
		return $( '.mf-icon-next-invert' );
	}

	get save() {
		return $( 'button.cdx-button' );
	}

	async openForEditing( title ) {
		return super.openTitle( title, { action: 'edit', mobileaction: 'toggle_view_mobile' } );
	}

	async edit( name, content ) {
		await this.openForEditing( name );
		await this.content.setValue( content );
		await this.next.click();
		await this.save.click();
		browser.acceptAlert();
	}
}

module.exports = new EditPage();
