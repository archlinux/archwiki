const { createMwApp, ref } = require( 'vue' );
const App = require( './App.vue' );
const { Config } = require( 'mmv.bootstrap' );
const { ImageInfo } = require( 'mmv.common' );
const { getLargerThumbnailUrl } = require( './thumbnailGuessing.js' );

/** @typedef {import('./types').LightboxImage} LightboxImage */
/** @typedef {import('./types').ImageModel} ImageModel */
/** @typedef {import('./types').ViewerState} ViewerState */

/**
 * Beta image viewer using Vue 3 and Codex.
 * Implements the interface expected by MultimediaViewerBootstrap so it can
 * be used as a drop-in replacement for the legacy MultimediaViewer class.
 */
class BetaViewer {
	constructor() {
		/** @property {boolean} */
		this.isOpen = false;

		/** @property {boolean} Set by bootstrap during hash-driven navigation */
		this.comingFromHashChange = false;

		/** @property {string} Original document title, restored on close */
		this.documentTitle = document.title;

		/** @property {LightboxImage[]} LightboxImage objects for the current page */
		this.thumbs = [];

		/** @property {LightboxImage|null} The currently displayed LightboxImage */
		this.currentImage = null;

		/** @property {Object|null} The mounted Vue application instance */
		this.app = null;

		/** @property {HTMLElement|null} The mount point element */
		this.mountEl = null;

		/** @property {ImageInfo|null} Lazily created provider (caches responses) */
		this.imageInfoProvider = null;

		// Reactive state shared with the Vue app via provide/inject.
		// We use Vue.ref() so that changes here propagate to the template.
		/** @type {ViewerState} */
		this.state = {
			image: ref( null ),
			imageInfo: ref( null ),
			displayUrl: ref( '' ),
			thumbs: ref( [] ),
			isOpen: ref( false ),
			chromeVisible: ref( true )
		};

		// Provide a ui object with unattach() so bootstrap can close
		// the viewer via viewer.ui.unattach() on non-MMV hash changes.
		this.ui = {
			unattach: () => {
				this.close();
			}
		};
	}

	/**
	 * Called by bootstrap after instantiation.
	 * Sets up keyboard and document-level event handlers.
	 */
	setupEventHandlers() {
		this.onKeyDown = ( e ) => {
			if ( !this.isOpen ) {
				return;
			}

			if ( e.key === 'Escape' ) {
				this.close();
			} else if ( e.key === 'ArrowLeft' ) {
				this.prevImage();
			} else if ( e.key === 'ArrowRight' ) {
				this.nextImage();
			}
		};

		document.addEventListener( 'keydown', this.onKeyDown );
	}

	/**
	 * Called by bootstrap with all the LightboxImage objects found on the page.
	 *
	 * @param {LightboxImage[]} thumbs
	 */
	initWithThumbs( thumbs ) {
		this.thumbs = thumbs;
		this.state.thumbs.value = thumbs;
	}

	/**
	 * Load and display an image by its file title.
	 * Called by bootstrap.route() when navigating via hash.
	 *
	 * @param {mw.Title} fileTitle
	 * @param {number} [position]
	 */
	loadImageByTitle( fileTitle, position ) {
		const prefixed = fileTitle.getPrefixedText();
		const matches = this.thumbs.filter(
			( t ) => t.filePageTitle.getPrefixedText() === prefixed
		);

		let image;

		if ( position && matches.length >= position ) {
			image = matches[ position - 1 ];
		} else {
			image = matches[ 0 ];
		}
		if ( image ) {
			this.loadImage( image );
		}
	}

	/**
	 * Display the given LightboxImage.
	 *
	 * @param {LightboxImage} image
	 */
	loadImage( image ) {
		this.currentImage = image;
		this.open();

		if ( !this.imageInfoProvider ) {
			this.imageInfoProvider = new ImageInfo(
				new mw.Api(),
				{ language: mw.config.get( 'wgUserLanguage' ) }
			);
		}

		const infoPromise = this.imageInfoProvider.get( image.filePageTitle );
		const thumbPromise = this.loadLargerThumbnail( image );

		Promise.all( [ infoPromise, thumbPromise ] ).then( ( [ imageData, largerUrl ] ) => {
			if ( this.currentImage === image ) {
				this.state.imageInfo.value = imageData;
				this.state.displayUrl.value = largerUrl || image.src;
				this.state.image.value = image;
			}
		} );

		this.prefetchAdjacent( image );
	}

	/**
	 * Preload a larger thumbnail for the given image.
	 *
	 * @param {LightboxImage} image
	 * @return {Promise<string|null>} Resolves with the URL, or null on failure
	 */
	loadLargerThumbnail( image ) {
		const url = getLargerThumbnailUrl( image );

		if ( !url ) {
			return Promise.resolve( null );
		}

		return new Promise( ( resolve ) => {
			const loader = new Image();
			loader.onload = () => resolve( url );
			loader.onerror = () => resolve( null );
			loader.src = url;
		} );
	}

	/**
	 * Prefetch thumbnails and imageInfo for the adjacent images so
	 * navigation feels instant.
	 *
	 * @param {LightboxImage} image The currently displayed image
	 */
	prefetchAdjacent( image ) {
		if ( this.thumbs.length <= 1 ) {
			return;
		}

		const idx = this.thumbs.indexOf( image );

		if ( idx < 0 ) {
			return;
		}

		const neighbors = [
			this.thumbs[ ( idx + 1 ) % this.thumbs.length ],
			this.thumbs[ ( idx - 1 + this.thumbs.length ) % this.thumbs.length ]
		];

		for ( const neighbor of neighbors ) {
			// Warm the imageInfo provider cache (promise is cached, no duplicate calls)
			this.imageInfoProvider.get( neighbor.filePageTitle );

			// Warm the browser image cache with a larger thumbnail
			const url = getLargerThumbnailUrl( neighbor );

			if ( url ) {
				( new Image() ).src = url;
			}
		}
	}

	/**
	 * Navigate to the next image.
	 */
	nextImage() {
		if ( !this.currentImage || this.thumbs.length <= 1 ) {
			return;
		}

		const idx = this.thumbs.indexOf( this.currentImage );
		const next = ( idx + 1 ) % this.thumbs.length;
		const image = this.thumbs[ next ];
		const hash = Config.getMediaHash( image.filePageTitle, image.position );

		location.hash = hash;
	}

	/**
	 * Navigate to the previous image.
	 */
	prevImage() {
		if ( !this.currentImage || this.thumbs.length <= 1 ) {
			return;
		}

		const idx = this.thumbs.indexOf( this.currentImage );
		const prev = ( idx - 1 + this.thumbs.length ) % this.thumbs.length;
		const image = this.thumbs[ prev ];
		const hash = Config.getMediaHash( image.filePageTitle, image.position );

		location.hash = hash;
	}

	/**
	 * Open the viewer overlay and mount the Vue app if needed.
	 */
	open() {
		if ( !this.app ) {
			this.mountEl = document.createElement( 'div' );
			this.mountEl.id = 'mmv-beta-root';

			// mw-mmv-wrapper is whitelisted in mmv.bootstrap.less so it stays
			// visible when body.mw-mmv-lightbox-open hides other children.
			this.mountEl.classList.add( 'mw-mmv-wrapper' );
			document.body.appendChild( this.mountEl );

			this.app = createMwApp( App );
			this.app.provide( 'state', this.state );
			this.app.provide( 'close', () => this.close() );
			this.app.provide( 'nextImage', () => this.nextImage() );
			this.app.provide( 'prevImage', () => this.prevImage() );
			this.app.provide( 'toggleChrome', () => {
				this.state.chromeVisible.value = !this.state.chromeVisible.value;
			} );
			this.app.mount( this.mountEl );
		}

		this.isOpen = true;
		this.state.isOpen.value = true;
		this.documentTitle = this.documentTitle || document.title;

		if ( this.currentImage ) {
			document.title = this.createDocumentTitle( this.currentImage.filePageTitle );
		}

		$( document ).trigger( 'mmv-setup-overlay' );
	}

	/**
	 * Close the viewer overlay.
	 */
	close() {
		if ( !this.isOpen ) {
			return;
		}

		this.isOpen = false;
		this.state.isOpen.value = false;
		this.state.chromeVisible.value = true;
		this.currentImage = null;
		this.state.image.value = null;
		this.state.imageInfo.value = null;
		this.state.displayUrl.value = '';

		document.title = this.createDocumentTitle( null );

		if ( !this.comingFromHashChange ) {
			// Reset the hash so the viewer doesn't re-open on back navigation
			location.hash = '';
		}

		$( document ).trigger( 'mmv-cleanup-overlay' );
	}

	/**
	 * Creates a document title string.
	 *
	 * @param {mw.Title|null} imageTitle The title of the displayed image, or null when closing
	 * @return {string}
	 */
	createDocumentTitle( imageTitle ) {
		if ( imageTitle ) {
			return imageTitle.getNameText() + ' - ' + this.documentTitle;
		}

		return this.documentTitle;
	}
}

module.exports = BetaViewer;
