/**
 * Gets the revealed status key for a user.
 *
 * @param {string} user The username of the temporary account.
 * @return {string}
 */
function getRevealedStatusKey( user ) {
	return 'mw-checkuser-temp-' + user;
}

/**
 * Gets the revealed status of a user.
 *
 * @param {string} user The username of the temporary account to check.
 * @return {null|true} The revealed status of the user (null if not revealed, true if revealed).
 */
function getRevealedStatus( user ) {
	return mw.storage.get( getRevealedStatusKey( user ) );
}

/**
 * Update the revealed status of a user to indicate that the user has been revealed.
 *
 * @param {string} user The username of the temporary account that has had its IPs revealed.
 */
function setRevealedStatus( user ) {
	if ( !getRevealedStatus( getRevealedStatusKey( user ) ) ) {
		mw.storage.set(
			getRevealedStatusKey( user ),
			true,
			mw.config.get( 'wgCheckUserTemporaryAccountMaxAge' )
		);
	}
}

/**
 * Get the name of the auto-reveal status global preference.
 *
 * @return {string}
 */
function getAutoRevealStatusPreferenceName() {
	return 'checkuser-temporary-account-enable-auto-reveal';
}

/**
 * Check whether the expiry time for auto-reveal mode is valid. A valid expiry is in the future
 * and less than the maximum allowed expiry.
 *
 * @param {number} expiry
 * @return {boolean}
 */
function isExpiryValid( expiry ) {
	const nowInSeconds = Date.now() / 1000;
	const maxExpiry = mw.config.get( 'wgCheckUserAutoRevealMaximumExpiry' );
	return ( expiry > nowInSeconds ) && ( expiry <= ( nowInSeconds + maxExpiry ) );
}

/**
 * Get the auto-reveal status from the global preference.
 *
 * @return {Promise}
 */
function getAutoRevealStatus() {
	const deferred = $.Deferred();
	if ( mw.config.get( 'wgCheckUserTemporaryAccountAutoRevealAllowed' ) ) {
		const api = new mw.Api();
		api.get( {
			action: 'query',
			meta: 'globalpreferences',
			gprprop: 'preferences'
		} ).then( ( response ) => {
			let preferences;
			try {
				preferences = response.query.globalpreferences.preferences;
			} catch ( e ) {
				preferences = {};
			}

			if ( !Object.prototype.hasOwnProperty.call(
				preferences,
				getAutoRevealStatusPreferenceName()
			) ) {
				deferred.resolve( false );
				return;
			}

			const autoRevealPreference = preferences[ getAutoRevealStatusPreferenceName() ] || 0;
			const expiry = Number( autoRevealPreference );
			if ( isExpiryValid( expiry ) ) {
				deferred.resolve( expiry );
			} else {
				setAutoRevealStatus().then(
					() => deferred.resolve( false ),
					() => deferred.resolve( false )
				);
			}
		} ).catch( () => {
			deferred.resolve( false );
		} );
	} else {
		deferred.resolve( false );
	}
	return deferred.promise();
}

/**
 * Update the auto-reveal status of a user to switch on, switch off, or extend expiry.
 *
 * The value stored is the Unix timestamp of the expiry, in seconds. Auto-reveal is only supported
 * if the GlobalPreferences extension is available, because the expiry is saved as a global
 * preference so that the mode can be remembered across sites.
 *
 * @param {number|undefined} relativeExpiry Number of seconds the "on" mode should be enabled,
 *  or no argument to disable the mode.
 * @return {Promise}
 */
function setAutoRevealStatus( relativeExpiry ) {
	// Err on the low side to avoid going over the maximum allowed expiry by a fraction of a second
	const nowInSeconds = Math.floor( Date.now() / 1000 );
	const absoluteExpiry = relativeExpiry ?
		nowInSeconds + relativeExpiry :
		undefined;

	if ( absoluteExpiry && !isExpiryValid( absoluteExpiry ) ) {
		return $.Deferred().reject( 'Expiry is invalid' ).promise();
	}

	const preferenceName = getAutoRevealStatusPreferenceName();

	const api = new mw.Api();
	return api.postWithToken( 'csrf', {
		action: 'globalpreferences',
		optionname: preferenceName,
		optionvalue: absoluteExpiry
	} ).then(
		() => {
			// Update mw.user.options to avoid needing another API call to find the expiry
			mw.user.options.set(
				preferenceName,
				absoluteExpiry ? String( absoluteExpiry ) : null
			);
		}
	);
}

/**
 * Extracts a username from a URL address to their user page or contributions page.
 *
 * @param {string} urlString The URL to extract the username from.
 * @return {string|undefined} The extracted username, or undefined if the URL is invalid or
 *   does not point to a user page or contributions page.
 */
function getUserNameFromUrl( urlString ) {
	const url = new URL( urlString, location.href );
	if ( url.hostname !== location.hostname ) {
		return undefined;
	}

	let pageTitle = '';
	const indexPath = mw.config.get( 'wgScript' );
	if ( url.pathname === indexPath ) {
		// URL of the form /w/index.php?title=User:Example
		// searchParams handles decoding
		pageTitle = url.searchParams.get( 'title' );
	} else {
		// URL of the form /wiki/User:Example
		const articlePath = mw.config.get( 'wgArticlePath' ).replace( /\$1$/, '' );
		if ( !url.pathname.startsWith( articlePath ) ) {
			return undefined;
		}
		pageTitle = decodeURIComponent( url.pathname.slice( articlePath.length ) );
	}
	const title = mw.Title.newFromText( pageTitle );
	if ( title === null ) {
		return undefined;
	}

	const nsUser = mw.config.get( 'wgNamespaceIds' ).user;
	const nsSpecial = mw.config.get( 'wgNamespaceIds' ).special;

	if ( title.getNamespaceId() === nsUser ) {
		return title.getMainText();
	}
	if ( title.getNamespaceId() === nsSpecial ) {
		// Ensure it's a contributions page
		const mainText = title.getMainText();
		const contribsPrefix = mw.config.get( 'wgCheckUserContribsPageLocalName' ) + '/';
		if ( mainText.startsWith( contribsPrefix ) ) {
			return mainText.slice( contribsPrefix.length );
		}
	}
	return undefined;
}

module.exports = {
	getRevealedStatus: getRevealedStatus,
	setRevealedStatus: setRevealedStatus,
	getAutoRevealStatus: getAutoRevealStatus,
	setAutoRevealStatus: setAutoRevealStatus,
	getUserNameFromUrl: getUserNameFromUrl
};
