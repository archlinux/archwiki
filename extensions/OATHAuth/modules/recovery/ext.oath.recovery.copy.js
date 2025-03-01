/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

class CopyButton {

	static attach() {
		// eslint-disable-next-line no-jquery/no-global-selector
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
