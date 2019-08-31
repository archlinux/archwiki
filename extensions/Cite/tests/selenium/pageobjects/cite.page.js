const Page = require( 'wdio-mediawiki/Page' );

class CitePage extends Page {
	getReference( num ) { return browser.elements( '#mw-content-text .reference' ).value[ num - 1 ]; }
	getCiteMultiBacklink( num ) { return browser.element( '.references li:nth-of-type(' + num + ') .mw-cite-up-arrow-backlink' ); }
	getCiteSingleBacklink( num ) { return browser.element( '.references li:nth-of-type(' + num + ') .mw-cite-backlink a' ); }
	getCiteSubBacklink( num ) { return browser.element( '.mw-cite-backlink sup:nth-of-type(' + num + ') a' ); }

	resourceLoaderModuleStatus( moduleName, moduleStatus, errMsg ) {
		// Word of caution: browser.waitUntil returns a Timer class NOT a Promise.
		// Webdriver IO will run waitUntil synchronously so not returning it will
		// block JavaScript execution while returning it will not.
		// http://webdriver.io/api/utility/waitUntil.html
		// https://github.com/webdriverio/webdriverio/blob/master/lib/utils/Timer.js
		browser.waitUntil( () => {
			const result = browser.execute( ( module ) => {
				return typeof mw !== 'undefined' &&
					mw.loader.getState( module.name ) === module.status;
			}, { status: moduleStatus, name: moduleName } );
			return result.value;
		}, 10000, errMsg );
	}

	scriptsReady() {
		this.resourceLoaderModuleStatus( 'ext.cite.ux-enhancements', 'ready', 'Cite scripts did not load' );
	}

	getFragmentFromLink( linkElement ) {
		// the href includes the full url so slice the fragment from it
		let href = linkElement.getAttribute( 'href' );
		return href.slice( href.indexOf( '#' ) + 1 );
	}
}

module.exports = new CitePage();
