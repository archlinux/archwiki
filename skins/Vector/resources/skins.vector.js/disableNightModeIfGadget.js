const userPreferences = require( './userPreferences.js' );

/**
 * T365083 - Disable night mode if night mode gadget is enabled
 *
 * While our implementation of night mode is still in beta, we want to respect the existing gadget
 * and disable our version to avoid a double invert - that said, we will still provide a prompt for
 * the user to disable the gadget so they can try our night mode
 */

/**
 * Are any of the gadgets associated with the broader night mode gadget enabled?
 * Note: This is localized to the names of the gadget in our particular language
 *
 * @return {boolean}
 */
function isNightModeGadgetEnabled() {
	return mw.msg( 'vector-night-mode-gadget-names' ).split( '|' ).some( ( gadget ) => {
		const state = mw.loader.getState( `ext.gadget.${ gadget }` );

		// the state is null if it's not installed or we're on the preference page, otherwise it's
		// registered if the user doesn't have it turned on - all other states we consider enabled
		return state !== null && state !== 'registered';
	} );
}

/**
 * Manually mark the page we're on as excluded
 */
function disableNightModeForGadget() {
	document.documentElement.classList.remove( 'skin-theme-clientpref-night', 'skin-theme-clientpref-os' );

	document.documentElement.classList.add( 'skin-theme-clientpref--excluded' );
}

/**
 * Modify the link to disable the gadget so that, when clicked, it will disable the night mode
 * gadget rather than simply take you to the page
 * Note: The gadget names are similarly localized to the current language
 *
 * @param {Element} container an html element containing a link
 */
function alterDisableLink( container ) {
	const gadgetNames = mw.msg( 'vector-night-mode-gadget-names' );
	const link = container.querySelector( 'a' );

	// if we can't disable the gadget, keep the link functional
	// (if we can't find a link, not much we can do)
	if ( !gadgetNames || !link ) {
		return;
	}

	link.removeAttribute( 'title' );
	link.removeAttribute( 'href' );
	link.style.display = 'inline';

	link.addEventListener( 'click', () => {
		/** @type {Object.<string, number>} */
		const disableOptions = {};

		gadgetNames.split( '|' ).forEach( ( gadgetName ) => {
			disableOptions[ `gadget-${ gadgetName }` ] = 0;
		} );

		userPreferences.saveOptions( disableOptions ).then( () => {
			window.location.reload();
		} );
	} );
}

/**
 * Removes the link that solicits user feedback for dark mode.
 */
function removeBetaNotice() {
	const feedbackNoticeContainer = document.getElementById( 'skin-theme-beta-notice' );
	if ( feedbackNoticeContainer ) {
		feedbackNoticeContainer.remove();
	}
}

/**
 * Modify the default exclusion message to indicate that we've disabled night mode on the page due
 * to a conflicting gadget, providing a link to disable the gadget in favor of our night mode
 */
function alterExclusionMessage() {
	const noticeContainer = document.querySelector( '#skin-client-prefs-skin-theme .exclusion-notice' );
	// if there's no exclusion notice, nothing we can do
	if ( !noticeContainer ) {
		return;
	}

	mw.loader.using( 'mediawiki.jqueryMsg' ).then( () => {
		// remove existing message
		noticeContainer.textContent = '';

		mw.message( 'vector-night-mode-gadget-warning' ).parseDom().appendTo( noticeContainer );

		alterDisableLink( noticeContainer );
	} );
}

module.exports = {
	isNightModeGadgetEnabled,
	disableNightModeForGadget,
	alterDisableLink,
	alterExclusionMessage,
	removeBetaNotice
};
