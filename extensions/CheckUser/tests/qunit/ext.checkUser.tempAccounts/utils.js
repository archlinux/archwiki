'use strict';

/**
 * Return a temporary user link with the correct class and text.
 *
 * @param {string} username The temporary user's name
 */
function makeTempUserLink( username ) {
	return $( '<a>' )
		.addClass( 'mw-tempuserlink' )
		.text( username )
		.attr( 'data-mw-target', username );
}

/**
 * Waits until the specified selector to no longer match any elements in the QUnit test fixture.
 *
 * @param {string} selector The JQuery selector to check
 * @return {Promise}
 */
function waitUntilElementDisappears( selector ) {
	return waitUntilElementCount( selector, 0 );
}

/**
 * Waits until the specified selector appears in the QUnit test fixture.
 *
 * @param {string} selector The JQuery selector to check
 * @return {Promise}
 */
function waitUntilElementAppears( selector ) {
	return waitUntilElementCount( selector, 1 );
}

/**
 * Waits until the specified selector matches the specified count of elements in
 * the QUnit test fixture.
 *
 * @param {string} selector The JQuery selector to check
 * @param {number} count The number of elements to wait for
 * @return {Promise}
 */
function waitUntilElementCount( selector, count ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	return new Promise( ( resolve ) => {
		// Check every 10ms if the class matches any element in the QUnit test fixture.
		// If the class is no longer present, then resolve is called.
		// If this condition is not met ever, then QUnit will time the test out after 6s.
		function runCheck() {
			setTimeout( () => {
				if ( $( selector, $qunitFixture ).length === count ) {
					return resolve();
				}
				runCheck();
			}, 10 );
		}
		runCheck();
	} );
}

module.exports = {
	makeTempUserLink: makeTempUserLink,
	waitUntilElementDisappears: waitUntilElementDisappears,
	waitUntilElementAppears: waitUntilElementAppears,
	waitUntilElementCount: waitUntilElementCount
};
