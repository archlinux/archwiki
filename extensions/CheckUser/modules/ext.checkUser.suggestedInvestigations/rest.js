/**
 * Sets the status of a case
 *
 * @param {number} caseId
 * @param {'open'|'resolved'|'invalid'} status
 * @param {string} reason
 * @param {boolean} [retryOnTokenMismatch=true]
 * @return {Promise<{caseId: number, status: string, reason: string, formattedReason: string}>}
 */
function setCaseStatus( caseId, status, reason, retryOnTokenMismatch ) {
	const restApi = new mw.Rest();
	const api = new mw.Api();
	const deferred = $.Deferred();

	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}

	api.getToken( 'csrf' ).then( ( token ) => {
		restApi.post(
			'/checkuser/v0/suggestedinvestigations/case/' + caseId + '/update',
			{
				token: token,
				status: status,
				reason: reason
			} )
			.then(
				( data ) => {
					deferred.resolve( data );
				},
				( err, errObject ) => {
					if ( retryOnTokenMismatch && isBadTokenError( errObject ) ) {
						// The CSRF token has expired. Retry the POST with a new token.
						api.badToken( 'csrf' );
						setCaseStatus( caseId, status, reason, false ).then(
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
	setCaseStatus: setCaseStatus
};
