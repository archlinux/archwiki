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

/**
 * A helper class for reuse logic.
 */
class Utils {
	/**
	 * Creates header from given options.
	 *
	 * @param {string} text
	 * @return {jQuery}
	 */
	static createHeader( text ) {
		const $header = $( '<div>' ).addClass( 'cdx-dialog__header' );
		$( '<p>' )
			.addClass( 'cdx-dialog__header__title' )
			.text( text )
			.appendTo( $header );
		return $header;
	}

	/**
	 * Creates input element with copy button from given options.
	 *
	 * @param {string} title
	 * @param {string} placeholder
	 * @return {jQuery[]} [$input, $div]
	 */
	static createInputWithCopy( title, placeholder ) {
		const $input = $( '<input>' )
			.addClass( 'cdx-text-input__input' )
			.attr( 'placeholder', placeholder );
		const $copyButton = $( '<button>' )
			.addClass( 'cdx-button cdx-button--action-default cdx-button--weight-normal cdx-button--size-medium cdx-button--framed' )
			.addClass( 'mw-mmv-pt-0 mw-mmv-pb-0' ) // override padding provided by ".oo-ui-buttonElement-framed.oo-ui-labelElement > .oo-ui-buttonElement-button, button"
			.attr( 'title', title )
			.append( $( '<span>' ).addClass( 'cdx-button__icon cdx-button__icon--copy' ).attr( 'aria-hidden', 'true' ) )
			.append( mw.msg( 'multimediaviewer-copy-button' ) )
			.on( 'click', () => {
				// navigator.clipboard() is not supported in Safari 11.1, iOS Safari 11.3-11.4
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( $input.val() );
					mw.notify( mw.msg( 'mw-widgets-copytextlayout-copy-success' ) );
				}
			} );
		const $div = $( '<div>' )
			.addClass( 'mw-mmv-flex mw-mmv-gap-50' )
			.append(
				$( '<div>' )
					.addClass( 'cdx-text-input mw-mmv-flex-grow-1' )
					.append( $input ),
				$copyButton
			);

		return [ $input, $div ];
	}

	/**
	 * Creates select menu from given options.
	 *
	 * @param {string[]} options
	 * @param {string} def
	 * @return {jQuery}
	 */
	static createSelectMenu( options, def ) {
		const $select = $( '<select>' ).addClass( 'cdx-select mw-mmv-flex-grow-1' );
		options.forEach( ( size ) => $( '<option>' )
			.attr( 'value', size )
			.data( 'name', size )
			.text( Utils.getDimensionsMessageHtml( size ) )
			.appendTo( $select )
		);
		$select.val( def );
		return $select;
	}

	/**
	 * Gets a promise for the large thumbnail URL. This is needed because thumbnail URLs cannot
	 * be reliably guessed, even if we know the full size of the image - most of the time replacing
	 * the size in another thumbnail URL works (as long as the new size is not larger than the full
	 * size), but if the file name is very long and with the larger size the URL length would
	 * exceed a certain threshold, a different schema is used instead.
	 *
	 * @param {number} width
	 *
	 * @fires MultimediaViewer#mmv-request-thumbnail
	 * @return {jQuery.Promise.<string>}
	 */
	static getThumbnailUrlPromise( width ) {
		return $( document ).triggerHandler( 'mmv-request-thumbnail', width ) ||
			$.Deferred().reject();
	}

	/**
	 * Updates the select options based on calculated sizes.
	 *
	 * @private
	 * @param {Object} sizes
	 * @param {jQuery} options
	 */
	static updateSelectOptions( sizes, options ) {
		for ( let i = 0; i < options.length; i++ ) {
			const $option = $( options[ i ] );
			const name = $option.data( 'name' );
			if ( sizes[ name ] ) {
				$option.prop( 'disabled', false );

				// These values are later used when the item is selected
				$option.data( 'width', sizes[ name ].width );
				$option.data( 'height', sizes[ name ].height );

				$option.text( Utils.getDimensionsMessageHtml( name, sizes[ name ].width, sizes[ name ].height ) );
			} else {
				$option.prop( 'disabled', true );
				$option.text( Utils.getDimensionsMessageHtml( name, undefined, undefined ) );
			}
		}
	}

	/**
	 * @typedef {Object} Dimensions
	 * @memberof Utils
	 * @property {number} width
	 * @property {number} height
	 */

	/**
	 * @typedef {Object} ImageSizes
	 * @memberof Utils
	 * @property {Utils.Dimensions} small
	 * @property {Utils.Dimensions} medium
	 * @property {Utils.Dimensions} large
	 * @property {Utils.Dimensions} [xl] Only present in HTML
	 * @property {Utils.Dimensions} [original] Only present in HTML
	 */

	/**
	 * Calculates possible image sizes for html snippets. It returns up to
	 * three possible snippet frame sizes (small, medium, large) plus the
	 * original image size.
	 *
	 * @param {number} width
	 * @param {number} height
	 * @return {Utils.ImageSizes}
	 */
	static getPossibleImageSizesForHtml( width, height ) {
		const buckets = {
			small: { width: 640, height: 480 },
			medium: { width: 1280, height: 720 }, // HD ready = 720p
			large: { width: 1920, height: 1080 }, // Full HD = 1080p
			xl: { width: 3840, height: 2160 } // 4K = 2160p
		};
		const sizes = {};
		const bucketNames = Object.keys( buckets );
		const widthToHeight = height / width;
		const heightToWidth = width / height;

		for ( let i = 0; i < bucketNames.length; i++ ) {
			const bucketName = bucketNames[ i ];
			const dimensions = buckets[ bucketName ];
			const bucketWidth = dimensions.width;
			const bucketHeight = dimensions.height;

			if ( width > bucketWidth ) {
				// Width fits in the current bucket
				const currentGuess = bucketWidth;

				if ( currentGuess * widthToHeight > bucketHeight ) {
					// Constrain in height, resize width accordingly
					sizes[ bucketName ] = {
						width: Math.round( bucketHeight * heightToWidth ),
						height: bucketHeight
					};
				} else {
					sizes[ bucketName ] = {
						width: currentGuess,
						height: Math.round( currentGuess * widthToHeight )
					};
				}
			} else if ( height > bucketHeight ) {
				// Height fits in the current bucket, resize width accordingly
				sizes[ bucketName ] = {
					width: Math.round( bucketHeight * heightToWidth ),
					height: bucketHeight
				};
			}
		}

		sizes.original = { width: width, height: height };

		return sizes;
	}

	/**
	 * Generates an i18n message for a label, given a size label and image dimensions
	 *
	 * @param {string} sizeLabel
	 * @param {number} width
	 * @param {number} height
	 * @return {string} i18n label html
	 */
	static getDimensionsMessageHtml( sizeLabel, width, height ) {
		const dimensions = !width || !height ? '' : mw.msg(
			'multimediaviewer-embed-dimensions-separated',
			mw.msg( 'multimediaviewer-embed-dimensions',
				width, height ) );

		// The following messages are used here:
		return mw.msg(
			// The following messages are used here:
			// * multimediaviewer-default-embed-dimensions
			// * multimediaviewer-original-embed-dimensions
			// * multimediaviewer-xl-embed-dimensions
			// * multimediaviewer-large-embed-dimensions
			// * multimediaviewer-medium-embed-dimensions
			// * multimediaviewer-small-embed-dimensions
			`multimediaviewer-${ sizeLabel }-embed-dimensions`,
			dimensions
		);
	}
}

module.exports = Utils;
