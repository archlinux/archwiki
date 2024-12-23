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
const HtmlUtils = require( './mmv.HtmlUtils.js' );
const ViewLogger = require( './logging/mmv.logging.ViewLogger.js' );
const Api = require( './provider/mmv.provider.Api.js' );
const GuessedThumbnailInfo = require( './provider/mmv.provider.GuessedThumbnailInfo.js' );
const ImageProvider = require( './provider/mmv.provider.Image.js' );
const ImageInfo = require( './provider/mmv.provider.ImageInfo.js' );
const ThumbnailInfo = require( './provider/mmv.provider.ThumbnailInfo.js' );
const ImageModel = require( './model/mmv.model.Image.js' );
const License = require( './model/mmv.model.License.js' );
const TaskQueue = require( './model/mmv.model.TaskQueue.js' );
const Thumbnail = require( './model/mmv.model.Thumbnail.js' );
const ThumbnailWidth = require( './model/mmv.model.ThumbnailWidth.js' );
const Canvas = require( './ui/mmv.ui.canvas.js' );
const CanvasButtons = require( './ui/mmv.ui.canvasButtons.js' );
const Description = require( './ui/mmv.ui.description.js' );
const Dialog = require( './ui/mmv.ui.dialog.js' );
const UiElement = require( './ui/mmv.ui.js' );
const MetadataPanel = require( './ui/mmv.ui.metadataPanel.js' );
const MetadataPanelScroller = require( './ui/mmv.ui.metadataPanelScroller.js' );
const Permission = require( './ui/mmv.ui.permission.js' );
const ProgressBar = require( './ui/mmv.ui.progressBar.js' );
const StripeButtons = require( './ui/mmv.ui.stripeButtons.js' );
const TruncatableTextField = require( './ui/mmv.ui.truncatableTextField.js' );
const LightboxInterface = require( './mmv.lightboxinterface.js' );
const ThumbnailWidthCalculator = require( './mmv.ThumbnailWidthCalculator.js' );
const { extensions, useThumbnailGuessing } = require( './config.json' );

const router = require( 'mediawiki.router' );
let comingFromHashChange = false;

/**
 * Analyses the page, looks for image content and sets up the hooks
 * to manage the viewing experience of such content.
 */
class MultimediaViewer {
	constructor() {
		const apiCacheMaxAge = 86400; // one day (24 hours * 60 min * 60 sec)
		const apiCacheFiveMinutes = 300; // 5 min * 60 sec
		const api = new mw.Api();

		/**
		 * @property {ImageProvider}
		 * @private
		 */
		this.imageProvider = new ImageProvider();

		/**
		 * @property {ImageInfo}
		 * @private
		 */
		this.imageInfoProvider = new ImageInfo( api, {
			language: Config.language(),
			maxage: apiCacheFiveMinutes
		} );

		/**
		 * @property {ThumbnailInfo}
		 * @private
		 */
		this.thumbnailInfoProvider = new ThumbnailInfo( api,
			{ maxage: apiCacheMaxAge } );

		/**
		 * @property {ThumbnailInfo}
		 * @private
		 */
		this.guessedThumbnailInfoProvider = new GuessedThumbnailInfo();

		/**
		 * Image index on page.
		 *
		 * @property {number}
		 */
		this.currentIndex = 0;

		/**
		 * @property {OO.Router} router
		 */
		this.router = router;
		this.setupRouter();
		comingFromHashChange = false;

		/**
		 * UI object used to display the pictures in the page.
		 *
		 * @property {LightboxInterface}
		 * @private
		 */
		this.ui = new LightboxInterface();

		/** @property {string} documentTitle base document title, MediaViewer will expand this */
		this.documentTitle = document.title;

		/**
		 * @property {ViewLogger} view -
		 */
		this.viewLogger = new ViewLogger( window );

		/**
		 * Stores whether the real image was loaded and displayed already.
		 * This is reset when paging, so it is not necessarily accurate.
		 *
		 * @property {boolean}
		 */
		this.realThumbnailShown = false;
	}

	/**
	 * Initialize the lightbox interface given an array of thumbnail
	 * objects.
	 *
	 * @param {LightboxImage[]} thumbs
	 */
	initWithThumbs( thumbs ) {
		this.thumbs = thumbs;
	}

	/**
	 * Handles resize events in viewer.
	 */
	resize() {
		const image = this.thumbs[ this.currentIndex ];
		const ext = this.thumbs[ this.currentIndex ].filePageTitle.getExtension().toLowerCase();

		this.preloadThumbnails();

		if ( image ) {
			const imageWidths = this.ui.canvas.getCurrentImageWidths();

			this.fetchThumbnailForLightboxImage(
				image, imageWidths.real
			).then( ( thumbnail, image2 ) => {
				// eslint-disable-next-line mediawiki/class-doc
				image2.className = ext;
				this.setImage( thumbnail, image2, imageWidths );
			}, ( error ) => {
				this.ui.canvas.showError( error );
			} );
		}

		this.ui.updateControls( this.thumbs.length > 1 );
	}

	/**
	 * Loads and sets the specified image. It also updates the controls.
	 *
	 * @param {Thumbnail} thumbnail thumbnail information
	 * @param {HTMLImageElement} imageElement
	 * @param {ThumbnailWidth} imageWidths
	 */
	setImage( thumbnail, imageElement, imageWidths ) {
		this.ui.canvas.setImageAndMaxDimensions( thumbnail, imageElement, imageWidths );
		this.ui.updateControls( this.thumbs.length > 1 );
	}

	/**
	 * Loads a specified image.
	 *
	 * @param {LightboxImage} image
	 */
	loadImage( image ) {
		const $initialImage = $( image.thumbnail ).clone();

		const pluginsPromise = this.loadExtensionPlugins( image.filePageTitle.getExtension().toLowerCase() );

		this.currentIndex = image.index;

		this.currentImage = image;

		if ( !this.isOpen ) {
			$( document ).trigger( $.Event( 'mmv-setup-overlay' ) );
			this.ui.open();
			this.isOpen = true;
		} else {
			this.ui.empty();
		}

		this.setTitle();

		// At this point we can't show the thumbnail because we don't
		// know what size it should be. We still assign it to allow for
		// size calculations in getCurrentImageWidths, which needs to know
		// the aspect ratio
		// eslint-disable-next-line mediawiki/class-doc
		$initialImage.hide()
			.removeAttr( 'style' )
			.removeClass()
			.addClass( `mw-mmv-placeholder-image ${ image.filePageTitle.getExtension().toLowerCase() }` );

		this.ui.canvas.set( image, $initialImage );

		this.preloadImagesMetadata();
		this.preloadThumbnails();
		const imageWidths = this.ui.canvas.getCurrentImageWidths();

		const imagePromise = this.fetchThumbnailForLightboxImage( image, imageWidths.real );

		if ( imagePromise.state() === 'pending' ) {
			this.displayPlaceholderThumbnail( image, $initialImage, imageWidths );
		}
		this.resetThumbnailStates();
		this.setupProgressBar( image, imagePromise, imageWidths.real );

		const metadataPromise = this.fetchSizeIndependentLightboxInfo( image.filePageTitle );

		imagePromise.then(
			// done
			( thumbnail, imageElement ) => {
				if ( this.currentIndex !== image.index ) {
					return;
				}

				// eslint-disable-next-line mediawiki/class-doc
				imageElement.className = `mw-mmv-final-image ${ image.filePageTitle.getExtension().toLowerCase() }`;
				imageElement.alt = image.alt;

				$.when( metadataPromise, pluginsPromise ).then( ( imageInfo ) => {
					$( document ).trigger( $.Event( 'mmv-metadata', { viewer: this, image, imageInfo } ) );
				} );

				this.displayRealThumbnail( thumbnail, imageElement, imageWidths );
			},
			// fail
			( error ) => {
				this.ui.canvas.showError( error );
				return $.Deferred().reject( error );
			}
		);

		metadataPromise.then(
			// done
			( imageInfo ) => {
				if ( this.currentIndex !== image.index ) {
					return;
				}

				this.ui.panel.setImageInfo( image, imageInfo );

				// File reuse steals a bunch of information from the DOM, so do it last
				this.ui.setFileReuseData( imageInfo, image.caption, image.alt );
			},
			// fail
			( error ) => {
				if ( this.currentIndex === image.index ) {
					// Set title to caption or file name if caption is not available;
					// see setTitle() in mmv.ui.metadataPanel for extended caption fallback
					this.ui.panel.showError( image.caption ?
						HtmlUtils.htmlToTextWithTags( image.caption ) :
						image.filePageTitle.getNameText(), error );
				}

				return $.Deferred().reject( error );
			}
		);

		$.when( imagePromise, metadataPromise ).then( () => {
			if ( this.currentIndex !== image.index ) {
				return;
			}

			this.ui.panel.scroller.animateMetadataOnce();
		} );
	}

	/**
	 * Loads an image by its title
	 *
	 * @param {mw.Title} title
	 * @param {number} [position]
	 */
	loadImageByTitle( title, position ) {
		if ( !this.thumbs || !this.thumbs.length ) {
			return;
		}

		const thumb = this.thumbs.find( ( t ) => t.filePageTitle.getPrefixedText() === title.getPrefixedText() &&
			( !position || t.position === position )
		);

		if ( !thumb ) {
			this.onTitleNotFound( title );
			return;
		}

		this.loadImage( thumb );
	}

	/**
	 * When the image to load is not present on the current page,
	 * a notification is shown to the user and the MMV is closed.
	 *
	 * @param {mw.Title} title
	 * @private
	 */
	onTitleNotFound( title ) {
		this.close();
		const text = mw.msg( 'multimediaviewer-file-not-found-error', title.getMainText() );
		const $link = $( '<a>' ).text( mw.msg( 'multimediaviewer-file-page' ) ).prop( 'href', title.getUrl() );
		const $message = $( '<div>' ).text( text ).append( $( '<br>' ) ).append( $link );
		mw.notify( $message );
	}

	/**
	 * Resets the cross-request states needed to handle the thumbnail logic.
	 */
	resetThumbnailStates() {
		this.realThumbnailShown = false;
	}

	/**
	 * Display the real, full-resolution, thumbnail that was fetched with fetchThumbnail
	 *
	 * @param {Thumbnail} thumbnail
	 * @param {HTMLImageElement} imageElement
	 * @param {ThumbnailWidth} imageWidths
	 */
	displayRealThumbnail( thumbnail, imageElement, imageWidths ) {
		this.realThumbnailShown = true;
		this.setImage( thumbnail, imageElement, imageWidths );
		this.viewLogger.attach( thumbnail.url );
	}

	/**
	 * Display the thumbnail from the page
	 *
	 * @param {LightboxImage} image
	 * @param {jQuery} $initialImage The thumbnail from the page
	 * @param {ThumbnailWidth} imageWidths
	 * @param {boolean} [recursion=false] for internal use, never set this when calling from outside
	 */
	displayPlaceholderThumbnail( image, $initialImage, imageWidths, recursion ) {
		const size = { width: image.originalWidth, height: image.originalHeight };

		// If the actual image has already been displayed, there's no point showing the thumbnail.
		// This can happen if the API request to get the original image size needed to show the
		// placeholder thumbnail takes longer then loading the actual thumbnail.
		if ( this.realThumbnailShown ) {
			return;
		}

		// Width/height of the original image are added to the HTML by MediaViewer via a PHP hook,
		// and can be missing in exotic circumstances, e. g. when the extension has only been
		// enabled recently and the HTML cache has not cleared yet. If that is the case, we need
		// to fetch the size from the API first.
		if ( !size.width || !size.height ) {
			if ( recursion ) {
				// this should not be possible, but an infinite recursion is nasty
				// business, so we make a sense check
				throw new Error( 'MediaViewer internal error: displayPlaceholderThumbnail recursion' );
			}
			this.imageInfoProvider.get( image.filePageTitle ).done( ( imageInfo ) => {
				// Make sure the user has not navigated away while we were waiting for the size
				if ( this.currentIndex === image.index ) {
					image.originalWidth = imageInfo.width;
					image.originalHeight = imageInfo.height;
					this.displayPlaceholderThumbnail( image, $initialImage, imageWidths, true );
				}
			} );
		} else {
			this.ui.canvas.maybeDisplayPlaceholder( size, $initialImage, imageWidths );
		}
	}

	/**
	 * Displays a progress bar for the image loading, if necessary, and sets up handling of
	 * all the related callbacks.
	 *
	 * @param {LightboxImage} image
	 * @param {jQuery.Promise.<Thumbnail, HTMLImageElement>} imagePromise
	 * @param {number} imageWidth needed for caching progress (FIXME)
	 */
	setupProgressBar( image, imagePromise, imageWidth ) {
		const progressBar = this.ui.panel.progressBar;
		const key = `${ image.filePageTitle.getPrefixedDb() }|${ imageWidth }`;

		if ( !this.progressCache[ key ] ) {
			// Animate progress bar to 5 to give a sense that something is happening, and make sure
			// the progress bar is noticeable, even if we're sitting at 0% stuck waiting for
			// server-side processing, such as thumbnail (re)generation
			progressBar.jumpTo( 0 );
			progressBar.animateTo( 5 );
			this.progressCache[ key ] = 5;
		} else {
			progressBar.jumpTo( this.progressCache[ key ] );
		}

		// FIXME would be nice to have a "filtered" promise which does not fire when the image is not visible
		imagePromise.then(
			// done
			( thumbnail, imageElement ) => {
				this.progressCache[ key ] = 100;
				if ( this.currentIndex === image.index ) {
					// Fallback in case the browser doesn't have fancy progress updates
					progressBar.animateTo( 100 );

					// Hide progress bar, we're done
					// TODO not really needed, but testcase depends on it
					progressBar.hide();
				}

				return $.Deferred().resolve( thumbnail, imageElement );
			},
			// fail
			( error ) => {
				this.progressCache[ key ] = 100;

				if ( this.currentIndex === image.index ) {
					// Hide progress bar on error
					progressBar.hide();
				}

				return $.Deferred().reject( error );
			},
			// progress
			( progress ) => {
				// We pretend progress is always at least 5%, so progress events below 5% should be ignored
				// 100 will be handled by the done handler, do not mix two animations
				if ( progress >= 5 && progress < 100 ) {
					this.progressCache[ key ] = progress;

					// Touch the UI only if the user is looking at this image
					if ( this.currentIndex === image.index ) {
						progressBar.animateTo( progress );
					}
				}

				return progress;
			}
		);
	}

	/**
	 * Orders lightboximage indexes for preloading. Works similar to $.each, except it only takes
	 * the callback argument. Calls the callback with each lightboximage index in some sequence
	 * that is ideal for preloading.
	 *
	 * @private
	 * @param {function(number, LightboxImage)} callback
	 */
	eachPreloadableLightboxIndex( callback ) {
		for ( let i = 0; i <= this.preloadDistance; i++ ) {
			if ( this.currentIndex + i < this.thumbs.length ) {
				callback(
					this.currentIndex + i,
					this.thumbs[ this.currentIndex + i ]
				);
			}
			if ( i && this.currentIndex - i >= 0 ) { // skip duplicate for i==0
				callback(
					this.currentIndex - i,
					this.thumbs[ this.currentIndex - i ]
				);
			}
		}
	}

	/**
	 * A helper function to fill up the preload queues.
	 * taskFactory(lightboxImage) should return a preload task for the given lightboximage.
	 *
	 * @private
	 * @param {function(LightboxImage)} taskFactory
	 * @return {TaskQueue}
	 */
	pushLightboxImagesIntoQueue( taskFactory ) {
		const queue = new TaskQueue();

		this.eachPreloadableLightboxIndex( ( i, lightboxImage ) => {
			queue.push( taskFactory( lightboxImage ) );
		} );

		return queue;
	}

	/**
	 * Cancels in-progress image metadata preloading.
	 */
	cancelImageMetadataPreloading() {
		if ( this.metadataPreloadQueue ) {
			this.metadataPreloadQueue.cancel();
		}
	}

	/**
	 * Cancels in-progress image thumbnail preloading.
	 */
	cancelThumbnailsPreloading() {
		if ( this.thumbnailPreloadQueue ) {
			this.thumbnailPreloadQueue.cancel();
		}
	}

	/**
	 * Preload metadata for next and prev N image (N = MMVP.preloadDistance).
	 * Two images will be loaded at a time (one forward, one backward), with closer images
	 * being loaded sooner.
	 */
	preloadImagesMetadata() {
		this.cancelImageMetadataPreloading();

		this.metadataPreloadQueue = this.pushLightboxImagesIntoQueue( ( lightboxImage ) => () => this.fetchSizeIndependentLightboxInfo( lightboxImage.filePageTitle ) );

		this.metadataPreloadQueue.execute();
	}

	/**
	 * Preload thumbnails for next and prev N image (N = MMVP.preloadDistance).
	 * Two images will be loaded at a time (one forward, one backward), with closer images
	 * being loaded sooner.
	 */
	preloadThumbnails() {
		this.cancelThumbnailsPreloading();

		this.thumbnailPreloadQueue = this.pushLightboxImagesIntoQueue( ( lightboxImage ) => () => {
			// viewer.ui.canvas.getLightboxImageWidths needs the viewer to be open
			// because it needs to read the size of visible elements
			if ( !this.isOpen ) {
				return;
			}

			const imageWidths = this.ui.canvas.getLightboxImageWidths( lightboxImage );

			return this.fetchThumbnailForLightboxImage( lightboxImage, imageWidths.real );
		} );

		this.thumbnailPreloadQueue.execute();
	}

	/**
	 * Loads all the size-independent information needed by the lightbox (image metadata, repo
	 * information).
	 *
	 * @param {mw.Title} fileTitle Title of the file page for the image.
	 * @return {jQuery.Promise.<ImageModel>}
	 */
	fetchSizeIndependentLightboxInfo( fileTitle ) {
		return this.imageInfoProvider.get( fileTitle );
	}

	/**
	 * Loads size-dependent components of a lightbox - the thumbnail model and the image itself.
	 *
	 * @param {LightboxImage} image
	 * @param {number} width the width of the requested thumbnail
	 * @return {jQuery.Promise.<Thumbnail, HTMLImageElement>}
	 */
	fetchThumbnailForLightboxImage( image, width ) {
		return this.fetchThumbnail(
			image.filePageTitle,
			width,
			image.src,
			image.originalWidth,
			image.originalHeight
		);
	}

	/**
	 * Loads size-dependent components of a lightbox - the thumbnail model and the image itself.
	 *
	 * @param {mw.Title} fileTitle
	 * @param {number} width the width of the requested thumbnail
	 * @param {string} [sampleUrl] a thumbnail URL for the same file (but with different size) (might be missing)
	 * @param {number} [originalWidth] the width of the original, full-sized file (might be missing)
	 * @param {number} [originalHeight] the height of the original, full-sized file (might be missing)
	 * @param {boolean} [useThumbnailGuessing0] the useThumbnailGuessing flag
	 * @return {jQuery.Promise.<Thumbnail, HTMLImageElement>} A promise resolving to
	 *  a thumbnail model and an <img> element. It might or might not have progress events which
	 *  return a single number.
	 */
	fetchThumbnail( fileTitle, width, sampleUrl, originalWidth, originalHeight, useThumbnailGuessing0 = useThumbnailGuessing ) {
		let guessing = false;
		const combinedDeferred = $.Deferred();
		let thumbnailPromise;
		let imagePromise;

		if ( fileTitle.getExtension().toLowerCase() !== 'svg' && originalWidth && width > originalWidth ) {
			// Do not request images larger than the original image
			width = originalWidth;
		}

		if ( sampleUrl && originalWidth && originalHeight && useThumbnailGuessing0 ) {
			guessing = true;
			thumbnailPromise = this.guessedThumbnailInfoProvider.get(
				fileTitle, sampleUrl, width, originalWidth, originalHeight
			).then( null, () => this.thumbnailInfoProvider.get( fileTitle, sampleUrl, width ) );
		} else {
			thumbnailPromise = this.thumbnailInfoProvider.get( fileTitle, sampleUrl, width );
		}

		imagePromise = thumbnailPromise.then( ( thumbnail ) => this.imageProvider.get( thumbnail.url ) );

		if ( guessing ) {
			// If we guessed wrong, need to retry with real URL on failure.
			// As a side effect this introduces an extra (harmless) retry of a failed thumbnailInfoProvider.get call
			// because thumbnailInfoProvider.get is already called above when guessedThumbnailInfoProvider.get fails.
			imagePromise = imagePromise
				.then( null, () => this.thumbnailInfoProvider.get( fileTitle, sampleUrl, width )
					.then( ( thumbnail ) => this.imageProvider.get( thumbnail.url ) ) );
		}

		// In jQuery<3, $.when used to also relay notify, but that is no longer
		// the case - but we still want to pass it along...
		$.when( thumbnailPromise, imagePromise ).then( combinedDeferred.resolve, combinedDeferred.reject );
		imagePromise.then( null, null, ( arg, progress ) => {
			combinedDeferred.notify( progress );
		} );
		return combinedDeferred;
	}

	/**
	 * Loads an image at a specified index in the viewer's thumbnail array.
	 *
	 * @param {number} index
	 */
	loadIndex( index ) {
		if ( index < this.thumbs.length && index >= 0 ) {
			this.viewLogger.recordViewDuration();

			const thumb = this.thumbs[ index ];
			this.loadImage( thumb );
			router.navigateTo( null, {
				path: Config.getMediaHash( thumb.filePageTitle, thumb.position ),
				useReplaceState: true
			} );
		}
	}

	/**
	 * Opens the last image
	 */
	firstImage() {
		this.loadIndex( 0 );
	}

	/**
	 * Opens the last image
	 */
	lastImage() {
		this.loadIndex( this.thumbs.length - 1 );
	}

	/**
	 * Opens the next image
	 */
	nextImage() {
		if ( this.currentIndex === this.thumbs.length - 1 ) {
			this.firstImage();
		} else {
			this.loadIndex( this.currentIndex + 1 );
		}
	}

	/**
	 * Opens the previous image
	 */
	prevImage() {
		if ( this.currentIndex === 0 ) {
			this.lastImage();
		} else {
			this.loadIndex( this.currentIndex - 1 );
		}
	}

	/**
	 * Handles close event coming from the lightbox
	 */
	close() {
		this.viewLogger.recordViewDuration();
		this.viewLogger.unattach();

		if ( comingFromHashChange ) {
			comingFromHashChange = false;
		} else {
			this.router.back();
		}
		// update title after route change, see T225387
		document.title = this.createDocumentTitle( null );

		// This has to happen after the hash reset, because setting the hash to # will reset the page scroll
		$( document ).trigger( $.Event( 'mmv-cleanup-overlay' ) );

		this.isOpen = false;
	}

	/**
	 * Sets up the route handlers
	 */
	setupRouter() {
		// handle empty hashes, and anchor links (page sections, possibly including /)
		this.router.addRoute( /.*$/, () => {
			if ( this.isOpen ) {
				comingFromHashChange = true;
				document.title = this.createDocumentTitle( null );
				if ( this.ui ) {
					// FIXME triggers mmv-close event, which calls viewer.close()
					this.ui.unattach();
				} else {
					this.close();
				}
			}
		} );
	}

	/**
	 * Updates the page title to reflect the current title.
	 */
	setTitle() {
		// update title after route change, see T225387
		document.title = this.createDocumentTitle( this.currentImage && this.currentImage.filePageTitle );
	}

	/**
	 * Creates a string which can be shown as document title (the text at the top of the browser window).
	 *
	 * @param {mw.Title|null} imageTitle the title object for the image which is displayed; null when the
	 *  viewer is being closed
	 * @return {string}
	 */
	createDocumentTitle( imageTitle ) {
		if ( imageTitle ) {
			return `${ imageTitle.getNameText() } - ${ this.documentTitle }`;
		} else {
			return this.documentTitle;
		}
	}

	/**
	 * Fired when the viewer is closed. This is used by the lightbox to notify the main app.
	 *
	 * @event MultimediaViewer#mmv-close
	 */

	/**
	 * Fired when the user requests the next image.
	 *
	 * @event MultimediaViewer#mmv-next
	 */

	/**
	 * Fired when the user requests the previous image.
	 *
	 * @event MultimediaViewer#mmv-prev
	 */

	/**
	 * Fired when the screen size changes. Debounced to avoid continuous triggering while resizing with a mouse.
	 *
	 * @event MultimediaViewer#mmv-resize-end
	 */

	/**
	 * Used by components to request a thumbnail URL for the current thumbnail, with a given size.
	 *
	 * @event MultimediaViewer#mmv-request-thumbnail
	 * @param {number} size
	 */

	/**
	 * Registers all event handlers
	 */
	setupEventHandlers() {
		this.ui.connect( this, {
			first: 'firstImage',
			last: 'lastImage',
			next: 'nextImage',
			prev: 'prevImage'
		} );

		$( document ).on( 'mmv-close.mmvp', () => {
			this.close();
		} ).on( 'mmv-resize-end.mmvp', () => {
			this.resize();
		} ).on( 'mmv-request-thumbnail.mmvp', ( e, size ) => {
			if ( this.currentImage && this.currentImage.filePageTitle ) {
				return this.thumbnailInfoProvider.get( this.currentImage.filePageTitle, this.currentImage.src, size );
			} else {
				return $.Deferred().reject();
			}
		} ).on( 'mmv-viewfile.mmvp', () => {
			this.imageInfoProvider.get( this.currentImage.filePageTitle ).done( ( imageInfo ) => {
				document.location = imageInfo.url;
			} );
		} );
	}

	/**
	 * Unregisters all event handlers. Currently only used in tests.
	 */
	cleanupEventHandlers() {
		$( document ).off( 'mmv-close.mmvp mmv-resize-end.mmvp' );

		this.ui.disconnect( this );
	}

	/**
	 * Loads the RL module defined for a given file extension, if any
	 *
	 * @param {string} extension File extension
	 * @return {jQuery.Promise}
	 */
	loadExtensionPlugins( extension ) {
		const deferred = $.Deferred();

		if ( !( extension in extensions ) || extensions[ extension ] === 'default' ) {
			return deferred.resolve();
		}

		mw.loader.using( extensions[ extension ], () => {
			deferred.resolve();
		} );

		return deferred;
	}
}

/**
 * Image loading progress. Keyed by image (database) name + '|' + thumbnail width in pixels,
 * value is a number between 0-100.
 *
 * @private
 * @property {Object.<string, number>}
 */
MultimediaViewer.prototype.progressCache = {};

/**
 * Preload this many prev/next images to speed up navigation.
 * (E.g. preloadDistance = 3 means that the previous 3 and the next 3 images will be loaded.)
 * Preloading only happens when the viewer is open.
 *
 * @property {number}
 */
MultimediaViewer.prototype.preloadDistance = 1;

/**
 * Stores image metadata preloads, so they can be cancelled.
 *
 * @property {TaskQueue}
 */
MultimediaViewer.prototype.metadataPreloadQueue = null;

/**
 * Stores image thumbnail preloads, so they can be cancelled.
 *
 * @property {TaskQueue}
 */
MultimediaViewer.prototype.thumbnailPreloadQueue = null;

module.exports = {
	Api,
	Canvas,
	CanvasButtons,
	Description,
	Dialog,
	GuessedThumbnailInfo,
	HtmlUtils,
	ImageInfo,
	ImageModel,
	ImageProvider,
	License,
	LightboxInterface,
	MetadataPanel,
	MetadataPanelScroller,
	MultimediaViewer,
	Permission,
	ProgressBar,
	StripeButtons,
	TaskQueue,
	Thumbnail,
	ThumbnailInfo,
	ThumbnailWidth,
	ThumbnailWidthCalculator,
	TruncatableTextField,
	UiElement,
	ViewLogger
};
