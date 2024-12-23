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

const EmbedFileFormatter = require( './mmv.EmbedFileFormatter.js' );
const Utils = require( './mmv.ui.utils.js' );
const { UiElement } = require( 'mmv' );

/**
 * UI component that provides the user html/wikitext snippets needed to share
 * and/or embed a media asset.
 */
class Embed extends UiElement {
	/**
	 * @param {jQuery} $container
	 */
	constructor( $container ) {
		super( $container );

		/**
		 * Formatter converting image data into formats needed for output
		 *
		 * @property {EmbedFileFormatter}
		 */
		this.formatter = new EmbedFileFormatter();

		Utils.createHeader( mw.msg( 'multimediaviewer-embed-tab' ) )
			.appendTo( $container );

		const $body = $( '<div>' )
			.addClass( 'cdx-dialog__body mw-mmv-pt-0 mw-mmv-pb-150' )
			.appendTo( $container );

		this.createSnippetSelectionButtons( $body );
		this.createSizePulldownMenus( $body );
		this.createSnippetTextAreas( $body );
	}

	/**
	 * Creates text areas for html and wikitext snippets.
	 *
	 * @param {jQuery} $container
	 */
	createSnippetTextAreas( $container ) {
		[ this.$embedTextHtml, this.$embedTextHtmlDiv ] = Utils.createInputWithCopy(
			mw.msg( 'multimediaviewer-reuse-copy-embed' ),
			mw.msg( 'multimediaviewer-reuse-loading-placeholder' )
		);
		this.$embedTextHtml.attr( 'title', mw.msg( 'multimediaviewer-embed-explanation' ) );

		[ this.$embedTextWikitext, this.$embedTextWikitextDiv ] = Utils.createInputWithCopy(
			mw.msg( 'multimediaviewer-reuse-copy-embed' ),
			mw.msg( 'multimediaviewer-reuse-loading-placeholder' )
		);
		this.$embedTextWikitext.attr( 'title', mw.msg( 'multimediaviewer-embed-explanation' ) );

		$container.append(
			this.$embedTextHtmlDiv,
			this.$embedTextWikitextDiv
		);
	}

	/**
	 * Creates snippet selection buttons.
	 *
	 * @param {jQuery} $container
	 */
	createSnippetSelectionButtons( $container ) {
		const $embedSwitch = $( '<div>' ).addClass( 'cdx-tabs mw-mmv-mb-75' ).appendTo( $container );
		const $embedSwitchHeader = $( '<div>' ).addClass( 'cdx-tabs__header' ).appendTo( $embedSwitch );
		this.$embedSwitchList = $( '<div>' ).addClass( 'cdx-tabs__list' ).attr( 'role', 'tablist' ).appendTo( $embedSwitchHeader );
		[ 'wikitext', 'html' ].forEach( ( name ) => $( '<button>' )
			.addClass( 'cdx-tabs__list__item' )
			.attr( 'role', 'tab' )
			.data( 'name', name )
			.text( mw.msg( name === 'wikitext' ? 'multimediaviewer-embed-wt' : 'multimediaviewer-embed-html' ) )
			.on( 'click', () => this.handleTypeSwitch( name ) )
			.appendTo( this.$embedSwitchList )
		);
	}

	/**
	 * Creates pulldown menus to select file sizes.
	 *
	 * @param {jQuery} $container
	 */
	createSizePulldownMenus( $container ) {
		// Wikitext sizes pulldown menu
		this.$embedSizeSwitchWikitext = Utils.createSelectMenu(
			[ 'default', 'small', 'medium', 'large' ],
			'default'
		);

		// Html sizes pulldown menu
		this.$embedSizeSwitchHtml = Utils.createSelectMenu(
			[ 'small', 'medium', 'large', 'original' ],
			'original'
		);

		$( '<div>' ).addClass( 'mw-mmv-flex mw-mmv-mb-75' ).append(
			this.$embedSizeSwitchHtml,
			this.$embedSizeSwitchWikitext
		).appendTo( $container );
	}

	/**
	 * Registers listeners.
	 */
	attach() {
		// Logged-out defaults to 'html', logged-in to 'wikitext'
		this.handleTypeSwitch( mw.user.isAnon() ? 'html' : 'wikitext' );

		// Register handlers for switching between file sizes
		this.$embedSizeSwitchHtml.on( 'change', () => this.handleSizeSwitch() );
		this.$embedSizeSwitchWikitext.on( 'change', () => this.handleSizeSwitch() );
	}

	/**
	 * Clears listeners.
	 */
	unattach() {
		super.unattach();

		this.$embedSizeSwitchHtml.off( 'change' );
		this.$embedSizeSwitchWikitext.off( 'change' );
	}

	/**
	 * Handles size menu change events.
	 */
	handleSizeSwitch() {
		// eslint-disable-next-line no-jquery/no-sizzle
		const $html = this.$embedSizeSwitchHtml.find( ':selected' );
		if ( $html.length ) {
			this.updateEmbedHtml( {}, $html.data( 'width' ), $html.data( 'height' ) );
		}
		// eslint-disable-next-line no-jquery/no-sizzle
		const $wikitext = this.$embedSizeSwitchWikitext.find( ':selected' );
		if ( $wikitext.length ) {
			this.updateEmbedWikitext( $wikitext.data( 'width' ) );
		}
	}

	/**
	 * Handles snippet type switch.
	 *
	 * @param {string} value 'html' or 'wikitext'
	 */
	handleTypeSwitch( value ) {
		this.$embedSwitchList.children().each( ( _i, element ) => {
			const $element = $( element );
			$element.attr( 'aria-selected', $element.data( 'name' ) === value );
		} );

		this.$embedTextHtmlDiv.toggle( value === 'html' );
		this.$embedSizeSwitchHtml.toggle( value === 'html' );

		this.$embedTextWikitextDiv.toggle( value === 'wikitext' );
		this.$embedSizeSwitchWikitext.toggle( value === 'wikitext' );
	}

	/**
	 * Reset current menu selection to default item.
	 */
	resetCurrentSizeMenuToDefault() {
		this.$embedSizeSwitchWikitext.val( 'default' );
		this.$embedSizeSwitchHtml.val( 'original' );
		this.handleSizeSwitch();
	}

	/**
	 * Sets the HTML embed text.
	 *
	 * Assumes that the set() method has already been called to update this.embedFileInfo
	 *
	 * @param {Thumbnail} thumbnail (can be just an empty object)
	 * @param {number} width New width to set
	 * @param {number} height New height to set
	 */
	updateEmbedHtml( thumbnail, width, height ) {
		if ( !this.embedFileInfo ) {
			return;
		}

		let src = thumbnail.url || this.embedFileInfo.imageInfo.url;

		// If the image dimension requested are "large", use the current image url
		if ( width > Embed.LARGE_IMAGE_WIDTH_THRESHOLD || height > Embed.LARGE_IMAGE_HEIGHT_THRESHOLD ) {
			src = this.embedFileInfo.imageInfo.url;
		}

		this.$embedTextHtml.val(
			this.formatter.getThumbnailHtml( this.embedFileInfo, src, width, height )
		);
	}

	/**
	 * Updates the wikitext embed text with a new value for width.
	 *
	 * Assumes that the set method has already been called.
	 *
	 * @param {number} width
	 */
	updateEmbedWikitext( width ) {
		if ( !this.embedFileInfo ) {
			return;
		}

		this.$embedTextWikitext.val(
			this.formatter.getThumbnailWikitextFromEmbedFileInfo( this.embedFileInfo, width )
		);
	}

	/**
	 * @typedef {Object} SizeOptions
	 * @memberof Embed
	 * @property {Utils.ImageSizes} html Collection of possible image sizes for html snippets
	 * @property {Utils.ImageSizes} wikitext Collection of possible image sizes for wikitext snippets
	 */

	/**
	 * Gets size options for html and wikitext snippets.
	 *
	 * @param {number} width
	 * @param {number} height
	 * @return {Embed.SizeOptions}
	 */
	getSizeOptions( width, height ) {
		const sizes = {};

		sizes.html = Utils.getPossibleImageSizesForHtml( width, height );
		sizes.wikitext = this.getPossibleImageSizesForWikitext( width, height );

		return sizes;
	}

	/**
	 * Sets the data on the element.
	 *
	 * @param {ImageModel} imageInfo
	 * @param {string} [caption]
	 * @param {string} [alt]
	 */
	set( imageInfo, caption, alt ) {
		const sizes = this.getSizeOptions( imageInfo.width, imageInfo.height );

		this.embedFileInfo = { imageInfo, caption, alt };

		Utils.updateSelectOptions( sizes.html, this.$embedSizeSwitchHtml.children() );
		Utils.updateSelectOptions( sizes.wikitext, this.$embedSizeSwitchWikitext.children() );

		// Reset defaults
		this.resetCurrentSizeMenuToDefault();

		Utils.getThumbnailUrlPromise( this.LARGE_IMAGE_WIDTH_THRESHOLD )
			.done( ( thumbnail ) => {
				this.updateEmbedHtml( thumbnail );
			} );
	}

	/**
	 * @inheritdoc
	 */
	empty() {
		this.$embedTextHtml.val( '' );
		this.$embedTextWikitext.val( '' );

		this.$embedSizeSwitchHtml.toggle( false );
		this.$embedSizeSwitchWikitext.toggle( false );
	}

	/**
	 * Calculates possible image sizes for wikitext snippets. It returns up to
	 * three possible snippet frame sizes (small, medium, large).
	 *
	 * @param {number} width
	 * @param {number} height
	 * @return {Utils.ImageSizes}
	 */
	getPossibleImageSizesForWikitext( width, height ) {
		const buckets = {
			small: 300,
			medium: 400,
			large: 500
		};
		const sizes = {};
		const widthToHeight = height / width;

		for ( const bucketName in buckets ) {
			const bucketWidth = buckets[ bucketName ];

			if ( width > bucketWidth ) {
				sizes[ bucketName ] = {
					width: bucketWidth,
					height: Math.round( bucketWidth * widthToHeight )
				};
			}
		}

		sizes.default = { width: null, height: null };

		return sizes;
	}
}

/**
 * @property {number} Width threshold at which an image is to be considered "large"
 * @static
 */
Embed.LARGE_IMAGE_WIDTH_THRESHOLD = 1200;

/**
 * @property {number} Height threshold at which an image is to be considered "large"
 * @static
 */
Embed.LARGE_IMAGE_HEIGHT_THRESHOLD = 900;

module.exports = Embed;
