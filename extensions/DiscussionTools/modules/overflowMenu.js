/**
 * Build an overflow menu button and items for display adjacent to heading and comment thread items.
 *
 * @param {jQuery} $container
 * @param {ThreadItemSet} pageThreads
 */
function init( $container, pageThreads ) {
	mw.loader.using( [ 'oojs-ui-widgets', 'oojs-ui.styles.icons-editing-core' ] ).then( () => {
		$container.find( '.ext-discussiontools-init-section-overflowMenuButton' ).each( ( i, button ) => {
			// Comment ellipsis
			let $threadMarker = $( button ).closest( '[data-mw-thread-id]' );
			if ( !$threadMarker.length ) {
				// Heading ellipsis
				$threadMarker = $( button ).closest( '.ext-discussiontools-init-section' ).find( '[data-mw-thread-id]' );
			}
			const threadItem = pageThreads.findCommentById( $threadMarker.data( 'mw-thread-id' ) );

			const buttonMenu = OO.ui.infuse( button, {
				$overlay: true,
				menu: {
					classes: [ 'ext-discussiontools-init-section-overflowMenu' ],
					horizontalPosition: threadItem.type === 'heading' ? 'end' : 'start'
				}
			} );

			mw.loader.using( buttonMenu.getData().resourceLoaderModules || [] ).then( () => {
				const itemConfigs = buttonMenu.getData().itemConfigs;
				if ( !itemConfigs ) {
					// We should never have missing itemConfigs, but if this happens, hide the empty menu
					buttonMenu.toggle( false );
					return;
				}
				const overflowMenuItemWidgets = itemConfigs.map( ( itemConfig ) => new OO.ui.MenuOptionWidget( itemConfig ) );
				buttonMenu.getMenu().addItems( overflowMenuItemWidgets );
				buttonMenu.getMenu().items.forEach( ( menuItem ) => {
					mw.hook( 'discussionToolsOverflowMenuOnAddItem' ).fire( menuItem.getData().id, menuItem, threadItem );
				} );
			} );

			buttonMenu.getMenu().on( 'choose', ( menuItem ) => {
				mw.hook( 'discussionToolsOverflowMenuOnChoose' ).fire( menuItem.getData().id, menuItem, threadItem );
			} );
		} );

		$container.find( '.ext-discussiontools-init-section-bar' ).on( 'click', ( e ) => {
			// Don't toggle section when clicking on bar
			e.stopPropagation();
		} );
	} );
}

module.exports = {
	init: init
};
