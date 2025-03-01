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

const Canvas = require( './ui/mmv.ui.canvas.js' );
const CanvasButtons = require( './ui/mmv.ui.canvasButtons.js' );
const MetadataPanel = require( './ui/mmv.ui.metadataPanel.js' );
const ThumbnailWidthCalculator = require( './mmv.ThumbnailWidthCalculator.js' );
const UiElement = require( './ui/mmv.ui.js' );

/** Proxy for a Dialog. Initialises and attaches the dialog upon first use. */
class DialogProxy extends UiElement {
	constructor( eventName, initDialog ) {
		super();
		this.eventName = eventName;
		this.initDialog = initDialog;
	}

	attach() {
		this.handleEvent( this.eventName, this.handleOpenCloseClick.bind( this ) );
	}

	set( ...setValues ) {
		this.setValues = setValues;
	}

	handleOpenCloseClick() {
		mw.loader.using( 'mmv.ui.reuse', ( req ) => {
			this.unattach();
			const dialog = this.initDialog( req );
			dialog.attach();
			dialog.set( ...this.setValues );
			dialog.handleOpenCloseClick();
		} );
	}

	closeDialog() {}
}

/**
 * Represents the main interface of the lightbox
 */
class LightboxInterface extends UiElement {

	constructor() {
		const $wrapper = $( '<div>' )
			.addClass( 'mw-mmv-wrapper' );
		super( $wrapper );
		this.$wrapper = $wrapper;

		// When opening we might override the theme-color, so remember the original value
		const metaElement = document.querySelector( 'meta[name="theme-color"]' );
		this.originalThemeColor = metaElement ? metaElement.getAttribute( 'content' ) : null;

		/**
		 * @property {ThumbnailWidthCalculator}
		 * @private
		 */
		this.thumbnailWidthCalculator = new ThumbnailWidthCalculator();

		this.$main = $( '<div>' )
			.addClass( 'mw-mmv-main' );

		// I blame CSS for this
		this.$innerWrapper = $( '<div>' )
			.addClass( 'mw-mmv-image-inner-wrapper' );

		this.$imageWrapper = $( '<div>' )
			.addClass( 'mw-mmv-image-wrapper' )
			.append( this.$innerWrapper );

		this.$preDiv = $( '<div>' )
			// The overlay has no-invert, so the interface overlaid
			// on it must also have no-invert
			.addClass( 'mw-mmv-pre-image mw-no-invert' );

		this.$postDiv = $( '<div>' )
			.addClass( 'mw-mmv-post-image' );

		this.$aboveFold = $( '<div>' )
			.addClass( 'mw-mmv-above-fold' );

		this.$main.append(
			this.$preDiv,
			this.$imageWrapper,
			this.$postDiv
		);

		this.$wrapper.append(
			this.$main
		);

		this.setupCanvasButtons();

		this.panel = new MetadataPanel( this.$postDiv, this.$aboveFold );
		this.buttons = new CanvasButtons( this.$preDiv, this.$closeButton, this.$fullscreenButton );
		this.canvas = new Canvas( this.$innerWrapper, this.$imageWrapper, this.$wrapper );

		/** @property {DialogProxy|ReuseDialog} */
		this.fileReuse = new DialogProxy( 'mmv-reuse-open', ( req ) => {
			const { ReuseDialog } = req( 'mmv.ui.reuse' );
			this.fileReuse = new ReuseDialog( this.$preDiv, this.buttons.$download );
			return this.fileReuse;
		} );
		/** @property {DialogProxy|DownloadDialog} */
		this.downloadDialog = new DialogProxy( 'mmv-download-open', ( req ) => {
			const { DownloadDialog } = req( 'mmv.ui.reuse' );
			this.downloadDialog = new DownloadDialog( this.$preDiv, this.buttons.$download );
			return this.downloadDialog;
		} );
	}

	/**
	 * Sets up the file reuse data in the DOM
	 *
	 * @param {ImageModel} image
	 * @param {string} caption
	 * @param {string} alt
	 */
	setFileReuseData( image, caption, alt ) {
		this.buttons.set( image );
		this.fileReuse.set( image, caption, alt );
		this.downloadDialog.set( image );
	}

	/**
	 * Empties the interface.
	 */
	empty() {
		this.panel.empty();
		this.canvas.empty();
		this.buttons.empty();

		this.$main.addClass( 'metadata-panel-is-closed' )
			.removeClass( 'metadata-panel-is-open' );
	}

	/**
	 * Opens the lightbox.
	 */
	open() {
		this.empty();
		this.attach();
	}

	/**
	 * Attaches the interface to the DOM.
	 *
	 * @param {string} [parentId] parent id where we want to attach the UI. Defaults to document
	 *  element, override is mainly used for testing.
	 */
	attach( parentId ) {
		// Advanced description needs to be below the fold when the lightbox opens
		// regardless of what the scroll value was prior to opening the lightbox
		// If the lightbox is already attached, it means we're doing prev/next, and
		// we should avoid scrolling the panel
		if ( !this.attached ) {
			$( window ).scrollTop( 0 );
		}

		// Make sure that the metadata is going to be at the bottom when it appears
		// 83 is the height of the top metadata area. Which can't be measured by
		// reading the DOM at this point of the execution, unfortunately
		this.$postDiv.css( 'top', `${ $( window ).height() - 83 }px` );

		// Re-appending the same content can have nasty side-effects
		// Such as the browser leaving fullscreen mode if the fullscreened element is part of it
		if ( this.currentlyAttached ) {
			return;
		}

		// Make sure devices set their theming to dark to match the background of the viewer
		this.setThemeColor( '#000000' );

		this.handleEvent( 'keyup', ( e ) => {
			if ( e.keyCode === 27 && !( e.altKey || e.ctrlKey || e.shiftKey || e.metaKey ) ) {
				// Escape button pressed
				this.unattach();
			}
		} );

		this.handleEvent( 'fullscreenchange.lip', () => {
			this.fullscreenChange();
		} );

		this.handleEvent( 'keydown', ( e ) => {
			this.keydown( e );
		} );

		this.handleEvent( 'touchstart', ( e ) => {
			this.touchTap( e );
		} );

		// mousemove generates a ton of events, which is why we throttle it
		this.handleEvent( 'mousemove.lip', mw.util.throttle( ( e ) => {
			this.mousemove( e );
		}, 100, true ) );

		this.buttons.connect( this, {
			next: [ 'emit', 'next' ],
			prev: [ 'emit', 'prev' ]
		} );

		const $parent = $( parentId || document.body );

		// Clean up fullscreen data left attached to the DOM
		this.$main.removeClass( 'jq-fullscreened' ).removeClass( 'user-inactive' );
		this.isFullscreen = false;

		$parent
			.append(
				this.$wrapper
			);
		this.currentlyAttached = true;

		this.panel.attach();

		this.canvas.attach();

		// cross-communication between panel and canvas, sort of
		this.$postDiv.on( 'mmv-metadata-open.lip', () => {
			this.$main.addClass( 'metadata-panel-is-open' )
				.removeClass( 'metadata-panel-is-closed' );
		} ).on( 'mmv-metadata-close.lip', () => {
			this.$main.removeClass( 'metadata-panel-is-open' )
				.addClass( 'metadata-panel-is-closed' );
		} );
		this.$wrapper.on( 'mmv-panel-close-area-click.lip', () => {
			this.panel.scroller.toggle( 'down' );
		} );

		this.buttons.attach();

		this.fileReuse.attach();
		this.downloadDialog.attach();

		this.attached = true;
	}

	/**
	 * Detaches the interface from the DOM.
	 *
	 * @fires MultimediaViewer#mmv-close
	 */
	unattach() {
		// We trigger this event on the document because unattach() can run
		// when the interface is unattached
		// We're calling this before cleaning up (below) the DOM, as that
		// appears to have an impact on automatic scroll restoration (which
		// might happen as a result of this being closed) in FF
		$( document ).trigger( $.Event( 'mmv-close' ) )
			.off( 'fullscreenchange.lip' );

		// Has to happen first so that the scroller can freeze with visible elements
		this.panel.unattach();

		this.$wrapper.detach();

		this.currentlyAttached = false;

		this.buttons.unattach();

		this.$postDiv.off( '.lip' );
		this.$wrapper.off( 'mmv-panel-close-area-click.lip' );

		this.fileReuse.unattach();
		this.fileReuse.closeDialog();

		this.downloadDialog.unattach();
		this.downloadDialog.closeDialog();

		// Canvas listens for events from dialogs, so should be unattached at the end
		this.canvas.unattach();

		this.clearEvents();

		this.buttons.disconnect( this, {
			next: [ 'emit', 'next' ],
			prev: [ 'emit', 'prev' ]
		} );

		this.setThemeColor( this.originalThemeColor );

		this.attached = false;
	}

	/**
	 * Exits fullscreen mode.
	 */
	exitFullscreen() {
		this.fullscreenButtonJustPressed = true;
		if ( this.$main.get( 0 ) === document.fullscreenElement ) {
			if ( document.exitFullscreen ) {
				document.exitFullscreen();
			}
		}
		this.isFullscreen = false;
		this.$main.removeClass( 'jq-fullscreened' );
		clearTimeout( this.interactionTimer );
		this.userActivity();
	}

	/**
	 * Enters fullscreen mode.
	 */
	enterFullscreen() {
		const el = this.$main.get( 0 );
		if ( el.requestFullscreen ) {
			el.requestFullscreen();
		}
		this.isFullscreen = true;
		this.$main.addClass( 'jq-fullscreened' );
		this.resetInteractionTimer();
		this.userInactive();
	}

	/**
	 * Interrupt and reset the 3sec delay to hide the controls
	 */
	resetInteractionTimer() {
		clearTimeout( this.interactionTimer );
		this.interactionTimer = setTimeout( () => {
			this.userInactive();
		}, 3000 );
	}

	/**
	 * In fullscreen, hide the mouse cursor and the controls
	 * Called from resetInteractionTimer()
	 */
	userInactive() {
		this.$main.addClass( 'user-inactive' );
	}

	/**
	 * In fullscreen, show the mouse cursor and the controls
	 * Call this after any interactivity
	 */
	userActivity() {
		this.$main.removeClass( 'user-inactive' );
	}

	/**
	 * Setup for canvas navigation buttons
	 */
	setupCanvasButtons() {
		this.$closeButton = $( '<button>' )
			.addClass( 'cdx-button cdx-button--icon-only mw-mmv-button mw-mmv-close' )
			.prop( 'title', mw.msg( 'multimediaviewer-close-popup-text' ) )
			.append( $( '<span>' ).addClass( 'mw-mmv-icon' ) )
			.on( 'click', () => {
				this.unattach();
			} );

		this.$fullscreenButton = $( '<button>' )
			.addClass( 'cdx-button cdx-button--icon-only mw-mmv-button mw-mmv-fullscreen' )
			.prop( 'title', mw.msg( 'multimediaviewer-fullscreen-popup-text' ) )
			.append( $( '<span>' ).addClass( 'mw-mmv-icon' ) )
			.on( 'click', () => {
				if ( this.isFullscreen ) {
					this.exitFullscreen();
				} else {
					this.enterFullscreen();
				}
			} );
	}

	/**
	 * Handle a fullscreen change event.
	 */
	fullscreenChange() {
		// eslint-disable-next-line compat/compat
		this.isFullscreen = !!document.fullscreenElement;
		if ( this.isFullscreen ) {
			this.$fullscreenButton
				.prop( 'title', mw.msg( 'multimediaviewer-defullscreen-popup-text' ) )
				.attr( 'alt', mw.msg( 'multimediaviewer-defullscreen-popup-text' ) );
		} else {
			this.$fullscreenButton
				.prop( 'title', mw.msg( 'multimediaviewer-fullscreen-popup-text' ) )
				.attr( 'alt', mw.msg( 'multimediaviewer-fullscreen-popup-text' ) );
		}

		if ( !this.fullscreenButtonJustPressed && !this.isFullscreen ) {
			// Close the interface all the way if the user pressed 'esc'
			this.unattach();
		} else if ( this.fullscreenButtonJustPressed ) {
			this.fullscreenButtonJustPressed = false;
		}

		// Fullscreen change events can happen after unattach(), in which
		// case we shouldn't do anything UI-related
		if ( !this.currentlyAttached ) {
			return;
		}

		if ( this.isFullscreen ) {
			this.userInactive();
		}

		// Some browsers only send resize events before toggling fullscreen, but not once the toggling is done
		// This makes sure that the UI is properly resized after a fullscreen change
		this.$main.trigger( $.Event( 'mmv-resize-end' ) );
	}

	/**
	 * Handles keydown events on the document
	 *
	 * @param {jQuery.Event} e The jQuery keypress event object
	 */
	keydown( e ) {
		const isRtl = $( document.body ).hasClass( 'rtl' );

		if ( e.altKey || e.shiftKey || e.ctrlKey || e.metaKey ) {
			return;
		}
		if ( e.key === 'ArrowLeft' ) {
			this.emit( isRtl ? 'next' : 'prev' );
			e.preventDefault();
		} else if ( e.key === 'ArrowRight' ) {
			this.emit( isRtl ? 'prev' : 'next' );
			e.preventDefault();
		} else if ( e.key === 'Home' ) {
			this.emit( 'first' );
			e.preventDefault();
		} else if ( e.key === 'End' ) {
			this.emit( 'last' );
			e.preventDefault();
		} else if ( this.isFullscreen ) {
			// Any other key in fullscreen reveals the controls
			this.resetInteractionTimer();
			this.userActivity();
		}
	}

	/**
	 * Handles mousemove events on the document
	 *
	 * @param {jQuery.Event} e The mousemove event object
	 */
	mousemove( e ) {
		// T77869 ignore fake mousemove events triggered by Chrome
		if (
			e &&
			e.originalEvent &&
			e.originalEvent.movementX === 0 &&
			e.originalEvent.movementY === 0
		) {
			return;
		}

		if ( this.isFullscreen ) {
			this.resetInteractionTimer();
			this.userActivity();
		}
	}

	touchTap() {
		if ( this.isFullscreen ) {
			this.resetInteractionTimer();
			this.userActivity();
		}
	}

	/**
	 * Updates the next and prev buttons
	 *
	 * @param {boolean} showPrevNext Show prev/next button
	 */
	updateControls( showPrevNext ) {
		const prevNextTop = `${ ( this.$imageWrapper.height() - 60 ) / 2 }px`;

		if ( this.isFullscreen ) {
			this.$postDiv.css( 'top', '' );
		} else {
			this.$postDiv.css( 'top', this.$imageWrapper.height() );
		}

		this.buttons.setOffset( prevNextTop );
		this.buttons.$nav.toggle( showPrevNext );
	}

	/**
	 * Update the theme-color of the document
	 *
	 * @param {string|null} color to set as theme-color or null to remove the theme-color
	 */
	setThemeColor( color ) {
		let metaElement = document.querySelector( 'meta[name="theme-color"]' );
		if ( !metaElement ) {
			metaElement = document.createElement( 'meta' );
			metaElement.setAttribute( 'name', 'theme-color' );
			document.head.appendChild( metaElement );
		}
		if ( color === null ) {
			metaElement.remove();
		} else {
			this.originalThemeColor = metaElement.getAttribute( 'content' );
			metaElement.setAttribute( 'content', color );
		}
	}
}

module.exports = LightboxInterface;
