const features = require( './features.js' );
const PINNED_HEADER_CLASS = 'vector-pinnable-header-pinned';
const UNPINNED_HEADER_CLASS = 'vector-pinnable-header-unpinned';
const popupNotification = require( './popupNotification.js' );
/** @type {MediaQueryList|null} */
let pinnableBreakpoint = null;
/** @type {(function(MediaQueryListEvent): void)|null} */
let breakpointHandler = null;

/**
 * Pinnable elements are UI elements (typically menus) that can be pinned to the
 * column layout (ColumnStart or ColumnEnd) for easier access, or unpinned
 * to be located in a dropdown elsewhere in the UI.
 *
 * Pinnable elements include:
 * - main menu
 * - page tools menu
 * - appearance menu
 * - table of contents
 *
 * Pin - The element is moved into the column layout and is always visible.
 * Unpin - The element is moved to a dropdown and is hidden until the dropdown is opened.
 *
 * This module handles the pinning and unpinning of these elements, including
 * moving the element in the DOM, toggling CSS classes and features, and handling
 * responsive behaviour at certain breakpoints.
 */

/**
 * @param {HTMLElement} header
 * @return {boolean} Returns true if the element is pinned and false otherwise.
 */
function isPinned( header ) {
	const featureName = /** @type {string} */ ( header.dataset.featureName );
	return features.isEnabled( featureName );
}

/**
 * Saves the persistent pinnable state in the element's dataset
 * so that it can be overridden at lower resolutions and the
 * reverted to at wider resolutions.
 *
 * This is not necessarily the elements current state, but it
 * seeks to represent the state of the saved user preference.
 *
 * @param {HTMLElement} header
 */
function savePinnedState( header ) {
	header.dataset.savedPinnedState = String( isPinned( header ) );
}

/**
 * Update feature classes on the body and pinnable element
 *
 * @param {HTMLElement} header pinnable element
 * @param {boolean} pinState the new pinnable element state (true for pinned, false for unpinned)
 * @param {pinState} saveState whether or not to save the state in client preferences
 */
function updatePinnableClasses( header, pinState, saveState = true ) {
	const featureName = /** @type {string} */ ( header.dataset.featureName );

	if ( pinState !== isPinned( header ) ) {
		features.toggleDocClasses( featureName, pinState );
		if ( saveState ) {
			features.save( featureName, pinState );
			// Set saved pinned state for narrow breakpoint behaviour.
			savePinnedState( header );
		}
	}

	// Toggle pinned class
	header.classList.toggle( PINNED_HEADER_CLASS, pinState );
	header.classList.toggle( UNPINNED_HEADER_CLASS, !pinState );
}

/**
 * Move pinnable element to the unpinned or pinned container
 *
 * @param {HTMLElement} header PinnableHeader element.
 * @param {boolean} pinState true to force pinned state, false for unpinned
 */
function movePinnableElement( header, pinState ) {
	const {
		pinnableElementId,
		pinnedContainerId,
		unpinnedContainerId
	} = header.dataset;

	if ( !pinnableElementId || !pinnedContainerId || !unpinnedContainerId ) {
		mw.log.warn( 'movePinnableElement: missing data-* attributes', header );
		return;
	}

	const pinnableElem = document.getElementById( pinnableElementId );
	const currContainer = pinnableElem && pinnableElem.parentElement;
	const newContainerId = pinState ? pinnedContainerId : unpinnedContainerId;

	// Avoid moving element if unnecessary
	if ( currContainer && currContainer.id !== newContainerId ) {
		const newContainer = document.getElementById( newContainerId );

		if ( !newContainer ) {
			mw.log.warn( 'movePinnableElement: destination container not found ', newContainerId );
			return;
		} else if ( !pinnableElem || !currContainer ) {
			mw.log.warn( 'movePinnableElement: elements not found' );
			return;
		}

		newContainer.insertAdjacentElement( 'beforeend', pinnableElem );
		// T336729 The width of the screen may change when the pinnableElement is pinned/unpinned
		// window.resize is a generic way to ensure changes can be handled by other elements
		window.dispatchEvent( new Event( 'resize' ) );
		popupNotification.hideAll();
	}
}

/**
 * Set pinnable header to specified state and move to relevant container
 *
 * @param {HTMLElement} header PinnableHeader element.
 * @param {boolean} pinState true to set pinned state, false for unpinned
 * @param {pinState} saveState whether or not to save the state in client preferences
 */
function updatePinnableState( header, pinState, saveState = true ) {
	updatePinnableClasses( header, pinState, saveState );
	movePinnableElement( header, pinState );
}

/**
 * Sets focus on the correct toggle button depending on the pinned state.
 * Also opens the dropdown containing the unpinned element.
 *
 * @param {HTMLElement} header PinnableHeader element.
 */
function setFocusAfterToggle( header ) {
	const { pinnableElementId } = header.dataset;
	const pinnableElement = document.getElementById( pinnableElementId || '' );

	if ( pinnableElement ) {
		let focusElement;
		if ( isPinned( header ) ) {
			focusElement = /** @type {HTMLElement|null} */ ( pinnableElement.querySelector( '.vector-pinnable-header-unpin-button' ) );
		} else {
			const dropdown = pinnableElement.closest( '.vector-dropdown' );
			focusElement = /** @type {HTMLInputElement|null} */ ( dropdown && dropdown.querySelector( '.vector-dropdown-checkbox' ) );
		}
		if ( focusElement ) {
			focusElement.focus();
		}
	}
}

/**
 * Create the indicators for the pinnable element to show its unpinned location
 *
 * @param {HTMLElement} header pinnable element
 */
function showUnpinnedIndicator( header ) {
	const { pinnableElementId, unpinnedContainerId } = header.dataset;
	const unpinnedContainer = document.getElementById( unpinnedContainerId || '' );
	const container = /** @type {HTMLElement|null} */ (
		unpinnedContainer && unpinnedContainer.closest( '.vector-dropdown' )
	);

	if ( !container || !pinnableElementId ) {
		mw.log.warn( 'showUnpinnedIndicator: unable to find container for indicator', unpinnedContainerId );
		return;
	}

	// Possible messages include:
	// * vector-page-tools-unpinned-popup
	// * vector-main-menu-unpinned-popup
	// * vector-toc-unpinned-popup
	// * vector-appearance-unpinned-popup
	const message = mw.msg( `${ pinnableElementId }-unpinned-popup` );
	popupNotification.add( container, message, pinnableElementId )
		.then( ( popupWidget ) => {
			if ( popupWidget ) {
				popupNotification.show( popupWidget );
			}
		} );
}

/**
 * Binds the pin/unpin buttons in a pinnableElement
 * to the click handler that toggles pin state.
 *
 * @param {HTMLElement} header
 */
function bindToggleButtons( header ) {
	const toggleButtons = header.querySelectorAll( '.vector-pinnable-header-toggle-button' );
	toggleButtons.forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const newPinState = !isPinned( header );
			updatePinnableState( header, newPinState );
			setFocusAfterToggle( header );
			// Show an indicator when unpinning
			if ( !newPinState ) {
				showUnpinnedIndicator( header );
			}
		} );
	} );
}

/**
 * Callback for matchMedia listener that overrides all pinnable header's stored state
 * at a certain breakpoint and forces it to unpin.
 * Usage of 'e.matches' assumes a `max-width` not `min-width` media query.
 *
 * @param {NodeListOf<HTMLElement>} headers
 * @param {MediaQueryList|MediaQueryListEvent} e
 */
function updatePinningAtBreakpoint( headers, e ) {
	headers.forEach( ( header ) => {
		const savedPinnedState = JSON.parse( header.dataset.savedPinnedState || 'false' );

		// If pinned, we want to override the state to be unpinned when below the breakpoint
		if ( savedPinnedState === true ) {
			updatePinnableState( header, !e.matches, false );
		}
	} );
}

/**
 * Binds pinnable breakpoint to allow automatic unpinning
 * of pinnable elements with pinnedContainerId and unpinnedContainerId defined
 *
 * @param {NodeListOf<HTMLElement>} headers
 */
function bindBreakpoint( headers ) {
	pinnableBreakpoint = window.matchMedia( '(max-width: 1119px)' );
	breakpointHandler = updatePinningAtBreakpoint.bind( null, headers );

	// Check the breakpoint in case an override is needed on pageload.
	updatePinningAtBreakpoint( headers, pinnableBreakpoint );

	// Add match media handler.
	if ( pinnableBreakpoint.addEventListener ) {
		pinnableBreakpoint.addEventListener( 'change', breakpointHandler );
	} else {
		// Before Safari 14, MediaQueryList is based on EventTarget,
		// so you must use addListener() and removeListener() to observe media query lists.
		pinnableBreakpoint.addListener( breakpointHandler );
	}
}

// T349924: Remove hasPinnedElements after one cycle of analyticsPinnedState() merge.
/**
 * Checks if at least one of the elements in the HTML document is pinned based on CSS class names.
 *
 * @method
 * @return {boolean} True if at least one pinned element is found, otherwise false.
 */
function hasPinnedElements() {
	const suffixesToCheck = [ 'pinned-clientpref-1', 'pinned-enabled' ];
	const htmlElement = document.documentElement;
	return Array.from( htmlElement.classList ).some(
		( className ) => suffixesToCheck.some( ( suffix ) => className.endsWith( suffix ) )
	);
}

/**
 * @stable for use in WikimediaEvents only.
 * Checks if at least one of the elements in the HTML document is pinned based on CSS class names.
 *
 * @method
 * @return {boolean} True if at least one pinned element is found, otherwise false.
 */
function analyticsPinnedState() {
	const htmlElement = document.documentElement;
	return htmlElement.classList.contains( 'vector-feature-main-menu-pinned-enabled' ) || htmlElement.classList.contains( 'vector-feature-page-tools-pinned-enabled' );
}

/**
 * A hook handler for ve.hideVectorColumns hook.
 *
 * Force-unpins appearance, tools, main menu, and ToC features,
 * without affecting user preferences.
 */
function hideVectorColumnsHandler() {
	const pinnableHeaders = /** @type {NodeListOf<HTMLElement>} */ ( document.querySelectorAll( '.vector-pinnable-header' ) );
	pinnableHeaders.forEach( ( header ) => {
		updatePinnableState( header, false, false );
		// Overriding the pinned state temporarily, so we hide the buttons to prevent users from changing the state
		header.classList.add( 'vector-pinnable-header-override' );
	} );
	// temporarily unbind the pinnable breakpoint so that none of the header states respond to screen-resizing
	if ( pinnableBreakpoint && breakpointHandler ) {
		if ( pinnableBreakpoint.removeEventListener ) {
			pinnableBreakpoint.removeEventListener( 'change', breakpointHandler );
		} else {
			pinnableBreakpoint.removeListener( breakpointHandler );
		}
		pinnableBreakpoint = null;
		breakpointHandler = null;
	}
}

/**
 * A hook handler for ve.restoreVectorColumns hook.
 *
 * Restores appearance, tools, and main menu features' pinned states
 * according to user preferences.
 */
function restoreVectorColumnsHandler() {
	const pinnableHeaders = /** @type {NodeListOf<HTMLElement>} */ ( document.querySelectorAll( '.vector-pinnable-header' ) );
	pinnableHeaders.forEach( ( header ) => {
		const savedPinnedState = JSON.parse( header.dataset.savedPinnedState || 'false' );
		updatePinnableState( header, savedPinnedState, false );
		// Removing the override class to restore pin/unpin button functionality
		header.classList.remove( 'vector-pinnable-header-override' );
	} );
	bindBreakpoint( pinnableHeaders );
}

function init() {
	const pinnableHeaders = /** @type {NodeListOf<HTMLElement>} */ ( document.querySelectorAll( '.vector-pinnable-header' ) );
	pinnableHeaders.forEach( ( header ) => {
		if ( header.dataset.featureName && header.dataset.pinnableElementId ) {
			bindToggleButtons( header );
			updatePinnableState( header, isPinned( header ) );
			// Set saved pinned state for narrow breakpoint behaviour.
			savePinnedState( header );
		}
	} );
	bindBreakpoint( pinnableHeaders );

	mw.hook( 've.hideVectorColumns' ).add( hideVectorColumnsHandler );
	mw.hook( 've.restoreVectorColumns' ).add( restoreVectorColumnsHandler );
}

module.exports = {
	init,
	hideVectorColumnsHandler,
	restoreVectorColumnsHandler,
	// T349924: Remove hasPinnedElements.
	hasPinnedElements,
	analyticsPinnedState,
	updatePinnableState,
	isPinned,
	PINNED_HEADER_CLASS,
	UNPINNED_HEADER_CLASS
};
