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

const UiElement = require( './mmv.ui.js' );

/**
 * Class for buttons which are placed on the metadata stripe (the always visible part of the
 * metadata panel).
 */
class StripeButtons extends UiElement {
	/**
	 * @param {jQuery} $container the title block (.mw-mmv-title-contain) which wraps the buttons and all
	 *  other title elements
	 */
	constructor( $container ) {
		super( $container );

		this.$buttonContainer = $( '<div>' )
			.addClass( 'mw-mmv-stripe-button-container' )
			.appendTo( $container );

		/**
		 * A button linking to the file description page.
		 */
		this.$descriptionPage = $( '<a>' )
			.addClass( 'mw-mmv-stripe-button empty mw-mmv-description-page-button cdx-button cdx-button--weight-primary cdx-button--action-progressive cdx-button--size-large cdx-button--fake-button cdx-button--fake-button--enabled' )
			// elements are right-floated so we use prepend instead of append to keep the order
			.prependTo( this.$buttonContainer );
	}

	/**
	 * @inheritdoc
	 * @param {LightboxImage} image
	 * @param {ImageModel} imageInfo
	 */
	set( image, imageInfo ) {
		const match = image && image.src ?
			image.src.match( /(lang|page)([\d\-a-z]+)-(\d+)px/ ) : // multi lingual SVG or PDF page
			null;

		const commons = '//commons.wikimedia.org';
		const isCommonsServer = String( mw.config.get( 'wgServer' ) ).includes( commons );
		let descriptionUrl = imageInfo.descriptionUrl;
		let isCommons = String( descriptionUrl ).includes( commons );

		if ( imageInfo.pageID && !isCommonsServer ) {
			const params = {};
			if ( match ) {
				params[ match[ 1 ] ] = match[ 2 ];
			}
			// The file has a local description page, override the description URL
			descriptionUrl = imageInfo.title.getUrl( params );
			isCommons = false;
		} else {
			const parsedUrl = new URL( descriptionUrl, location );
			if ( match ) {
				parsedUrl.searchParams.set( match[ 1 ], match[ 2 ] );
			}
			descriptionUrl = parsedUrl.toString();
		}

		this.$descriptionPage.empty()
			.append( $( '<span>' ).addClass( 'cdx-button__icon' ) )
			.append( mw.msg( 'multimediaviewer-repository-local' ) )
			.attr( 'href', descriptionUrl )
			.removeClass( 'empty' )
			.toggleClass( 'mw-mmv-repo-button-commons', isCommons );
	}

	/**
	 * @inheritdoc
	 */
	empty() {
		this.$descriptionPage.attr( { href: null, title: null, 'original-title': null } )
			.addClass( 'empty' )
			.removeClass( 'mw-mmv-repo-button-commons' );
	}
}

module.exports = StripeButtons;
