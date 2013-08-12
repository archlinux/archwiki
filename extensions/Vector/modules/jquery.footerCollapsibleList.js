( function( $ ) {
	// Small jQuery plugin to handle the toggle function & cookie for state
	// For collapsible items in the footer
	$.fn.footerCollapsibleList = function( config ) {
		if ( !( 'title' in config ) || !( 'name' in config ) ) {
			return;
		}

		return this.each( function () {
			var $container, $ul, $explanation, $icon;

			$container = $( this );
			$ul = $container.find( 'ul' );
			$explanation = $container.find( '.mw-templatesUsedExplanation, .mw-hiddenCategoriesExplanation' );

			$icon = $( '<span>' );
			$ul.before(
				$( '<a>' )
					.addClass( 'collapsible-list' )
					.text( config.title )
					.append( $icon )
					.on( 'click', function( e ) {
						// Modify state cookie.
						var state = ( $.cookie( config.name ) !== 'expanded' ) ? 'expanded' : 'collapsed';
						$.cookie( config.name, state );

						// Modify DOM.
						$ul.slideToggle();
						$icon.toggleClass( 'collapsed' );

						e.preventDefault();
					} )
			);

			$explanation.remove();

			// Check cookie and collapse.
			if( $.cookie( config.name ) === null || $.cookie( config.name ) === 'collapsed' ) {
				$ul.hide();
				$icon.addClass( 'collapsed' );
			}
		} );
	};
}( jQuery ) );
