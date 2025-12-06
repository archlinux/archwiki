const { getAutoRevealStatus } = require( './ipRevealUtils.js' );

/**
 * Run code when the page loads.
 *
 * @param {string|*} documentRoot A Document or selector to use as the root of the
 *   search for elements
 */
module.exports = function ( documentRoot ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

	const $toolLink = $( '#t-checkuser-ip-auto-reveal span' );

	// Ensure the IP auto-reveal toollink has the correct message, even if the UI is cached
	getAutoRevealStatus().then( ( expiry ) => {
		if ( expiry ) {
			$toolLink.text( mw.message( 'checkuser-ip-auto-reveal-link-sidebar-on' ) );
		} else {
			$toolLink.text( mw.message( 'checkuser-ip-auto-reveal-link-sidebar' ) );
		}
	} );

	$( '.checkuser-ip-auto-reveal', documentRoot ).on(
		'click',
		() => {
			mw.loader.using( [ 'vue', '@wikimedia/codex' ] ).then( () => {
				getAutoRevealStatus().then( ( expiry ) => {
					$( 'body' ).append(
						$( '<div>' ).attr( { id: 'checkuser-ip-auto-reveal' } )
					);
					let App;
					if ( expiry ) {
						App = require( './components/IPAutoRevealOffDialog.vue' );
					} else {
						App = require( './components/IPAutoRevealOnDialog.vue' );
					}
					const Vue = require( 'vue' );
					Vue.createMwApp( App, {
						expiryTimestamp: Number( expiry ),
						toolLink: $toolLink
					} ).mount( '#checkuser-ip-auto-reveal' );
				} );
			} );
		} );
};
