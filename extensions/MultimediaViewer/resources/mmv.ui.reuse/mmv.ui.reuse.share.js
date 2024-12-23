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

const { Config } = require( 'mmv.bootstrap' );
const Utils = require( './mmv.ui.utils.js' );
const { UiElement } = require( 'mmv' );

/**
 * Represents the file reuse dialog and link to open it.
 */
class Share extends UiElement {
	/**
	 * @param {jQuery} $container
	 */
	constructor( $container ) {
		super( $container );

		Utils.createHeader( mw.msg( 'multimediaviewer-share-tab' ) )
			.appendTo( $container );

		const $body = $( '<div>' )
			.addClass( 'cdx-dialog__body mw-mmv-pt-0' )
			.appendTo( $container );

		[ this.$pageInput, this.$pageInputDiv ] = Utils.createInputWithCopy(
			mw.msg( 'multimediaviewer-reuse-copy-share' ),
			mw.msg( 'multimediaviewer-reuse-loading-placeholder' )
		);
		this.$pageInput.attr( 'title', mw.msg( 'multimediaviewer-share-explanation' ) );
		this.$pageInputDiv.appendTo( $body );
	}

	/**
	 * Shows the pane.
	 */
	show() {
		super.show();
	}

	/**
	 * @inheritdoc
	 * @param {ImageModel} image
	 */
	set( image ) {
		const url = image.descriptionUrl + Config.getMediaHash( image.title );
		this.$pageInput.val( url );
	}

	/**
	 * @inheritdoc
	 */
	empty() {
		this.$pageInput.val( '' );
	}
}

module.exports = Share;
