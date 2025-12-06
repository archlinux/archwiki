/**
 * Perform a REST API request to reveal the IP address(es) used for given revIds, logIds,
 * and aflIds performed by temporary accounts. If no ids are specified, this will return
 * the last IP address used by a temporary account.
 *
 * @param {string} target
 * @param {Object} revIds
 * @param {Object} logIds
 * @param {Object} aflIds
 * @param {boolean} [retryOnTokenMismatch]
 * @return {Promise}
 */
function performRevealRequest( target, revIds, logIds, aflIds, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}

	const deferred = $.Deferred();

	/**
	 * Takes an object with array of integers and turns it into an array of strings.
	 *
	 * This is used when building requests for fetching data by log IDs, since
	 * these IDs are integers, but the backend API expects strings.
	 *
	 * @param {{allIds: number[] | null} | null} ids Values to cast into strings
	 * @return {string[]}
	 */
	const makeStringIDs = ( ids ) => {
		if ( !ids || !ids.allIds || !Array.isArray( ids.allIds ) ) {
			return [];
		}
		return ids.allIds.map( ( id ) => id.toString() );
	};

	const request = {
		[ target ]: {
			revIds: makeStringIDs( revIds ),
			logIds: makeStringIDs( logIds ),
			lastUsedIp: true
		}
	};

	if ( mw.config.get( 'wgCheckUserAbuseFilterExtensionLoaded' ) ) {
		request[ target ].abuseLogIds = makeStringIDs( aflIds );
	}

	performBatchRevealRequestInternal( request, retryOnTokenMismatch )
		.then( ( data ) => {
			let key;
			if ( isAbuseFilterLogLookup( aflIds ) ) {
				key = 'abuseLogIps';
			} else if ( isRevisionLookup( revIds ) ) {
				key = 'revIps';
			} else if ( isLogLookup( logIds ) ) {
				key = 'logIps';
			} else {
				key = 'lastUsedIp';
			}

			// Adjust the response format to what's expected by the caller
			if ( !Object.prototype.hasOwnProperty.call( data, target ) ||
				!Object.prototype.hasOwnProperty.call( data[ target ], key ) ) {
				throw new Error( 'Malformed response' );
			}

			// Request was made without any IDs, so return the last used IP
			if ( key === 'lastUsedIp' ) {
				return deferred.resolve( {
					ips: [ data[ target ].lastUsedIp ],
					autoReveal: data.autoReveal
				} );
			}

			deferred.resolve( {
				ips: data[ target ][ key ],
				autoReveal: data.autoReveal
			} );
		} ).catch( ( err ) => {
			deferred.reject( err, {} );
		} );

	return deferred.promise();
}

/**
 * Perform a REST API request to reveal all the IP addresses used by a temporary account.
 *
 * @param {string} target
 * @param {boolean} [retryOnTokenMismatch]
 * @return {Promise}
 */
function performFullRevealRequest( target, retryOnTokenMismatch ) {
	const restApi = new mw.Rest();
	const api = new mw.Api();
	const deferred = $.Deferred();

	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}

	api.getToken( 'csrf' ).then( ( token ) => {
		restApi.post(
			'/checkuser/v0/temporaryaccount/' + target,
			{ token: token } )
			.then(
				( data ) => {
					deferred.resolve( data );
				},
				( err, errObject ) => {
					if ( retryOnTokenMismatch && isBadTokenError( errObject ) ) {
						// The CSRF token has expired. Retry the POST with a new token.
						api.badToken( 'csrf' );
						performFullRevealRequest( target, false ).then(
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
 * @typedef {Object} RevealRequest
 * @property {string[]} revIds
 * @property {string[]} logIds
 * @property {boolean} lastUsedIp
 */

/** @typedef {Map<string, RevealRequest>} BatchRevealRequest */

/** @type {Object<string, Promise>} */
const requests = {};

/**
 * Reveal multiple IP addresses in a single request.
 *
 * @param {BatchRevealRequest} request
 * @param {boolean} retryOnTokenMismatch
 * @return {Promise}
 */
function performBatchRevealRequest( request, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}

	// De-duplicate requests using the same request parameters.
	const serialized = JSON.stringify( request );
	if ( Object.prototype.hasOwnProperty.call( requests, serialized ) ) {
		return requests[ serialized ];
	}

	const requestPromise = performBatchRevealRequestInternal( request, retryOnTokenMismatch )
		.then( ( response ) => {
			delete requests[ serialized ];
			return response;
		} )
		.catch( ( err ) => {
			delete requests[ serialized ];
			return err;
		} );

	requests[ serialized ] = requestPromise;

	return requestPromise;
}

/**
 * @param {BatchRevealRequest} request
 * @param {boolean} retryOnTokenMismatch
 * @return {Promise}
 */
function performBatchRevealRequestInternal( request, retryOnTokenMismatch ) {
	const restApi = new mw.Rest();
	const api = new mw.Api();
	const deferred = $.Deferred();

	api.getToken( 'csrf' ).then( ( token ) => {
		restApi.post( '/checkuser/v0/batch-temporaryaccount', { token: token, users: request } ).then(
			( data ) => {
				deferred.resolve( data );
			},
			( err, errObject ) => {
				if ( retryOnTokenMismatch && isBadTokenError( errObject ) ) {
					// The CSRF token has expired. Retry the POST with a new token.
					api.badToken( 'csrf' );
					performBatchRevealRequestInternal( request, false ).then(
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
	} ).catch( ( err, errObject ) => {
		deferred.reject( err, errObject );
	} );

	return deferred.promise();
}

/**
 * Determine whether to look up IPs for revision IDs.
 *
 * @param {Object} revIds
 * @return {boolean} There are revision IDs
 */
function isRevisionLookup( revIds ) {
	return !!( revIds && revIds.allIds && revIds.allIds.length );
}

/**
 * Determine whether to look up IPs for log IDs.
 *
 * @param {Object} logIds
 * @return {boolean} There are log IDs
 */
function isLogLookup( logIds ) {
	return !!( logIds && logIds.allIds && logIds.allIds.length );
}

/**
 * Determine whether to look up IPs for AbuseFilter log IDs.
 *
 * @param {Object} aflIds
 * @return {boolean} There are revision IDs
 */
function isAbuseFilterLogLookup( aflIds ) {
	return !!( aflIds && aflIds.allIds && aflIds.allIds.length );
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
	performRevealRequest: performRevealRequest,
	performFullRevealRequest: performFullRevealRequest,
	performBatchRevealRequest: performBatchRevealRequest,
	isRevisionLookup: isRevisionLookup,
	isLogLookup: isLogLookup,
	isAbuseFilterLogLookup: isAbuseFilterLogLookup
};
