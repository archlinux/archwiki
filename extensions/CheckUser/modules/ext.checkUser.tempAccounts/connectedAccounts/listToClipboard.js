/**
 * Run code when the page loads.
 */
module.exports = function () {
	$( document ).on( 'click', '.ext-checkuser-related-tas-list-copy-button', () => {
		const content = $( 'li.ext-checkuser-related-ta .mw-tempuserlink bdi' )
			.map( function () {
				return $( this ).text().trim();
			} )
			.get()
			.join( '\n' );

		// eslint-disable-next-line compat/compat
		navigator.clipboard.writeText( content ).then( () => {
			mw.track( 'stats.mediawiki_checkuser_connected_talist_copied_total' );
			mw.notify(
				mw.msg( 'checkuser-contributions-temporary-accounts-related-list-copy-copied' ),
				{ type: 'success' }
			);
		} ).catch( ( e ) => {
			mw.errorLogger.logError( e, 'error.checkuser' );
			mw.track( 'stats.mediawiki_checkuser_connected_talist_copy_error_total' );
			mw.notify(
				mw.msg( 'checkuser-contributions-temporary-accounts-related-list-copy-error' ),
				{ type: 'error' }
			);
		} );
	} );
};
