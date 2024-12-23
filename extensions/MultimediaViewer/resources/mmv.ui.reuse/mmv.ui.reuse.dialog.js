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

const { Dialog } = require( 'mmv' );
const Embed = require( './mmv.ui.reuse.embed.js' );
const Share = require( './mmv.ui.reuse.share.js' );

/**
 * Represents the file reuse dialog and the link to open it.
 */
class ReuseDialog extends Dialog {
	/**
	 * @param {jQuery} $container the element to which the dialog will be appended
	 * @param {jQuery} $openButton the button which opens the dialog. Only used for positioning.
	 */
	constructor( $container, $openButton ) {
		super( $container, $openButton );

		this.share = new Share( this.$dialog );
		this.embed = new Embed( this.$dialog );
		this.$dialog.addClass( 'mw-mmv-reuse-dialog' );

		this.eventPrefix = 'use-this-file';
	}

	/**
	 * Registers listeners.
	 */
	attach() {
		this.share.attach();
		this.embed.attach();

		this.handleEvent( 'mmv-reuse-open', this.handleOpenCloseClick.bind( this ) );

		this.handleEvent( 'mmv-download-open', this.closeDialog.bind( this ) );
	}

	/**
	 * Sets data needed by contained panes and makes dialog launch link visible.
	 *
	 * @param {ImageModel} image
	 * @param {string} caption
	 * @param {string} alt
	 */
	set( image, caption, alt ) {
		this.share.set( image );
		this.embed.set( image, caption, alt );
		this.showImageWarnings( image );
	}

	/**
	 * Fired when the dialog is opened.
	 *
	 * @event ReuseDialog#mmv-reuse-opened
	 */

	/**
	 * Opens a dialog with information about file reuse.
	 */
	openDialog() {
		super.openDialog();

		this.$warning.insertAfter( this.$container );

		$( document ).trigger( 'mmv-reuse-opened' );
	}

	/**
	 * Fired when the dialog is closed.
	 *
	 * @event ReuseDialog#mmv-reuse-closed
	 */

	/**
	 * Closes the reuse dialog.
	 */
	closeDialog() {
		super.closeDialog();

		$( document ).trigger( 'mmv-reuse-closed' );
	}
}

module.exports = ReuseDialog;
