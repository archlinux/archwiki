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
 * UI component that provides functionality to download the media asset displayed.
 */
class DownloadPane extends UiElement {
	/**
	 * @param {jQuery} $container
	 */
	constructor( $container ) {
		super( $container );

		this.formatter = new EmbedFileFormatter();
		this.createDownloadSection( this.$container );
		this.createAttributionButton( this.$container );

		/** @property {ImageModel|null} Image the download button currently points to. */
		this.image = null;
	}

	/**
	 * Creates dialog download section.
	 *
	 * @param {jQuery} $container
	 */
	createDownloadSection( $container ) {
		const $header = $( '<div>' )
			.addClass( 'cdx-dialog__header' )
			.appendTo( $container );
		$( '<div>' )
			.addClass( 'cdx-dialog__header__title' )
			.text( mw.msg( 'multimediaviewer-download-link' ) )
			.appendTo( $header );

		const $body = $( '<div>' )
			.addClass( 'cdx-dialog__body mw-mmv-pt-0' )
			.appendTo( $container );

		this.$downloadArea = $( '<div>' )
			.addClass( 'mw-mmv-flex mw-mmv-gap-50' )
			.appendTo( $body );
		this.createSizePulldownMenu( this.$downloadArea );
		this.createDownloadButton( this.$downloadArea );

		const $p = $( '<p>' ).addClass( 'mw-mmv-mt-75' ).appendTo( $body );
		this.createPreviewLink( $p );
	}

	/**
	 * Creates download split button. It is a link with the "download" property set plus an
	 * arrow that allows the user to select the image size desired. The "download" property
	 * triggers native browser downloading in browsers that support it. The fallback is the
	 * 'download' parameter which instructs the server to send the right headers so the browser
	 * downloads the file instead of just displaying it. If all this fails, the image will appear
	 * in another window/tab.
	 *
	 * @param {jQuery} $container
	 */
	createDownloadButton( $container ) {
		this.$downloadButton = $( '<a>' )
			.attr( 'target', '_blank' )
			.attr( 'download', '' )
			.addClass( 'cdx-button cdx-button--weight-primary cdx-button--action-progressive cdx-button--fake-button cdx-button--fake-button--enabled' )
			.html( '<span class="cdx-button__icon cdx-button__icon--download" aria-hidden="true"></span>' + mw.msg( 'multimediaviewer-download' ) )
			.appendTo( $container );
	}

	/**
	 * Creates pulldown menu to select image sizes.
	 *
	 * @param {jQuery} $container
	 */
	createSizePulldownMenu( $container ) {
		this.$downloadSizeMenu = Utils.createSelectMenu(
			[ 'original', 'small', 'medium', 'large', 'xl' ],
			'original'
		).appendTo( $container );
	}

	/**
	 * Creates preview link.
	 *
	 * @param {jQuery} $container
	 */
	createPreviewLink( $container ) {
		this.$previewLink = $( '<a>' )
			.attr( 'target', '_blank' )
			.addClass( 'cdx-docs-link' )
			.html( mw.msg( 'multimediaviewer-download-preview-link-title' ) )
			.appendTo( $container );
	}

	createAttributionButton( $container ) {
		[ this.$attributionInput, this.$attributionInputDiv ] = Utils.createInputWithCopy(
			mw.msg( 'multimediaviewer-download-attribution-copy' ), ''
		);

		const $header = $( '<div>' )
			.addClass( 'cdx-dialog__header' )
			.appendTo( $container );
		$( '<p>' )
			.addClass( 'cdx-dialog__header__title' )
			.text( mw.msg( 'multimediaviewer-download-attribution' ) )
			.appendTo( $header );
		this.$attributionHowHeader = $( '<p>' )
			.addClass( 'mw-mmv-mb-75' )
			.text( mw.msg( 'multimediaviewer-download-attribution-cta-header' ) );

		const $attributionTabs = $( '<div>' ).addClass( 'cdx-tabs' );
		const $attributionTabsHeader = $( '<div>' ).addClass( 'cdx-tabs__header' ).appendTo( $attributionTabs );
		this.$attributionTabsList = $( '<div>' ).addClass( 'cdx-tabs__list' ).attr( 'role', 'tablist' ).appendTo( $attributionTabsHeader );
		[ 'plain', 'html' ].forEach( ( name ) => $( '<button>' )
			.addClass( 'cdx-tabs__list__item' )
			.attr( 'role', 'tab' )
			.data( 'name', name )
			// The following messages are used here:
			// * multimediaviewer-attr-plain
			// * multimediaviewer-attr-html
			.text( mw.msg( 'multimediaviewer-attr-' + name ) )
			.on( 'click', () => this.selectAttribution( name ) )
			.appendTo( this.$attributionTabsList )
		);
		this.selectAttribution( 'plain' );

		$( '<div>' )
			.addClass( 'cdx-dialog__body mw-mmv-pt-0 mw-mmv-pb-150' )
			.append(
				this.$attributionHowHeader,
				$attributionTabs,
				this.$attributionInputDiv.addClass( ' mw-mmv-mt-75' )
			)
			.appendTo( $container );
	}

	/**
	 * Selects the specified attribution type.
	 *
	 * @param {string} [name='plain'] The attribution type to use ('plain' or 'html')
	 */
	selectAttribution( name ) {
		this.currentAttrView = name;

		this.$attributionTabsList.children().each( ( _i, element ) => {
			const $element = $( element );
			$element.attr( 'aria-selected', $element.data( 'name' ) === name );
		} );

		if ( this.currentAttrView === 'html' ) {
			this.$attributionInput.val( this.htmlCredit );
		} else {
			this.$attributionInput.val( this.textCredit );
		}
	}

	/**
	 * Registers listeners.
	 */
	attach() {
		// Register handlers for switching between file sizes
		this.$downloadSizeMenu.on( 'change', () => this.handleSizeSwitch() );
	}

	/**
	 * Clears listeners.
	 */
	unattach() {
		super.unattach();
		this.$downloadSizeMenu.off( 'change' );
	}

	/**
	 * Handles size menu change events.
	 */
	handleSizeSwitch() {
		// eslint-disable-next-line no-jquery/no-sizzle
		const $option = this.$downloadSizeMenu.find( ':selected' );
		const value = {
			name: $option.data( 'name' ),
			width: $option.data( 'width' ),
			height: $option.data( 'height' )
		};

		if ( value.name === 'original' && this.image !== null ) {
			this.setDownloadUrl( this.image.url );
		} else {
			// Disable download while we get the image
			this.$downloadButton.addClass( 'disabledLink' );
			Utils.getThumbnailUrlPromise( value.width ).done( ( thumbnail ) => {
				this.setDownloadUrl( thumbnail.url );
			} );
		}
	}

	/**
	 * Sets the URL on the download button.
	 *
	 * @param {string} url
	 */
	setDownloadUrl( url ) {
		this.$downloadButton.attr( 'href', `${ url }?download` );
		this.$previewLink.attr( 'href', url );

		// Re-enable download
		this.$downloadButton.removeClass( 'disabledLink' );
	}

	/**
	 * Sets the text in the attribution input element.
	 *
	 * @param {ImageModel} imageInfo
	 */
	setAttributionText( imageInfo ) {
		this.htmlCredit = this.formatter.getCreditHtml( imageInfo );
		this.textCredit = this.formatter.getCreditText( imageInfo );
		this.selectAttribution( this.currentAttrView );
	}

	/**
	 * Chops off the extension part of an URL.
	 *
	 * @param {string} url URL
	 * @return {string} Extension
	 */
	getExtensionFromUrl( url ) {
		const urlParts = url.split( '.' );
		return urlParts[ urlParts.length - 1 ];
	}

	/**
	 * Sets the data on the element.
	 *
	 * @param {ImageModel} image
	 */
	set( image ) {
		const license = image && image.license;
		const sizes = Utils.getPossibleImageSizesForHtml( image.width, image.height );

		this.image = image;

		Utils.updateSelectOptions( sizes, this.$downloadSizeMenu.children() );

		// Note: This extension will not be the real one for file types other than: png/gif/jpg/jpeg
		this.imageExtension = image.title.getExtension().toLowerCase();

		// Reset size menu to default item and update download button label now that we have the info
		this.$downloadSizeMenu.val( 'original' );
		this.handleSizeSwitch();

		if ( image ) {
			this.setAttributionText( image );
		}

		const attributionCtaMessage = ( license && license.needsAttribution() ) ?
			'multimediaviewer-download-attribution-cta-header' :
			'multimediaviewer-download-optional-attribution-cta-header';
		// Message defined above
		// eslint-disable-next-line mediawiki/msg-doc
		this.$attributionHowHeader.text( mw.msg( attributionCtaMessage ) );
	}

	/**
	 * @inheritdoc
	 */
	empty() {
		this.$downloadButton.attr( 'href', '' );
		this.$previewLink.attr( 'href', '' );
		this.imageExtension = undefined;

		this.image = null;
	}
}

module.exports = DownloadPane;
