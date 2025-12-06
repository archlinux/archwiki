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

	const api = new mw.Api();
	return api.postWithToken( 'csrf', {
		action: 'globalpreferences',
		optionname: getAutoRevealStatusPreferenceName(),
		optionvalue: absoluteExpiry
	} );
}

module.exports = {
	getRevealedStatus: getRevealedStatus,
	setRevealedStatus: setRevealedStatus,
	getAutoRevealStatus: getAutoRevealStatus,
	setAutoRevealStatus: setAutoRevealStatus
};
