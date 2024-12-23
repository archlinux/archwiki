/*
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

const UiElement = require( './mmv.ui.js' );

/**
 * Represents the buttons which are displayed over the image - next, previous, close
 * and fullscreen.
 */
class CanvasButtons extends UiElement {
	/**
	 * @param {jQuery} $container The parent element we should put the buttons into.
	 * @param {jQuery} $closeButton The close button element from the parent class.
	 * @param {jQuery} $fullscreenButton The fullscreen button from the parent class.
	 * @fires MultimediaViewer#mmv-close
	 */
	constructor( $container, $closeButton, $fullscreenButton ) {
		super( $container );

		this.$close = $closeButton;
		this.$fullscreen = $fullscreenButton;

		this.$reuse = $( '<a>' )
			.attr( 'role', 'button' )
			.addClass( 'cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--icon-only mw-mmv-button mw-mmv-reuse-button' )
			.prop( 'title', mw.msg( 'multimediaviewer-reuse-link' ) )
			.append( $( '<span>' ).addClass( 'mw-mmv-icon' ) );

		this.$download = $( '<a>' )
			.attr( 'role', 'button' )
			.addClass( 'cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--icon-only mw-mmv-button mw-mmv-download-button' )
			.prop( 'title', mw.msg( 'multimediaviewer-download-link' ) )
			.append( $( '<span>' ).addClass( 'mw-mmv-icon' ) );

		this.$next = $( '<button>' )
			.prop( 'title', mw.msg( 'multimediaviewer-next-image-alt-text' ) )
			.addClass( 'cdx-button cdx-button--icon-only cdx-button--size-large mw-mmv-button mw-mmv-next-image' )
			.append( $( '<span>' ).addClass( 'mw-mmv-icon' ) );

		this.$prev = $( '<button>' )
			.prop( 'title', mw.msg( 'multimediaviewer-prev-image-alt-text' ) )
			.addClass( 'cdx-button cdx-button--icon-only cdx-button--size-large mw-mmv-button mw-mmv-prev-image' )
			.append( $( '<span>' ).addClass( 'mw-mmv-icon' ) );

		this.$nav = this.$next
			.add( this.$prev )
			.hide();

		this.$buttons = this.$close
			.add( this.$download )
			.add( this.$reuse )
			.add( this.$fullscreen )
			.add( this.$next )
			.add( this.$prev );

		this.$buttons.appendTo( this.$container );

		$( document ).on( 'mmv-close', () => {
			this.$nav.hide();
		} );

		this.$close.on( 'click', () => {
			$container.trigger( $.Event( 'mmv-close' ) );
		} );

		this.$next.on( 'click', () => {
			this.emit( 'next' );
		} );

		this.$prev.on( 'click', () => {
			this.emit( 'prev' );
		} );
	}

	/**
	 * Sets the top offset for the navigation buttons.
	 *
	 * @param {number} offset
	 */
	setOffset( offset ) {
		this.$nav.css( {
			top: offset
		} );
	}

	/**
	 * Registers listeners.
	 *
	 * @fires ReuseDialog#mmv-reuse-opened
	 * @fires ReuseDialog#mmv-reuse-closed
	 * @fires DownloadDialog#mmv-download-opened
	 * @fires DownloadDialog#mmv-download-closed
	 */
	attach() {
		this.$reuse.on( 'click.mmv-canvasButtons', ( e ) => {
			$( document ).trigger( 'mmv-reuse-open', e );
			return false;
		} );
		this.handleEvent( 'mmv-reuse-opened', () => this.$reuse.addClass( 'open' ) );
		this.handleEvent( 'mmv-reuse-closed', () => this.$reuse.removeClass( 'open' ) );

		this.$download.on( 'click.mmv-canvasButtons', ( e ) => {
			$( document ).trigger( 'mmv-download-open', e );
			return false;
		} );
		this.handleEvent( 'mmv-download-opened', () => this.$download.addClass( 'open' ) );
		this.handleEvent( 'mmv-download-closed', () => this.$download.removeClass( 'open' ) );

		this.$download
			.add( this.$reuse )
			.add( this.$close )
			.add( this.$fullscreen );
	}

	/**
	 * Removes all UI things from the DOM, or hides them
	 */
	unattach() {
		super.unattach();

		this.$download
			.add( this.$reuse )
			.add( this.$options )
			.add( this.$close )
			.add( this.$fullscreen )
			.off( 'click.mmv-canvasButtons' );
	}

	/**
	 * @param {ImageModel} image
	 */
	set( image ) {
		this.$reuse.prop( 'href', image.descriptionUrl );
		this.$download.prop( 'href', image.url );
	}

	empty() {
		this.$reuse
			.removeClass( 'open' )
			.prop( 'href', null );
		this.$download
			.prop( 'href', null );
	}
}

module.exports = CanvasButtons;
