/**
 * @license GPL-2.0-or-later
 */

class CopyButton {

	static attach() {

		$( '.mw-oathauth-recoverycodes-copy-button' )
			.addClass( 'clipboard-api-supported' )
			.on( 'click', ( e ) => {
				e.preventDefault();
				// eslint-disable-next-line compat/compat
				navigator.clipboard.writeText( mw.config.get( 'oathauth-recoverycodes' ) ).then( () => {
					mw.notify( mw.msg( 'oathauth-recoverycodes-copy-success' ), {
						type: 'success',
						tag: 'recoverycodes'
					} );
				} );
			} );
	}
}

if ( navigator.clipboard && navigator.clipboard.writeText ) {
	// navigator.clipboard() is not supported in Safari 11.1, iOS Safari 11.3-11.4
	$( CopyButton.attach );
}
