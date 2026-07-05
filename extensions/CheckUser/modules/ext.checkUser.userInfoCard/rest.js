/**
 * Gets UserInfoCard data for a given username
 *
 * @param {string} username
 * @param {boolean} [retryOnTokenMismatch=true]
 * @return {Promise<{caseId: number, status: string, reason: string, formattedReason: string}>}
 */
function getUserInfo( username, retryOnTokenMismatch ) {
	const restApi = new mw.Rest();
	const api = new mw.Api();
	const deferred = $.Deferred();

	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}

	// T404682
	const language = mw.config.get( 'wgUserLanguage' );

	api.getToken( 'csrf' ).then( ( token ) => {
		restApi.post(
			'/checkuser/v0/userinfo?uselang=' + language,
			{
				token: token,
				username: username
			}
		).then(
			( data ) => {
				deferred.resolve( data );
			},
			( err, errObject ) => {
				if ( retryOnTokenMismatch && isBadTokenError( errObject ) ) {
					// The CSRF token has expired. Retry the POST with a new token.
					api.badToken( 'csrf' );
					getUserInfo( username, false ).then(
						( data ) => {
							deferred.resolve( data );
						},
						( secondRequestErr, secondRequestErrObject ) => {
							deferred.reject( secondRequestErr, secondRequestErrObject );
						}
					);
				} else {
					deferred.reject( err, errObject );
				}
			}
		);
	} );

	return deferred.promise();
}

/**
 * Checks if an error response is caused by providing a bad CSRF token.
 *
 * @param {Object} errObject
 * @return {boolean}
 * @internal
 */
function isBadTokenError( errObject ) {
	return errObject.xhr &&
		errObject.xhr.responseJSON &&
		errObject.xhr.responseJSON.errorKey &&
		errObject.xhr.responseJSON.errorKey === 'rest-badtoken';
}

module.exports = {
	getUserInfo: getUserInfo
};
