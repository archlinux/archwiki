var newTopicButton, $readAsWikiPage, ledeSectionDialog;
var viewportScrollContainer = null;
var wasKeyboardOpen = null;
var initialClientHeight = null;
// Copied from ve.init.Platform.static.isIos
var isIos = /ipad|iphone|ipod/i.test( navigator.userAgent );

$( document.body ).toggleClass( 'ext-discussiontools-init-ios', isIos );

function onViewportChange() {
	var isKeyboardOpen;

	if ( isIos ) {
		isKeyboardOpen = visualViewport.height < viewportScrollContainer.clientHeight;
	} else {
		// TODO: Support orientation changes?
		isKeyboardOpen = viewportScrollContainer.clientHeight < initialClientHeight;
	}

	if ( isKeyboardOpen !== wasKeyboardOpen ) {
		$( document.body ).toggleClass( 'ext-discussiontools-init-virtual-keyboard-open', isKeyboardOpen );
	}

	wasKeyboardOpen = isKeyboardOpen;
}

function init( $container, pageThreads ) {
	// For compatibility with MobileWebUIActionsTracking logging (T295490)
	$container.find( '.section-heading' ).attr( 'data-event-name', 'talkpage.section' );

	// Keyboard body classes
	if ( !viewportScrollContainer && window.visualViewport ) {
		viewportScrollContainer = OO.ui.Element.static.getClosestScrollableContainer( document.body );
		initialClientHeight = viewportScrollContainer.clientHeight;
		var onViewportChangeThrottled = OO.ui.throttle( onViewportChange, 100 );
		$( visualViewport ).on( 'resize', onViewportChangeThrottled );
	}

	// Mobile overflow menu
	mw.loader.using( [ 'oojs-ui-widgets', 'oojs-ui.styles.icons-editing-core' ] ).then( function () {
		// TODO: Replace with .ext-discussiontools-init-section-overflowMenuButton
		//  after parser cache is updated
		$container.find( '.ext-discussiontools-init-section-ellipsisButton' ).each( function () {
			// Comment ellipsis
			var $threadMarker = $( this ).closest( '[data-mw-thread-id]' );
			if ( !$threadMarker.length ) {
				// Heading ellipsis
				$threadMarker = $( this ).closest( '.ext-discussiontools-init-section' ).find( '[data-mw-thread-id]' );
			}
			var threadItem = pageThreads.findCommentById( $threadMarker.data( 'mw-thread-id' ) );

			var buttonMenu = OO.ui.infuse( this, {
				$overlay: true,
				menu: {
					classes: [ 'ext-discussiontools-init-section-overflowMenu' ],
					horizontalPosition: threadItem.type === 'heading' ? 'end' : 'start'
				}
			} );

			mw.loader.using( buttonMenu.getData().resourceLoaderModules || [] ).then( function () {
				var itemConfigs = buttonMenu.getData().itemConfigs;
				if ( !itemConfigs ) {
					// We should never have missing itemConfigs, but if this happens, hide the empty menu
					buttonMenu.toggle( false );
					return;
				}
				var overflowMenuItemWidgets = itemConfigs.map( function ( itemConfig ) {
					return new OO.ui.MenuOptionWidget( itemConfig );
				} );
				buttonMenu.getMenu().addItems( overflowMenuItemWidgets );
				buttonMenu.getMenu().items.forEach( function ( menuItem ) {
					mw.hook( 'discussionToolsOverflowMenuOnAddItem' ).fire( menuItem.getData().id, menuItem, threadItem );
				} );
			} );

			buttonMenu.getMenu().on( 'choose', function ( menuItem ) {
				mw.hook( 'discussionToolsOverflowMenuOnChoose' ).fire( menuItem.getData().id, menuItem, threadItem );
			} );
		} );

		$container.find( '.ext-discussiontools-init-section-bar' ).on( 'click', function ( e ) {
			// Don't toggle section when clicking on bar
			e.stopPropagation();
		} );
	} );

	var $ledeContent = $container.find( '.mf-section-0' ).children( ':not( .ext-discussiontools-emptystate )' )
		// On non-existent pages MobileFrontend wrapping isn't there
		.add( $container.find( '.mw-talkpageheader' ) );
	var $ledeButton = $container.find( '.ext-discussiontools-init-lede-button' );
	if ( $ledeButton.length ) {
		var windowManager = OO.ui.getWindowManager();
		if ( !ledeSectionDialog ) {
			var LedeSectionDialog = require( './LedeSectionDialog.js' );
			ledeSectionDialog = new LedeSectionDialog();
			windowManager.addWindows( [ ledeSectionDialog ] );
		}

		// Lede section popup
		OO.ui.infuse( $ledeButton ).on( 'click', function () {
			mw.loader.using( 'oojs-ui-windows' ).then( function () {
				windowManager.openWindow( 'ledeSection', { $content: $ledeContent } );
				mw.track( 'webuiactions_log.click', 'lede-button' );
			} );
		} );
		mw.track( 'webuiactions_log.show', 'lede-button' );
	}

	// eslint-disable-next-line no-jquery/no-global-selector
	var $newTopicWrapper = $( '.ext-discussiontools-init-new-topic' );

	if (
		!newTopicButton &&
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.ext-discussiontools-init-new-topic-button' ).length
	) {
		// eslint-disable-next-line no-jquery/no-global-selector
		newTopicButton = OO.ui.infuse( $( '.ext-discussiontools-init-new-topic-button' ) );
		// For compatibility with MobileWebUIActionsTracking logging (T295490)
		newTopicButton.$element.attr( 'data-event-name', 'talkpage.add-topic' );
		var $scrollContainer = $( OO.ui.Element.static.getClosestScrollableContainer( document.body ) );
		var $scrollListener = $scrollContainer.is( 'html, body' ) ? $( OO.ui.Element.static.getWindow( $scrollContainer[ 0 ] ) ) : $scrollContainer;
		var lastScrollTop = $scrollContainer.scrollTop();
		var wasScrollDown = null;
		var $body = $( document.body );
		// This block of code is only run once, so we don't need to remove this listener ever
		$scrollListener[ 0 ].addEventListener( 'scroll', OO.ui.throttle( function () {
			// Round negative values up to 0 to ignore iOS scroll bouncing (T323400)
			var scrollTop = Math.max( $scrollContainer.scrollTop(), 0 );
			var isScrollDown = scrollTop > lastScrollTop;
			if ( isScrollDown !== wasScrollDown ) {
				if ( !isScrollDown ) {
					$newTopicWrapper.css( 'transition', 'none' );
				}
				$body.removeClass( [ 'ext-discussiontools-init-new-topic-closed', 'ext-discussiontools-init-new-topic-opened' ] );
				requestAnimationFrame( function () {
					$newTopicWrapper.css( 'transition', '' );
					$body.addClass( isScrollDown ? 'ext-discussiontools-init-new-topic-close' : 'ext-discussiontools-init-new-topic-open' );
					setTimeout( function () {
						$body.removeClass( [ 'ext-discussiontools-init-new-topic-close', 'ext-discussiontools-init-new-topic-open' ] );
						$body.addClass( isScrollDown ? 'ext-discussiontools-init-new-topic-closed' : 'ext-discussiontools-init-new-topic-opened' );
					}, 250 );
				} );
			}

			var observer = new IntersectionObserver(
				function ( entries ) {
					$newTopicWrapper.toggleClass( 'ext-discussiontools-init-new-topic-pinned', entries[ 0 ].intersectionRatio === 1 );
				},
				{ threshold: [ 1 ] }
			);

			observer.observe( $newTopicWrapper[ 0 ] );

			lastScrollTop = scrollTop;
			wasScrollDown = isScrollDown;
		}, 200 ), { passive: true } );
	}
	if ( !$readAsWikiPage ) {
		// Read as wiki page button, copied from old MobileFrontend/Minerva feature (removed in T319145)
		$readAsWikiPage = $( '<button>' )
			.addClass( 'ext-discussiontools-init-readAsWikiPage' )
			.attr( 'data-event-name', 'talkpage.readAsWiki' )
			.text( mw.message( 'minerva-talk-full-page' ).text() )
			.on( 'click', function () {
				$( document.body ).removeClass( 'ext-discussiontools-visualenhancements-enabled ext-discussiontools-replytool-enabled' );
			} );
	}

	/* eslint-disable no-jquery/no-global-selector */
	if ( newTopicButton ) {
		$newTopicWrapper.after( $readAsWikiPage );
	} else {
		$( '#mw-content-text' ).append( $readAsWikiPage );
	}

	// Tweak to prevent our footer buttons from overlapping Minerva skin elements (T328452).
	// TODO: It would be more elegant to do this in just CSS somehow.
	// BEWARE: I have wasted 4 hours here trying to make that happen. The elements are not nested in a
	// helpful way, and moving them around tends to break the stickiness of the "Add topic" button.
	if (
		$( '.catlinks' ).filter( '[data-mw="interface"]' ).length ||
		$( '#page-secondary-actions' ).children().length ||
		$( '.return-link' ).length
	) {
		$readAsWikiPage.addClass( 'ext-discussiontools-init-button-notFlush' );
		$newTopicWrapper.addClass( 'ext-discussiontools-init-button-notFlush' );
	}
	/* eslint-enable no-jquery/no-global-selector */
}

// Handler for "edit" link in overflow menu, only setup once as the hook is global
mw.hook( 'discussionToolsOverflowMenuOnChoose' ).add( function ( id, menuItem, threadItem ) {
	if ( id === 'edit' ) {
		// Click the hidden section-edit link
		$( threadItem.getNativeRange().commonAncestorContainer )
			.closest( '.ext-discussiontools-init-section' ).find( '.mw-editsection > a' ).trigger( 'click' );
	}
} );

/**
 * Close the lede section dialog if it is open.
 *
 * @return {jQuery.Promise} Promise resolved when the dialog is closed (or if it wasn't open)
 */
function closeLedeSectionDialog() {
	if ( ledeSectionDialog && ledeSectionDialog.isOpened() ) {
		return ledeSectionDialog.close().closed;
	}
	return $.Deferred().resolve();
}

module.exports = {
	init: init,
	closeLedeSectionDialog: closeLedeSectionDialog
};
