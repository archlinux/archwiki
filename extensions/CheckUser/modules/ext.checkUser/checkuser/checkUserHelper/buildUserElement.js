/* jshint browser: true */
/* jshint -W097 */

'use strict';

/**
 * @param {string} userName
 * @param {{
 *          ip: {},
 *          ua: {},
 *          sorted: {ip: string[], ua: string[]},
 *          linkUserPage: boolean,
 *          classes: string
 *        }} userData
 * @return {HTMLElement}
 */
function buildUserElement( userName, userData ) {
	let userElement;

	// Only link the username to the user page if it was linked in the results.
	// No link can be used if the username is hidden.
	if ( !userData.linkUserPage ) {
		userElement = document.createElement( 'span' );
		userElement.innerHTML = userName;

		return userElement;
	}

	const bdiElement = document.createElement( 'bdi' );
	bdiElement.innerHTML = userName;

	userElement = document.createElement( 'a' );
	userElement.setAttribute(
		'href',
		mw.util.getUrl( 'Special:Contributions/' + userName )
	);
	userElement.appendChild( bdiElement );

	if ( userData.classes ) {
		userElement.setAttribute( 'class', userData.classes );

		const classes = userData.classes.split( ' ' );
		if ( classes.includes( 'mw-tempuserlink-expired' ) ) {
			userElement.appendChild( getTooltip() );
			userElement.setAttribute( 'aria-description', getTooltipMessage() );

			const wrapper = document.createElement( 'span' );
			wrapper.appendChild( userElement.cloneNode( true ) );

			userElement = wrapper;
		}
	}

	return userElement;
}

/**
 * @return HTMLSpanElement
 */
function getTooltip() {
	const tooltip = document.createElement( 'span' );

	tooltip.setAttribute( 'class',
		'cdx-tooltip mw-tempuserlink-expired--tooltip'
	);

	tooltip.innerHTML = getTooltipMessage();

	return tooltip;
}

/**
 * @return string
 */
function getTooltipMessage() {
	return mw.message( 'tempuser-expired-link-tooltip' ).text();
}

module.exports = buildUserElement;
