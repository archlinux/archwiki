( function () {
	/**
	 * @typedef {Object} mw.Api.Options
	 * @property {Object} [parameters = { action: 'query', format: 'json' }] Default query
	 *  parameters for API requests
	 * @property {Object} [ajax = { url: mw.util.wikiScript( 'api' ), timeout: 30 * 1000, dataType: 'json' }]
	 *  Default options for jQuery#ajax
	 * @property {boolean} [useUS] Whether to use U+001F when joining multi-valued
	 *  parameters (since 1.28). Default is true if ajax.url is not set, false otherwise for
	 *  compatibility.
	 */

	/**
	 * @private
	 * @type {mw.Api.Options}
	 */
	let defaultOptions = null;

	/**
	 * @classdesc Interact with the MediaWiki API. `mw.Api` is a client library for
	 * the [action API](https://www.mediawiki.org/wiki/Special:MyLanguage/API:Main_page).
	 * An `mw.Api` object represents the API of a MediaWiki site. For the REST API,
	 * see {@link mw.Rest}.
	 *
	 * ```
	 * var api = new mw.Api();
	 * api.get( {
	 *     action: 'query',
	 *     meta: 'userinfo'
	 * } ).then( function ( data ) {
	 *     console.log( data );
	 * } );
	 * ```
	 *
	 * Since MW 1.25, multiple values for a parameter can be specified using an array:
	 *
	 * ```
	 * var api = new mw.Api();
	 * api.get( {
	 *     action: 'query',
	 *     meta: [ 'userinfo', 'siteinfo' ] // same effect as 'userinfo|siteinfo'
	 * } ).then( function ( data ) {
	 *     console.log( data );
	 * } );
	 * ```
	 *
	 * Since MW 1.26, boolean values for API parameters can be specified natively. Parameter
	 * values set to `false` or `undefined` will be omitted from the request, as required by
	 * the API.
	 *
	 * @class mw.Api
	 * @constructor
	 * @description Create an instance of `mw.Api`.
	 * @param {mw.Api.Options} [options] See {@link mw.Api.Options}. This can also be overridden for
	 *  each request by passing them to [get()]{@link mw.Api#get} or [post()]{@link mw.Api#post} (or directly to
	 *  [ajax()]{@link mw.Api#ajax}) later on.
	 */
	mw.Api = function ( options ) {
		const defaults = Object.assign( {}, options ),
			setsUrl = options && options.ajax && options.ajax.url !== undefined;

		defaults.parameters = Object.assign( {}, defaultOptions.parameters, defaults.parameters );
		defaults.ajax = Object.assign( {}, defaultOptions.ajax, defaults.ajax );

		// Force a string if we got a mw.Uri object
		if ( setsUrl ) {
			defaults.ajax.url = String( defaults.ajax.url );
		}
		if ( defaults.useUS === undefined ) {
			defaults.useUS = !setsUrl;
		}

		this.defaults = defaults;
		this.requests = [];
	};

	/**
	 * @private
	 * @type {mw.Api.Options}
	 */
	defaultOptions = {
		parameters: {
			action: 'query',
			format: 'json'
		},
		ajax: {
			url: mw.util.wikiScript( 'api' ),
			timeout: 30 * 1000, // 30 seconds
			dataType: 'json'
		}
	};

	function mapLegacyToken( action ) {
		// Legacy types for backward-compatibility with API action=tokens.
		const csrfActions = [
			'edit',
			'delete',
			'protect',
			'move',
			'block',
			'unblock',
			'email',
			'import',
			'options'
		];
		if ( csrfActions.indexOf( action ) !== -1 ) {
			mw.track( 'mw.deprecate', 'apitoken_' + action );
			mw.log.warn( 'Use of the "' + action + '" token is deprecated. Use "csrf" instead.' );
			return 'csrf';
		}
		return action;
	}

	function createTokenCache() {
		const tokenPromises = {};

		// Pre-populate with fake ajax promises to avoid HTTP requests for tokens that
		// we already have on the page from the embedded user.options module (T36733).
		tokenPromises[ defaultOptions.ajax.url ] = {};
		const tokens = mw.user.tokens.get();
		for ( const tokenKey in tokens ) {
			const value = tokens[ tokenKey ];
			// This requires #getToken to use the same key as mw.user.tokens.
			// Format: token-type + "Token" (eg. csrfToken, patrolToken, watchToken).
			tokenPromises[ defaultOptions.ajax.url ][ tokenKey ] = $.Deferred()
				.resolve( value )
				.promise( { abort: function () {} } );
		}

		return tokenPromises;
	}

	// Keyed by ajax url and symbolic name for the individual request
	let promises = createTokenCache();

	mw.Api.prototype = {
		/**
		 * Abort all unfinished requests issued by this Api object.
		 *
		 * @method
		 */
		abort: function () {
			this.requests.forEach( ( request ) => {
				if ( request ) {
					request.abort();
				}
			} );
		},

		/**
		 * Perform API get request. See [ajax()]{@link mw.Api#ajax} for details.
		 *
		 * @param {Object} parameters
		 * @param {Object} [ajaxOptions]
		 * @return {jQuery.Promise}
		 */
		get: function ( parameters, ajaxOptions ) {
			ajaxOptions = ajaxOptions || {};
			ajaxOptions.type = 'GET';
			return this.ajax( parameters, ajaxOptions );
		},

		/**
		 * Perform API post request. See [ajax()]{@link mw.Api#ajax} for details.
		 *
		 * @param {Object} parameters
		 * @param {Object} [ajaxOptions]
		 * @return {jQuery.Promise}
		 */
		post: function ( parameters, ajaxOptions ) {
			ajaxOptions = ajaxOptions || {};
			ajaxOptions.type = 'POST';
			return this.ajax( parameters, ajaxOptions );
		},

		/**
		 * Massage parameters from the nice format we accept into a format suitable for the API.
		 *
		 * NOTE: A value of undefined/null in an array will be represented by Array#join()
		 * as the empty string. Should we filter silently? Warn? Leave as-is?
		 *
		 * @private
		 * @param {Object} parameters (modified in-place)
		 * @param {boolean} useUS Whether to use U+001F when joining multivalued parameters.
		 */
		preprocessParameters: function ( parameters, useUS ) {
			let key;
			// Handle common MediaWiki API idioms for passing parameters
			for ( key in parameters ) {
				// Multiple values are pipe-separated
				if ( Array.isArray( parameters[ key ] ) ) {
					if ( !useUS || parameters[ key ].join( '' ).indexOf( '|' ) === -1 ) {
						parameters[ key ] = parameters[ key ].join( '|' );
					} else {
						parameters[ key ] = '\x1f' + parameters[ key ].join( '\x1f' );
					}
				} else if ( parameters[ key ] === false || parameters[ key ] === undefined ) {
					// Boolean values are only false when not given at all
					delete parameters[ key ];
				}
			}
		},

		/**
		 * Perform the API call.
		 *
		 * @param {Object} parameters Parameters to the API. See also {@link mw.Api.Options}
		 * @param {Object} [ajaxOptions] Parameters to pass to jQuery.ajax. See also
		 *   {@link mw.Api.Options}
		 * @return {jQuery.Promise} A promise that settles when the API response is processed.
		 *   Has an 'abort' method which can be used to abort the request.
		 *
		 *   - On success, resolves to `( result, jqXHR )` where `result` is the parsed API response.
		 *   - On an API error, rejects with `( code, result, result, jqXHR )` where `code` is the
		 *     [API error code](https://www.mediawiki.org/wiki/API:Errors_and_warnings), and `result`
		 *     is as above. When there are multiple errors, the code from the first one will be used.
		 *     If there is no error code, "unknown" is used.
		 *   - On other types of errors, rejects with `( 'http', details )` where `details` is an object
		 *     with three fields: `xhr` (the jqXHR object), `textStatus`, and `exception`.
		 *     The meaning of the last two fields is as follows:
		 *     - When the request is aborted (the abort method of the promise is called), textStatus
		 *       and exception are both set to "abort".
		 *     - On a network timeout, textStatus and exception are both set to "timeout".
		 *     - On a network error, textStatus is "error" and exception is the empty string.
		 *     - When the HTTP response code is anything other than 2xx or 304 (the API does not
		 *       use such response codes but some intermediate layer might), textStatus is "error"
		 *       and exception is the HTTP status text (the text following the status code in the
		 *       first line of the server response). For HTTP/2, `exception` is always an empty string.
		 *     - When the response is not valid JSON but the previous error conditions aren't met,
		 *       textStatus is "parsererror" and exception is the exception object thrown by
		 *       {@link JSON.parse}.
		 */
		ajax: function ( parameters, ajaxOptions ) {
			const api = this,
				apiDeferred = $.Deferred();

			parameters = Object.assign( {}, this.defaults.parameters, parameters );
			ajaxOptions = Object.assign( {}, this.defaults.ajax, ajaxOptions );

			let token;
			// Ensure that token parameter is last (per [[mw:API:Edit#Token]]).
			if ( parameters.token ) {
				token = parameters.token;
				delete parameters.token;
			}

			this.preprocessParameters( parameters, this.defaults.useUS );

			// If multipart/form-data has been requested and emulation is possible, emulate it
			if (
				ajaxOptions.type === 'POST' &&
				window.FormData &&
				ajaxOptions.contentType === 'multipart/form-data'
			) {

				const formData = new FormData();

				for ( const key in parameters ) {
					formData.append( key, parameters[ key ] );
				}
				// If we extracted a token parameter, add it back in.
				if ( token ) {
					formData.append( 'token', token );
				}

				ajaxOptions.data = formData;

				// Prevent jQuery from mangling our FormData object
				ajaxOptions.processData = false;
				// Prevent jQuery from overriding the Content-Type header
				ajaxOptions.contentType = false;
			} else {
				// This works because jQuery accepts data as a query string or as an Object
				ajaxOptions.data = $.param( parameters );
				// If we extracted a token parameter, add it back in.
				if ( token ) {
					ajaxOptions.data += '&token=' + encodeURIComponent( token );
				}

				if ( ajaxOptions.contentType === 'multipart/form-data' ) {
					// We were asked to emulate but can't, so drop the Content-Type header, otherwise
					// it'll be wrong and the server will fail to decode the POST body
					delete ajaxOptions.contentType;
				}
			}

			// Make the AJAX request
			const xhr = $.ajax( ajaxOptions )
				// If AJAX fails, reject API call with error code 'http'
				// and the details in the second argument.
				.fail( ( jqXHR, textStatus, exception ) => {
					apiDeferred.reject( 'http', {
						xhr: jqXHR,
						textStatus: textStatus,
						exception: exception
					} );
				} )
				// AJAX success just means "200 OK" response, also check API error codes
				.done( ( result, textStatus, jqXHR ) => {
					let code;
					if ( result === undefined || result === null || result === '' ) {
						apiDeferred.reject( 'ok-but-empty',
							'OK response but empty result (check HTTP headers?)',
							result,
							jqXHR
						);
					} else if ( result.error ) {
						// errorformat=bc
						code = result.error.code === undefined ? 'unknown' : result.error.code;
						apiDeferred.reject( code, result, result, jqXHR );
					} else if ( result.errors ) {
						// errorformat!=bc
						code = result.errors[ 0 ].code === undefined ? 'unknown' : result.errors[ 0 ].code;
						apiDeferred.reject( code, result, result, jqXHR );
					} else {
						apiDeferred.resolve( result, jqXHR );
					}
				} );

			const requestIndex = this.requests.length;
			this.requests.push( xhr );
			xhr.always( () => {
				api.requests[ requestIndex ] = null;
			} );
			// Return the Promise
			return apiDeferred.promise( { abort: xhr.abort } ).fail( ( code, details ) => {
				if ( !( code === 'http' && details && details.textStatus === 'abort' ) ) {
					mw.log( 'mw.Api error: ', code, details );
				}
			} );
		},

		/**
		 * Post to API with the specified type of token. If we have no token, get one and try to post.
		 * If we already have a cached token, try using that, and if the request fails using the cached token,
		 * blank it out and start over.
		 *
		 * @example <caption>For example, to change a user option, you could do:</caption>
		 * new mw.Api().postWithToken( 'csrf', {
		 *     action: 'options',
		 *     optionname: 'gender',
		 *     optionvalue: 'female'
		 * } );
		 *
		 * @param {string} tokenType The name of the token, like options or edit.
		 * @param {Object} params API parameters
		 * @param {Object} [ajaxOptions]
		 * @return {jQuery.Promise} See [post()]{@link mw.Api#post}
		 * @since 1.22
		 */
		postWithToken: function ( tokenType, params, ajaxOptions ) {
			const api = this,
				assertParams = {
					assert: params.assert,
					assertuser: params.assertuser
				},
				abortedPromise = $.Deferred().reject( 'http',
					{ textStatus: 'abort', exception: 'abort' } ).promise();
			let abortable,
				aborted;

			return api.getToken( tokenType, assertParams ).then( ( token ) => {
				params.token = token;
				// Request was aborted while token request was running, but we
				// don't want to unnecessarily abort token requests, so abort
				// a fake request instead
				if ( aborted ) {
					return abortedPromise;
				}

				return ( abortable = api.post( params, ajaxOptions ) ).catch(
					// Error handler
					function ( code ) {
						if ( code === 'badtoken' ) {
							api.badToken( tokenType );
							// Try again, once
							params.token = undefined;
							abortable = null;
							return api.getToken( tokenType, assertParams ).then( ( t ) => {
								params.token = t;
								if ( aborted ) {
									return abortedPromise;
								}

								return ( abortable = api.post( params, ajaxOptions ) );
							} );
						}

						// Let caller handle the error code
						return $.Deferred().rejectWith( this, arguments );
					}
				);
			} ).promise( { abort: function () {
				if ( abortable ) {
					abortable.abort();
				} else {
					aborted = true;
				}
			} } );
		},

		/**
		 * Get a token for a certain action from the API.
		 *
		 * @since 1.22
		 * @param {string} type Token type
		 * @param {Object|string} [additionalParams] Additional parameters for the API (since 1.35).
		 *   When given a string, it's treated as the 'assert' parameter (since 1.25).
		 * @return {jQuery.Promise<string>} Received token.
		 */
		getToken: function ( type, additionalParams ) {
			type = mapLegacyToken( type );
			if ( typeof additionalParams === 'string' ) {
				additionalParams = { assert: additionalParams };
			}

			const cacheKey = type + 'Token';
			let promiseGroup = promises[ this.defaults.ajax.url ];
			if ( !promiseGroup ) {
				promiseGroup = promises[ this.defaults.ajax.url ] = {};
			}
			let promise = promiseGroup && promiseGroup[ cacheKey ];

			function reject() {
				// Clear cache. Do not cache errors.
				delete promiseGroup[ cacheKey ];

				// Let caller handle the error code
				return $.Deferred().rejectWith( this, arguments );
			}

			if ( !promise ) {
				const apiPromise = this.get( Object.assign( {
					action: 'query',
					meta: 'tokens',
					type: type
				}, additionalParams ) );
				promise = apiPromise
					.then( ( res ) => {
						if ( !res.query ) {
							return reject( 'query-missing', res );
						}
						// If the token type is unknown, it is omitted from the response
						if ( !res.query.tokens[ type + 'token' ] ) {
							return $.Deferred().reject( 'token-missing', res );
						}
						return res.query.tokens[ type + 'token' ];
					}, reject )
					// Preserve abort handler
					.promise( { abort: apiPromise.abort } );

				// Optimization: Store the promise so we can reuse it immediately, even when
				// other async code requests before this one finishes.
				promiseGroup[ cacheKey ] = promise;
			}

			return promise;
		},

		/**
		 * Indicate that the cached token for a certain action of the API is bad.
		 *
		 * Call this if you get a 'badtoken' error when using the token returned by [getToken()]{@link mw.Api#getToken}.
		 * You may also want to use [postWithToken()]{@link mw.Api#postWithToken} instead, which invalidates bad cached tokens
		 * automatically.
		 *
		 * @param {string} type Token type
		 * @since 1.26
		 */
		badToken: function ( type ) {
			const promiseGroup = promises[ this.defaults.ajax.url ];

			type = mapLegacyToken( type );
			if ( promiseGroup ) {
				delete promiseGroup[ type + 'Token' ];
			}
		},

		/**
		 * Given an API response indicating an error, get a jQuery object containing a human-readable
		 * error message that you can display somewhere on the page.
		 *
		 * For better quality of error messages, it's recommended to use the following options in your
		 * API queries:
		 *
		 * ```
		 * errorformat: 'html',
		 * errorlang: mw.config.get( 'wgUserLanguage' ),
		 * errorsuselocal: true,
		 * ```
		 *
		 * Error messages, particularly for editing pages, may consist of multiple paragraphs of text.
		 * Your user interface should have enough space for that.
		 *
		 * @example
		 * var api = new mw.Api();
		 * // var title = 'Test valid title';
		 * var title = 'Test invalid title <>';
		 * api.postWithToken( 'watch', {
		 *   action: 'watch',
		 *   title: title
		 * } ).then( function ( data ) {
		 *   mw.notify( 'Success!' );
		 * }, function ( code, data ) {
		 *   mw.notify( api.getErrorMessage( data ), { type: 'error' } );
		 * } );
		 *
		 * @param {Object} data API response indicating an error
		 * @return {jQuery} Error messages, each wrapped in a `<div>`
		 */
		getErrorMessage: function ( data ) {
			if (
				data === undefined || data === null || data === '' ||
				// The #ajax method returns the data like this, it's not my fault...
				data === 'OK response but empty result (check HTTP headers?)'
			) {
				// The server failed so horribly that it did not set a HTTP error status
				return $( '<div>' ).append( mw.message( 'api-clientside-error-invalidresponse' ).parseDom() );

			} else if ( data.xhr ) {
				if ( data.textStatus === 'timeout' ) {
					// Hit the timeout (as defined above in defaultOptions)
					return $( '<div>' ).append( mw.message( 'api-clientside-error-timeout' ).parseDom() );
				} else if ( data.textStatus === 'abort' ) {
					// The request was cancelled by calling the abort() method on the promise
					return $( '<div>' ).append( mw.message( 'api-clientside-error-aborted' ).parseDom() );
				} else if ( data.textStatus === 'parsererror' ) {
					// Server returned invalid JSON
					// data.exception is probably a SyntaxError exception
					return $( '<div>' ).append( mw.message( 'api-clientside-error-invalidresponse' ).parseDom() );
				} else if ( data.xhr.status ) {
					// Server HTTP error
					// data.exception is probably the HTTP "reason phrase", e.g. "Internal Server Error"
					return $( '<div>' ).append( mw.message( 'api-clientside-error-http', data.xhr.status ).parseDom() );
				} else {
					// We don't know the status of the HTTP request. Common causes include (we have no way
					// to distinguish these): user losing their network connection (request wasn't even sent),
					// misconfigured CORS for cross-wiki queries.
					return $( '<div>' ).append( mw.message( 'api-clientside-error-noconnect' ).parseDom() );
				}

			} else if ( data.error ) {
				// errorformat: 'bc' (or not specified)
				return $( '<div>' ).text( data.error.info );

			} else if ( data.errors ) {
				// errorformat: 'html'
				return $( data.errors.map( ( err ) => {
					// formatversion: 1 / 2
					const $node = $( '<div>' ).html( err[ '*' ] || err.html );
					return $node[ 0 ];
				} ) );

			} else {
				// The server returned some valid but bogus JSON that probably doesn't even come from our API,
				// or this method was called incorrectly (e.g. with a successful response)
				mw.log.warn( 'mw.Api#getErrorMessage could not handle the response:', data );
				return $( '<div>' ).append( mw.message( 'api-clientside-error-invalidresponse' ).parseDom() );
			}
		}
	};

	if ( window.QUnit ) {
		mw.Api.resetTokenCacheForTest = function () {
			promises = createTokenCache();
		};
	}
}() );
