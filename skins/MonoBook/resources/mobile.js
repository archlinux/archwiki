( function () {
	$( function () {
		var mobileCutoffWidth = 550,
			// Track if DOM has been set up for mobile fanciness yet
			monobookMobileElements = false,
			// Toggles and targets for popouts
			toggles = {
				'#sidebar-toggle': '#sidebar-mobilejs',
				'#p-personal-toggle': '#p-personal',
				'#ca-more a': '#p-cactions',
				'#ca-languages a': '#p-lang',
				'#ca-tools a': '#p-tb'
			};

		// Close menus
		function closeMenus() {
			$( '.mobile-menu-active' ).removeClass( 'mobile-menu-active' );
			$( '.menus-cover' ).removeClass( 'visible' );
		}

		// Set up DOM for mobile fanciness
		// We don't automatically do this because MonoBook; most users will be on desktop
		function setupMonoBookMobile() {
			if ( !monobookMobileElements && $( window ).width() <= mobileCutoffWidth ) {
				// Duplicate nav
				$( '#column-one' ).append(
					$( '#sidebar' ).clone().find( '*' ).addBack().each( function () {
						if ( this.id ) {
							this.id = this.id + '-mobilejs';
						}
					} ).end().end()
				);
				// Thing to fade out the content while menus are active
				$( '#column-one' ).append( $( '<div id="menus-cover-background" class="menus-cover">' ) );
				$( '#column-one' ).append( $( '<div id="menus-cover" class="menus-cover">' ) );

				// Add extra cactions tabs - edit, editsource, contributions
				// Wrap in function to keep jenkins from whining
				$( function () {
					var newTabs = [
						'ca-edit',
						// 'ca-ve-edit', // TODO when VE is more usable to begin with here
						// 'ca-watch', 'ca-unwatch', // Maybe?
						't-contributions'
					];
					$.each( newTabs, function ( i, item ) {
						var a = $( '#' + item + ' a' );

						if ( a.length ) {
							mw.util.addPortletLink(
								'p-cactions-mobile',
								a.attr( 'href' ),
								a.text(),
								a.parent().attr( 'id' ) + '-mobile',
								a.attr( 'tooltip' ),
								a.attr( 'accesskey' ),
								'#ca-more'
							);
						}
					} );
				} );

				// Add close buttons
				$.each( toggles, function ( toggle, target ) {
					$( target ).append( $( '<div class="mobile-close-button">' ) );
				} );

				// Open menus
				$.each( toggles, function ( toggle, target ) {
					$( toggle ).on( 'click', function () {
						if ( $( window ).width() <= mobileCutoffWidth ) {
							$( target ).addClass( 'mobile-menu-active' );
							$( '.menus-cover' ).addClass( 'visible' );
						}
						// Don't still link to # targets
						return false;
					} );
				} );

				$( '.mobile-close-button, .menus-cover' ).click( closeMenus );
				// TODO: tap events on same (if not already included in 'click') - also close
				// TODO: appropriate swipe event(s) - also close

				monobookMobileElements = true;
			}
		}

		$( window ).resize( setupMonoBookMobile );
		setupMonoBookMobile();
	} );
}() );
