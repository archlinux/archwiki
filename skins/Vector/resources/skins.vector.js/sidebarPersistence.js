/**
 * JavaScript enhancement to persist the sidebar state for logged-in users.
 *
 */

/** @interface MwApi */

var /** @type {MwApi} */api,
	SIDEBAR_BUTTON_ID = 'mw-sidebar-button',
	debounce = require( /** @type {string} */ ( 'mediawiki.util' ) ).debounce,
	SIDEBAR_CHECKBOX_ID = 'mw-sidebar-checkbox',
	SIDEBAR_PREFERENCE_NAME = 'VectorSidebarVisible';

/**
 * Checks if persistent is enabled at current time.
 * When a user is using a browser with a screen resolution of < 1000 it is assumed
 * that it is preferred that the sidebar remains closed across page views, as otherwise
 * it gets in the way of reading. More context at T316191.
 *
 * @return {boolean}
 */
function isPersistentEnabled() {
	return window.innerWidth >= 1000;
}

/**
 * Execute a debounced API request to save the sidebar user preference.
 * The request is meant to fire 1000 milliseconds after the last click on
 * the sidebar button.
 *
 * @param {HTMLInputElement} checkbox
 * @param {number} timeout duration
 * @param {boolean} shouldTriggerResize whether a resize event is needed.
 * @return {any}
 */
function saveSidebarState( checkbox, timeout, shouldTriggerResize ) {
	return debounce( function () {
		api = api || new mw.Api();
		api.saveOption( SIDEBAR_PREFERENCE_NAME, checkbox.checked ? 1 : 0 );

		if ( !shouldTriggerResize ) {
			return;
		}
		// Trigger a resize event so other parts of the page can adapt:
		var event;
		if ( typeof Event === 'function' ) {
			event = new Event( 'resize' );
		} else {
			// IE11
			event = window.document.createEvent( 'UIEvents' );
			event.initUIEvent( 'resize', true, false, window, 0 );
		}
		window.dispatchEvent( event );
	}, timeout );
}

/**
 * Bind the event handler that saves the sidebar state to the click event
 * on the sidebar button.
 *
 * @param {HTMLElement|null} checkbox
 * @param {HTMLElement|null} button
 */
function bindSidebarClickEvent( checkbox, button ) {
	if ( checkbox instanceof HTMLInputElement && button ) {
		var handler = saveSidebarState( checkbox, 1000, true );
		checkbox.addEventListener( 'input', function () {
			if ( isPersistentEnabled() ) {
				handler();
			}
		} );
	}
}

var /** @type {boolean} */ wasCollapsedDuringResize = false;

/**
 * Collapses the sidebar if screen resolution too small.
 *
 * @param {HTMLInputElement} checkbox
 */
function collapseSidebar( checkbox ) {
	if ( checkbox.checked ) {
		wasCollapsedDuringResize = true;
		checkbox.checked = false;
		saveSidebarState( checkbox, 0, false )();
	}
}

/**
 * Expands the sidebar when the window is resized if it was previously collapsed.
 *
 * @param {HTMLInputElement} checkbox
 */
function expandSidebar( checkbox ) {
	if ( wasCollapsedDuringResize && !checkbox.checked ) {
		wasCollapsedDuringResize = false;
		checkbox.checked = true;
		saveSidebarState( checkbox, 0, false )();
	}
}

function init() {
	var checkbox = /** @type {HTMLInputElement|null} */ (
			window.document.getElementById( SIDEBAR_CHECKBOX_ID )
		),
		button = window.document.getElementById( SIDEBAR_BUTTON_ID );

	if ( mw.config.get( 'wgUserName' ) && !mw.config.get( 'wgVectorDisableSidebarPersistence' ) ) {
		bindSidebarClickEvent( checkbox, button );
	}

	// If the user has resized their window, an open sidebar will be taking up lots of space
	// so we should disable it.
	// When this happens the user must expand it again manually, to avoid conflicts with multiple
	// open windows (for example when an editor is viewing 2 articles side by side).
	if ( checkbox ) {
		var mediaQuery = window.matchMedia( '(max-width: 999px)' );
		var onMediaQueryChangeCollapse = function ( /** @type {MediaQueryListEvent} */ event ) {
			if ( event.matches ) {
				// @ts-ignore we checked it already.
				collapseSidebar( checkbox );
			} else {
				// @ts-ignore we checked it already.
				expandSidebar( checkbox );
			}
		};
		if ( mediaQuery.matches ) {
			collapseSidebar( checkbox );
		}
		if ( mediaQuery.addEventListener ) {
			mediaQuery.addEventListener( 'change', onMediaQueryChangeCollapse );
		} else {
			//  Before Safari 14, MediaQueryList is based on EventTarget,
			// so you must use addListener() and removeListener() to observe media query lists.
			mediaQuery.addListener( onMediaQueryChangeCollapse );
		}
	}
}

module.exports = {
	init: init
};
