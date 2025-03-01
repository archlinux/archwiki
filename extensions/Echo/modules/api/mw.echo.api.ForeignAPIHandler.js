( function () {
	/**
	 * Foreign notification API handler
	 *
	 * @class
	 * @extends mw.echo.api.LocalAPIHandler
	 *
	 * @constructor
	 * @param {string} apiUrl A url for the access point of the
	 *  foreign API.
	 * @param {Object} [config] Configuration object
	 * @param {boolean} [config.unreadOnly] Whether this handler should request unread
	 *  notifications by default.
	 */
	mw.echo.api.ForeignAPIHandler = function MwEchoApiForeignAPIHandler( apiUrl, config ) {
		config = config || {};

		// Parent constructor
		mw.echo.api.ForeignAPIHandler.super.call( this, config );

		this.api = new mw.ForeignApi( apiUrl );
		this.unreadOnly = config.unreadOnly !== undefined ? !!config.unreadOnly : false;
	};

	/* Setup */

	OO.inheritClass( mw.echo.api.ForeignAPIHandler, mw.echo.api.LocalAPIHandler );

	/**
	 * @inheritdoc
	 */
	mw.echo.api.ForeignAPIHandler.prototype.getTypeParams = function ( type ) {
		let params = {
			// Backwards compatibility
			notforn: 1
		};

		if ( this.unreadOnly ) {
			params = Object.assign( {}, params, { notfilter: '!read' } );
		}

		return Object.assign( {}, this.typeParams[ type ], params );
	};
}() );
