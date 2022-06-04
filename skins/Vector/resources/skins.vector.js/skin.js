var collapsibleTabs = require( '../skins.vector.legacy.js/collapsibleTabs.js' ),
	vector = require( '../skins.vector.legacy.js/vector.js' ),
	languageButton = require( './languageButton.js' ),
	initSearchLoader = require( './searchLoader.js' ).initSearchLoader,
	dropdownMenus = require( './dropdownMenus.js' ),
	sidebar = require( './sidebar.js' );

/**
 * Wait for first paint before calling this function. That's its whole purpose.
 *
 * Some CSS animations and transitions are "disabled" by default as a workaround to this old Chrome
 * bug, https://bugs.chromium.org/p/chromium/issues/detail?id=332189, which otherwise causes them to
 * render in their terminal state on page load. By adding the `vector-animations-ready` class to the
 * `html` root element **after** first paint, the animation selectors suddenly match causing the
 * animations to become "enabled" when they will work properly. A similar pattern is used in Minerva
 * (see T234570#5779890, T246419).
 *
 * Example usage in Less:
 *
 * ```less
 * .foo {
 *     color: #f00;
 *     .transform( translateX( -100% ) );
 * }
 *
 * // This transition will be disabled initially for JavaScript users. It will never be enabled for
 * // no-JS users.
 * .vector-animations-ready .foo {
 *     .transition( transform 100ms ease-out; );
 * }
 * ```
 *
 * @param {Document} document
 * @return {void}
 */
function enableCssAnimations( document ) {
	document.documentElement.classList.add( 'vector-animations-ready' );
}

/**
 * @param {Window} window
 * @return {void}
 */
function main( window ) {
	enableCssAnimations( window.document );
	collapsibleTabs.init();
	sidebar.init( window );
	vector.init();
	initSearchLoader( document );
	languageButton();
	dropdownMenus();
}

/**
 * @param {Window} window
 * @return {void}
 */
function init( window ) {
	var now = mw.now();
	// This is the earliest time we can run JS for users (and bucket anonymous
	// users for A/B tests).
	// Where the browser supports it, for a 10% sample of users
	// we record a value to give us a sense of the expected delay in running A/B tests or
	// disabling JS features. This will inform us on various things including what to expect
	// with regards to delay while running A/B tests to anonymous users.
	// When EventLogging is not available this will reject.
	// This code can be removed by the end of the Desktop improvements project.
	// https://www.mediawiki.org/wiki/Desktop_improvements
	mw.loader.using( 'ext.eventLogging' ).then( function () {
		if (
			mw.eventLog &&
			mw.eventLog.eventInSample( 100 /* 1 in 100 */ ) &&
			window.performance &&
			window.performance.timing &&
			window.performance.timing.navigationStart
		) {
			mw.track( 'timing.Vector.ready', now - window.performance.timing.navigationStart ); // milliseconds
		}
	} );
}

init( window );

/**
 * Because stickyHeader.js clones the user menu, it must initialize before
 * dropdownMenus.js initializes in order for the sticky header's user menu to
 * bind the necessary checkboxHack event listeners. This is solved by using
 * mw.loader.using to ensure that the skins.vector.es6 module initializes first
 * followed by initializing this module. If the es6 module loading fails (which
 * can happen in browsers that don't support es6), continue to initialize this
 * module.
 */
function initAfterEs6Module() {
	mw.loader.using( 'skins.vector.es6' ).then( function () {
		// Loading of the 'skins.vector.es6' module has succeeded. Initialize the
		// `skins.vector.es6` module first.
		require( /** @type {string} */ ( 'skins.vector.es6' ) ).main();
		// Initialize this module second.
		main( window );
	}, function () {
		// Loading of the 'skins.vector.es6' has failed (e.g. this will fail in
		// browsers that don't support ES6) so only initialize this module.
		main( window );
	} );
}

if ( document.readyState === 'interactive' || document.readyState === 'complete' ) {
	initAfterEs6Module();
} else {
	// This is needed when document.readyState === 'loading'.
	document.addEventListener( 'DOMContentLoaded', function () {
		initAfterEs6Module();
	} );
}
