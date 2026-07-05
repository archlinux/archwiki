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
 * Represents an image on the page.
 */
class LightboxImage {
	/**
	 * @param {string} fileLink Link to the file - generally a thumb URL
	 * @param {mw.Title} fileTitle Represents the File: page
	 * @param {number} index Which number file this is
	 * @param {number} position The relative position of this image to others with same file
	 * @param {HTMLImageElement} thumb The thumbnail that represents this image on the page
	 * @param {string} [caption] The caption, if any.
	 */
	constructor( fileLink, fileTitle, index, position, thumb, caption ) {
		/**
		 * Link to the file - generally a thumb URL
		 *
		 * @member {string}
		 */
		this.src = fileLink;

		/**
		 * Title of the image's file page
		 *
		 * @member {mw.Title}
		 */
		this.filePageTitle = fileTitle;

		/**
		 * What number this image is in the array of indexed images
		 *
		 * @member {number}
		 */
		this.index = index;

		/**
		 * The relative position of this image to others with same file
		 *
		 * @member {number}
		 */
		this.position = position;

		/**
		 * The <img> element that holds the already-loaded thumbnail of the image
		 *
		 * @member {HTMLImageElement}
		 */
		this.thumbnail = thumb;

		/**
		 * The caption of the image, if any
		 *
		 * @member {string}
		 */
		this.caption = caption;

		/**
		 * The alt text of the image
		 *
		 * @member {string}
		 */
		this.alt = $( thumb ).attr( 'alt' );

		/**
		 * Width of the full-sized file (read from HTML data attribute, might be missing)
		 *
		 * @member {number}
		 */
		this.originalWidth = parseInt( $( thumb ).attr( 'data-file-width' ), 10 );

		/**
		 * Height of the full-sized file (read from HTML data attribute, might be missing)
		 *
		 * @member {number}
		 */
		this.originalHeight = parseInt( $( thumb ).attr( 'data-file-height' ), 10 );
	}
}

module.exports = LightboxImage;
