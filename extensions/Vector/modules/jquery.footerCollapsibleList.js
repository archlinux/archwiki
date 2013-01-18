( function( $ ) {
	// Small jQuery plugin to handle the toggle function & cookie for state
	// For collapsible items in the footer
	$.fn.footerCollapsibleList = function( config ) {
		if (
			! ( 'title' in config ) ||
			! ( 'name' in config )
		) {
			return;
		}
		return this.each( function () {
			// Setup
			$( this )
				.parent()
					.prepend(
						$( '<a>' )
							.addClass( 'collapsible-list' )
							.text( config.title )
							.on( 'click', function( e ) {
								e.preventDefault();
								// Modify state cookie.
								var state = ( $.cookie( config.name ) !== 'expanded' ) ?
									'expanded' : 'collapsed';
								$.cookie( config.name, state );
								// Modify DOM.
								$( this ).next().toggle();
								$( this ).find( 'span' ).toggleClass( 'collapsed' );
							} )
							.append( $( '<span>' ) )
					)
					.end()
				.prev()
					.remove();
				// Check cookie and collapse.
				if(
					$.cookie( config.name ) === null ||
					$.cookie( config.name ) === 'collapsed'
				) {
					$( this )
						.slideUp()
						.prev()
							.find( 'span' ).addClass( 'collapsed' );
				}
		} );
	};
}( jQuery ) );
