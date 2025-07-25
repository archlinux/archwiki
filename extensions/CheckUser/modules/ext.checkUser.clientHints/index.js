( function () {
	/**
	 * Set up the listener for the postEdit hook, if client hints are supported by the browser.
	 *
	 * @param {Navigator|Object} navigatorData
	 * @return {boolean} true if client hints integration has been set up on postEdit hook,
	 *   false otherwise.
	 */
	function init( navigatorData ) {
		const hasHighEntropyValuesMethod = navigatorData.userAgentData &&
			navigatorData.userAgentData.getHighEntropyValues;
		if ( !hasHighEntropyValuesMethod ) {
			// The browser doesn't support navigator.userAgentData.getHighEntropyValues. Used
			// for tests.
			return false;
		}

		const wgCheckUserClientHintsHeadersJsApi = mw.config.get( 'wgCheckUserClientHintsHeadersJsApi' );

		/**
		 * POST an object with user-agent client hint data to a CheckUser REST endpoint.
		 *
		 * @param {Object} clientHintData Data structured returned by
		 *  navigator.userAgentData.getHighEntropyValues()
		 * @param {number} identifier The ID associated with the event
		 * @param {string} type The type of event (e.g. 'revision').
		 * @param {boolean} retryOnTokenMismatch Whether to retry the POST if the CSRF token is a
		 *  mismatch. A mismatch can happen if the token has expired.
		 * @return {jQuery.Promise} A promise that resolves after the POST is complete.
		 */
		function postClientHintData(
			clientHintData, identifier, type, retryOnTokenMismatch
		) {
			const restApi = new mw.Rest();
			const api = new mw.Api();
			const deferred = $.Deferred();
			api.getToken( 'csrf' ).then( ( token ) => {
				clientHintData.token = token;
				restApi.post(
					'/checkuser/v0/useragent-clienthints/' + type + '/' + identifier,
					clientHintData
				).then(
					( data ) => {
						deferred.resolve( data );
					}
				).catch( ( err, errObject ) => {
					mw.log.error( errObject );
					let errMessage = errObject.exception;
					if (
						errObject.xhr &&
						errObject.xhr.responseJSON &&
						errObject.xhr.responseJSON.messageTranslations
					) {
						errMessage = errObject.xhr.responseJSON.messageTranslations.en;
					}
					if (
						retryOnTokenMismatch &&
						errObject.xhr &&
						errObject.xhr.responseJSON &&
						errObject.xhr.responseJSON.errorKey &&
						errObject.xhr.responseJSON.errorKey === 'rest-badtoken'
					) {
						// The CSRF token has expired. Retry the POST with a new token.
						api.badToken( 'csrf' );
						postClientHintData( clientHintData, identifier, type, false ).then(
							( data ) => {
								deferred.resolve( data );
							},
							( secondRequestErr, secondRequestErrObject ) => {
								deferred.reject( secondRequestErr, secondRequestErrObject );
							}
						);
					} else {
						mw.errorLogger.logError( new Error( errMessage ), 'error.checkuser' );
						deferred.reject( err, errObject );
					}
				} );
			} ).catch( ( err, errObject ) => {
				mw.log.error( errObject );
				let errMessage = errObject.exception;
				if ( errObject.xhr &&
				errObject.xhr.responseJSON &&
				errObject.xhr.responseJSON.messageTranslations ) {
					errMessage = errObject.xhr.responseJSON.messageTranslations.en;
				}
				mw.errorLogger.logError( new Error( errMessage ), 'error.checkuser' );
				deferred.reject( err, errObject );
			} );
			return deferred.promise();
		}

		/**
		 * Collect and POST Client Hints data for a given event.
		 *
		 * @param {number} identifier The ID associated with the event
		 * @param {string} type The type of event (e.g. 'revision').
		 * @return {Promise<Object>}
		 */
		function collectAndSendClientHintsData( identifier, type ) {
			return collectClientHintsData().then( ( userAgentHighEntropyValues ) => {
				postClientHintData( userAgentHighEntropyValues, identifier, type, true );
			} );
		}

		/**
		 * Collect high entropy client hints data.
		 *
		 * @return {Promise<Object>}
		 */
		function collectClientHintsData() {
			try {
				return navigatorData.userAgentData.getHighEntropyValues(
					wgCheckUserClientHintsHeadersJsApi
				);
			} catch ( err ) {
				// Handle NotAllowedError, if the browser throws it.
				mw.log.error( err );
				mw.errorLogger.logError( new Error( err ), 'error.checkuser' );
				return Promise.reject( err );
			}
		}

		// Collect and send Client Hints data if the user has just performed a
		// CheckUser private event.
		const privateEventId = mw.config.get( 'wgCheckUserClientHintsPrivateEventId' );
		if ( privateEventId ) {
			collectAndSendClientHintsData( privateEventId, 'privatelog' );
		}

		/**
		 * Respond to postEdit hook, fired by MediaWiki core, VisualEditor and DiscussionTools.
		 *
		 * Note that CheckUser only adds this code to article page views if
		 * CheckUserClientHintsEnabled is set to true.
		 */
		mw.hook( 'postEdit' ).add( () => {
			collectAndSendClientHintsData( mw.config.get( 'wgCurRevisionId' ), 'revision' );
		} );

		/**
		 * Respond to JS logout flow in core, and add high entropy client hint data to
		 * the request to ApiLogout.
		 */
		mw.hook( 'extendLogout' ).add( ( data ) => {
			// eslint-disable-next-line arrow-body-style
			data.promise = data.promise.then( () => {
				return collectClientHintsData().then( ( userAgentHighEntropyValues ) => {
					data.params.checkuserclienthints = JSON.stringify( userAgentHighEntropyValues );
				} );
			} );
		} );

		return true;
	}

	init( navigator );

	module.exports = {
		init: init
	};
}() );
