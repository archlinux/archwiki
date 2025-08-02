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

			if ( !Object.prototype.hasOwnProperty.call( preferences, getAutoRevealStatusPreferenceName() ) ) {
				deferred.resolve( false );
				return;
			}

			const autoRevealPreference = preferences[ getAutoRevealStatusPreferenceName() ] || 0;
			const expiry = Number( autoRevealPreference );
			if ( expiry > ( Date.now() / 1000 ) ) {
				deferred.resolve( expiry );
			} else {
				// The expiry time has passed, so remove the row from the database table
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
	const nowInSeconds = Math.round( Date.now() / 1000 );
	const oneDayInSeconds = 86400;
	const absoluteExpiry = relativeExpiry ?
		nowInSeconds + relativeExpiry :
		undefined;

	if ( absoluteExpiry > nowInSeconds + oneDayInSeconds ) {
		return $.Deferred().reject().promise();
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
