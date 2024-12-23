( function () {
	/** @class jQuery */

	/**
	 * Adds support to find parent elements using .closest with less-than selector syntax.
	 *
	 *     $.findWithParent( $div, "< html div < body" ); // find closest parent of $div "html", find child "div" of it, find closest parent "body" of that, return "body"
	 *     $( '#foo' ).findWithParent( '.bar < .baz' ); // find child ".bar" of "#foo", return closest parent ".baz" from there
	 *
	 * @method findWithParent
	 * @param {jQuery|HTMLElement|string} $context
	 * @param {string} selector
	 * @return {jQuery}
	 */
	function jQueryFindWithParent( $context, selector ) {
		$context = $( $context );
		selector = selector.trim();

		let matches;
		while ( selector && ( matches = selector.match( /(.*?(?:^|[>\s+~]))(<\s*[^>\s+~]+)(.*?)$/ ) ) ) {
			if ( matches[ 1 ].trim() ) {
				$context = $context.find( matches[ 1 ] );
			}
			if ( matches[ 2 ].trim() ) {
				$context = $context.closest( matches[ 2 ].slice( 1 ) );
			}
			selector = matches[ 3 ].trim();
		}

		if ( selector ) {
			$context = $context.find( selector );
		}

		return $context;
	}

	$.findWithParent = jQueryFindWithParent;

	/** @class jQuery.fn */
	/**
	 * @param {string} selector
	 * @return {jQuery}
	 * @see jQuery#findWithParent
	 */
	$.fn.findWithParent = function ( selector ) {
		const selectors = selector.split( ',' );
		let $elements = $();

		selectors.forEach( ( sel ) => {
			$elements = $elements.add( jQueryFindWithParent( this, sel ) );
		} );

		return $elements;
	};
}() );
