'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	Util = require( 'wdio-mediawiki/Util' );

// Adapted from mw-core edit.page.js

class EditPage extends Page {
	get content() {
		return $( '#wpTextbox1' );
	}

	get save() {
		return $( '#wpSave' );
	}

	get summary() {
		return $( '#wpSummary' );
	}

	async openForEditing( title ) {
		await super.openTitle( title, { action: 'submit', vehidebetadialog: 1, hidewelcomedialog: 1 } );
		// Compatibility with CodeMirror extension (T324879)
		await Util.waitForModuleState( 'mediawiki.base' );
		// eslint-disable-next-line no-undef
		const hasToolbar = await this.save.isExisting() && await browser.execute( () => mw.loader.getState( 'ext.wikiEditor' ) !== null );
		if ( !hasToolbar ) {
			return;
		}
		await $( '#wikiEditor-ui-toolbar' ).waitForDisplayed();
		const cmButton = $( '.mw-editbutton-codemirror-active' );
		if ( await cmButton.isExisting() ) {
			await cmButton.click();
			await browser.waitUntil( async () => !( await cmButton.getAttribute( 'class' ) ).includes( 'mw-editbutton-codemirror-active' ) );
		}
	}

	async edit( name, content, summary ) {
		await this.openForEditing( name );
		await this.content.setValue( content );
		await this.summary.setValue( summary );
		await this.save.click();
	}
}

module.exports = new EditPage();
