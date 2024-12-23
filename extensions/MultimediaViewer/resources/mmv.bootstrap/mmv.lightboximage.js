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
		/** @property {string} Link to the file - generally a thumb URL */
		this.src = fileLink;

		/** @property {mw.Title} filePageTitle Title of the image's file page */
		this.filePageTitle = fileTitle;

		/** @property {number} index What number this image is in the array of indexed images */
		this.index = index;

		/** @property {number} position The relative position of this image to others with same file */
		this.position = position;

		/** @property {HTMLImageElement} thumbnail The <img> element that holds the already-loaded thumbnail of the image */
		this.thumbnail = thumb;

		/** @property {string} caption The caption of the image, if any */
		this.caption = caption;

		/** @property {string} The alt text of the image */
		this.alt = $( this.thumbnail ).attr( 'alt' );

		/** @property {number} originalWidth of the full-sized file (read from HTML data attribute, might be missing) */
		this.originalWidth = parseInt( $( this.thumbnail ).data( 'file-width' ), 10 );

		/** @property {number} originalHeight Height of the full-sized file (read from HTML data attribute, might be missing) */
		this.originalHeight = parseInt( $( this.thumbnail ).data( 'file-height' ), 10 );
	}
}

module.exports = LightboxImage;
