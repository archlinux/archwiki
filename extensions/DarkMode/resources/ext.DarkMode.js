/**
 * Some code adapted from the enwiki gadget https://w.wiki/5Ktj
 */
$( () => {
	const $darkModeLink = $( '.ext-darkmode-link' );

	$darkModeLink.on( 'click', ( e ) => {
		e.preventDefault();

		// NOTE: this must be on <html> element because the CSS filter creates a new stacking context.
		// See comments in Hooks::onBeforePageDisplay() for more information.
		const darkMode = document.documentElement.classList.toggle( 'client-darkmode' );

		// Update the icon.
		if ( darkMode ) {
			$darkModeLink.find( '.mw-ui-icon-moon' )
				.removeClass( 'mw-ui-icon-moon' )
				.addClass( 'mw-ui-icon-bright' );
		} else {
			$darkModeLink.find( '.mw-ui-icon-bright' )
				.removeClass( 'mw-ui-icon-bright' )
				.addClass( 'mw-ui-icon-moon' );
		}

		// Use different CSS selectors for the dark mode link based on the skin.
		// eslint-disable-next-line no-restricted-syntax
		const labelSelector = [ 'vector', 'vector-2022', 'minerva' ].includes( mw.config.get( 'skin' ) ) ?
			'span:not(.mw-ui-icon)' :
			'a';

		// Update the link text.
		$darkModeLink.find( labelSelector )
			.text( mw.msg( darkMode ? 'darkmode-default-link' : 'darkmode-link' ) );
		new mw.Api().saveOption( 'darkmode', darkMode ? '1' : '0' );

		// Update the mobile theme-color
		$( 'meta[name="theme-color"]' ).attr( 'content', darkMode ? '#000000' : '#eaecf0' );
	} );
} );
