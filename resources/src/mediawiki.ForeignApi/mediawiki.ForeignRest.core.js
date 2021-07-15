module.exports = ( function () {

	/**
	 * Create an object like mw.Rest, but automatically handling everything required to communicate
	 * with another MediaWiki wiki via cross-origin requests (CORS).
	 *
	 *
	 * The foreign wiki must be configured to accept requests from the current wiki. See
	 * <https://www.mediawiki.org/wiki/Manual:$wgCrossSiteAJAXdomains> for details.
	 *
	 *     var api = new mw.ForeignRest( 'https://commons.wikimedia.org/w/rest.php' );
	 *     api.get( '/page/Main_Page/html' )
	 *     .done( function ( data ) {
	 *         console.log( data );
	 *     } );
	 *
	 * Authentication-related MediaWiki extensions may extend this class to ensure that the user
	 * authenticated on the current wiki will be automatically authenticated on the foreign one. These
	 * extension modules should be registered using the ResourceLoaderForeignApiModules hook. See
	 * CentralAuth for a practical example. The general pattern to extend and override the name is:
	 *
	 *     function MyForeignRest() {};
	 *     OO.inheritClass( MyForeignRest, mw.ForeignRest );
	 *     mw.ForeignRest = MyForeignRest;
	 *
	 * @class mw.ForeignRest
	 * @extends mw.Rest
	 * @since 1.36
	 *
	 * @constructor
	 * @param {string} url URL pointing to another wiki's `rest.php` endpoint.
	 * @param {mw.ForeignApi} foreignActionApi
	 * @param {Object} [options] See mw.Rest.
	 * @param {Object} [options.anonymous=false] Perform all requests anonymously. Use this option if
	 *     the target wiki may otherwise not accept cross-origin requests, or if you don't need to
	 *     perform write actions or read restricted information and want to avoid the overhead.
	 *
	 * @author Petr Pchelko
	 */
	function CoreForeignRest( url, foreignActionApi, options ) {
		this.apiUrl = url;
		this.anonymous = options && options.anonymous;
		this.foreignActionApi = foreignActionApi;

		options = $.extend( /* deep= */ true,
			{
				ajax: {
					url: this.apiUrl,
					xhrFields: {
						withCredentials: !this.anonymous
					}
				}
			},
			options
		);

		// Call parent constructor
		CoreForeignRest.parent.call( this, options );
	}

	OO.inheritClass( CoreForeignRest, mw.Rest );

	return CoreForeignRest;
}() );
