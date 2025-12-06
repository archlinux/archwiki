( function () {
	mw.hook( 'wikipage.content' ).add( () => {
		const inputBoxForms = document.querySelectorAll( '.inputbox-searchengine-not-set' );

		// Replace form action with the user's preference.
		const userPref = mw.user.options.get( 'search-special-page' );
		const searchPages = mw.config.get( 'SpecialSearchPages' );
		if ( searchPages[ userPref ] !== undefined ) {
			const searchUrl = mw.util.getUrl( searchPages[ userPref ] );
			for ( const inputboxForm of inputBoxForms ) {
				inputboxForm.action = searchUrl;
			}
		}

		// Change the 'search' parameter on submit, if searchfilter is present.
		// This avoids an HTTP redirect that happens as a no-JS fallback.
		for ( const inputboxForm of inputBoxForms ) {
			const searchFilter = inputboxForm.querySelector( 'input[name="searchfilter"]' );
			if ( searchFilter ) {
				inputboxForm.addEventListener( 'submit', () => {
					const search = inputboxForm.querySelector( 'input[name="search"]' );
					if ( search ) {
						// This matches what's done in InputBoxHooks::onSpecialPageBeforeExecute().
						search.value = search.value.trim() + ' ' + searchFilter.value.trim();
						// Remove the searchfilter input, so it doesn't trigger the redirect.
						searchFilter.remove();
					}
				} );
			}
		}
	} );
}() );
