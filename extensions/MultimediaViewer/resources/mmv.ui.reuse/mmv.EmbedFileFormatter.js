/*
 * This file is part of the MediaWiki extension MediaViewer.
 *
 * MediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

const { Config } = require( 'mmv.bootstrap' );
const { HtmlUtils } = require( 'mmv' );

/**
 * Converts data in various formats needed by the Embed sub-dialog
 */
class EmbedFileFormatter {
	/**
	 * Helper function to generate thumbnail wikicode
	 *
	 * @param {mw.Title} title
	 * @param {number} [width]
	 * @param {string} [caption]
	 * @param {string} [alt]
	 * @return {string}
	 */
	getThumbnailWikitext( title, width, caption, alt ) {
		const widthSection = width ? `|${ width }px` : '';
		const captionSection = caption ? `|${ caption }` : '';
		const altSection = alt ? `|alt=${ alt }` : '';

		return `[[${ title.getPrefixedText() }${ widthSection }|thumb${ captionSection }${ altSection }]]`;
	}

	/**
	 * Helper function to generate thumbnail wikicode
	 *
	 * @param {Object} info
	 * @param {ImageModel} info.imageInfo
	 * @param {number} [width]
	 * @return {string}
	 */
	getThumbnailWikitextFromEmbedFileInfo( info, width ) {
		const caption = info.caption ? HtmlUtils.htmlToText( info.caption ) : info.imageInfo.title.getNameText();
		return this.getThumbnailWikitext( info.imageInfo.title, width, caption, info.alt );
	}

	/**
	 * Byline construction
	 *
	 * @param {string} [author] author name (can contain HTML)
	 * @param {string} [source] source name (can contain HTML)
	 * @param {string} [attribution] custom attribution line (can contain HTML)
	 * @param {Function} [formatterFunction] Format function for the text - defaults to allowlisting HTML links, but all else sanitized.
	 * @return {string} Byline (can contain HTML)
	 */
	getByline( author, source, attribution, formatterFunction ) {
		formatterFunction = formatterFunction || ( ( txt ) => HtmlUtils.htmlToTextWithLinks( txt ) );

		if ( attribution ) {
			attribution = attribution && formatterFunction( attribution );
			return attribution;
		} else {
			author = author && formatterFunction( author );
			source = source && formatterFunction( source );

			if ( author && source ) {
				return mw.msg( 'multimediaviewer-credit', author, source );
			} else {
				return author || source;
			}
		}
	}

	/**
	 * Generates the plain text embed code for the image credit line.
	 *
	 * @param {ImageModel} imageInfo
	 * @return {string}
	 */
	getCreditText( imageInfo ) {
		const shortURL = imageInfo.descriptionShortUrl;
		const license = imageInfo.license;
		const byline = this.getByline(
			imageInfo.author,
			imageInfo.source,
			imageInfo.attribution,
			( txt ) => HtmlUtils.htmlToText( txt )
		);

		// If both the byline and licence are missing, the credit text is simply the URL
		if ( !byline && !license ) {
			return shortURL;
		}

		// The following messages are used here:
		// * multimediaviewer-text-embed-credit-text-bl
		// * multimediaviewer-text-embed-credit-text-b
		// * multimediaviewer-text-embed-credit-text-l
		const creditParams = [
			'multimediaviewer-text-embed-credit-text-'
		];

		if ( byline ) {
			creditParams[ 0 ] += 'b';
			creditParams.push( byline );
		}

		if ( license ) {
			creditParams[ 0 ] += 'l';
			creditParams.push( HtmlUtils.htmlToText( license.getShortName() ) );
		}

		creditParams.push( shortURL );
		return mw.message.apply( mw, creditParams ).plain();
	}

	/**
	 * Generates the HTML embed code for the image credit line.
	 *
	 * @param {ImageModel} imageInfo
	 * @return {string}
	 */
	getCreditHtml( imageInfo ) {
		const shortURL = imageInfo.descriptionShortUrl;
		const shortLink = HtmlUtils.makeLinkText( mw.message( 'multimediaviewer-html-embed-credit-link-text' ), { href: shortURL } );
		const license = imageInfo.license;
		const byline = this.getByline( imageInfo.author, imageInfo.source, imageInfo.attribution );

		if ( !byline && !license ) {
			return shortLink;
		}

		// The following messages are used here:
		// * multimediaviewer-html-embed-credit-text-bl
		// * multimediaviewer-html-embed-credit-text-b
		// * multimediaviewer-html-embed-credit-text-l
		const creditParams = [
			'multimediaviewer-html-embed-credit-text-'
		];

		if ( byline ) {
			creditParams[ 0 ] += 'b';
			creditParams.push( byline );
		}
		if ( license ) {
			creditParams[ 0 ] += 'l';
			creditParams.push( license.getShortLink() );
		}

		creditParams.push( shortLink );
		return mw.message.apply( mw, creditParams ).plain();
	}

	/**
	 * Generates the HTML embed code for the image.
	 *
	 * @param {Object} info
	 * @param {ImageModel} info.imageInfo
	 * @param {string} imgUrl URL to the file itself.
	 * @param {number} [width] Width to put into the image element.
	 * @param {number} [height] Height to put into the image element.
	 * @return {string} Embed code.
	 */
	getThumbnailHtml( info, imgUrl, width, height ) {
		return HtmlUtils.jqueryToHtml(
			$( '<p>' ).append(
				$( '<a>' )
					.attr( 'href', info.imageInfo.descriptionUrl + Config.getMediaHash( info.imageInfo.title ) )
					.append(
						$( '<img>' )
							.attr( 'src', imgUrl )
							.attr( 'alt', info.alt || info.imageInfo.title.getMainText() )
							.attr( 'height', height )
							.attr( 'width', width )
					),
				$( '<br>' ),
				this.getCreditHtml( info.imageInfo )
			)
		);
	}
}

module.exports = EmbedFileFormatter;
