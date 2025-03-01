/*
 * HTMLForm enhancements:
 * Infuse some OOUI HTMLForm fields (those which benefit from always being infused).
 */
mw.hook( 'htmlform.enhance' ).add( ( $root ) => {
	let $oouiNodes = $root.find( '.mw-htmlform-autoinfuse' );

	$oouiNodes = $oouiNodes.filter( function () {
		return !$( this ).closest( '.mw-htmlform-autoinfuse-lazy' ).length;
	} );

	if ( $oouiNodes.length ) {
		// The modules are preloaded (added server-side in HTMLFormField, and the individual fields
		// which need extra ones), but this module doesn't depend on them. Wait until they're loaded.
		const modules = [ 'mediawiki.htmlform.ooui' ];
		$oouiNodes.each( function () {
			const data = $( this ).data( 'mw-modules' );
			if ( data ) {
				// We can trust this value, 'data-mw-*' attributes are banned from user content in Sanitizer
				const extraModules = data.split( ',' );
				modules.push( ...extraModules );
			}
		} );
		mw.loader.using( modules ).done( () => {
			$oouiNodes.each( function () {
				OO.ui.infuse( this );
			} );
		} );
	}
} );
