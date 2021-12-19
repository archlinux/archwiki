var
	HEADER_SELECTOR = '.mw-header',
	SEARCH_TOGGLE_SELECTOR = '.search-toggle',
	SEARCH_BOX_ID = 'p-search',
	SEARCH_VISIBLE_CLASS = 'vector-header-search-toggled';

/**
 * Binds event handlers necessary for the searchBox to disappear when the user
 * clicks outside the searchBox.
 *
 * @param {HTMLElement} searchBox
 * @param {HTMLElement} header
 */
function bindSearchBoxHandler( searchBox, header ) {
	/**
	 * @param {Event} ev
	 * @ignore
	 */
	function clickHandler( ev ) {
		if (
			ev.target instanceof HTMLElement &&
			// Check if the click target was a suggestion link. WVUI clears the
			// suggestion elements from the DOM when a suggestion is clicked so we
			// can't test if the suggestion is a child of the searchBox.
			!$( ev.target ).closest( '.wvui-typeahead-suggestion' ).length &&
			!searchBox.contains( ev.target )
		) {
			// eslint-disable-next-line mediawiki/class-doc
			header.classList.remove( SEARCH_VISIBLE_CLASS );

			document.removeEventListener( 'click', clickHandler );
		}
	}

	document.addEventListener( 'click', clickHandler );
}

/**
 * Binds event handlers necessary for the searchBox to show when the toggle is
 * clicked.
 *
 * @param {HTMLElement} searchBox
 * @param {HTMLElement} header
 * @param {HTMLElement} searchToggle
 */
function bindToggleClickHandler( searchBox, header, searchToggle ) {
	/**
	 * @param {Event} ev
	 * @ignore
	 */
	function handler( ev ) {
		// The toggle is an anchor element. Prevent the browser from navigating away
		// from the page when clicked.
		ev.preventDefault();

		bindSearchBoxHandler( searchBox, header );

		// eslint-disable-next-line mediawiki/class-doc
		header.classList.add( SEARCH_VISIBLE_CLASS );

		// Defer focusing the input to another task in the event loop. At the time
		// of this writing, Safari 14.0.3 has trouble changing the visibility of the
		// element and focusing the input within the same task.
		setTimeout( function () {
			var searchInput = /** @type {HTMLInputElement|null} */ ( searchBox.querySelector( 'input[type="search"]' ) );

			if ( searchInput ) {
				searchInput.focus();
			}
		} );
	}

	searchToggle.addEventListener( 'click', handler );
}

module.exports = function initSearchToggle() {
	var
		header = /** @type {HTMLElement|null} */ ( document.querySelector( HEADER_SELECTOR ) ),
		searchBox = /** @type {HTMLElement|null} */ ( document.getElementById( SEARCH_BOX_ID ) ),
		searchToggle =
			/** @type {HTMLElement|null} */ ( document.querySelector( SEARCH_TOGGLE_SELECTOR ) );

	if ( !( searchBox && searchToggle && header ) ) {
		return;
	}

	bindToggleClickHandler( searchBox, header, searchToggle );
};
