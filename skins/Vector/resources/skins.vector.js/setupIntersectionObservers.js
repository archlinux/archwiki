// Enable Vector features limited to ES6 browse
const
	stickyHeader = require( './stickyHeader.js' ),
	scrollObserver = require( './scrollObserver.js' ),
	initSectionObserver = require( './sectionObserver.js' ),
	initTableOfContents = require( './tableOfContents.js' ),
	pinnableElement = require( './pinnableElement.js' ),
	features = require( './features.js' ),
	deferUntilFrame = require( './deferUntilFrame.js' ),
	STICKY_HEADER_ENABLED_CLASS = 'vector-sticky-header-enabled',
	STICKY_HEADER_VISIBLE_CLASS = 'vector-sticky-header-visible',
	TOC_ID = 'vector-toc',
	BODY_CONTENT_ID = 'bodyContent',
	// Selectors that match heading markup, which looks like this:
	//   <div class="mw-heading"> <h2 id="...">...</h2> ... </div>
	HEADING_TAGS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
	HEADING_SELECTOR = [ '.mw-heading' ]
		.map( ( sel ) => `.mw-parser-output ${ sel }` ).join( ', ' ),
	HEADLINE_SELECTOR = [ ...HEADING_TAGS.map( ( tag ) => `${ tag }[id]` ) ]
		.map( ( sel ) => `.mw-parser-output ${ sel }` ).join( ', ' ),
	TOC_SECTION_ID_PREFIX = 'toc-',
	PAGE_TITLE_INTERSECTION_CLASS = 'vector-below-page-title';

const belowDesktopMedia = window.matchMedia( '(max-width: 1119px)' );

/**
 * @typedef {ReturnType<import('./tableOfContents.js')>} TableOfContents
 */

/**
 * @callback OnIntersection
 * @param {HTMLElement} element The section that triggered the new intersection change.
 */

/**
 * @ignore
 * @param {Function} changeActiveSection
 * @return {OnIntersection}
 */
const getHeadingIntersectionHandler = ( changeActiveSection ) =>
	/**
	 * @param {HTMLElement} section
	 */
	// eslint-disable-next-line implicit-arrow-linebreak
	( section ) => {
		const headline = section.classList.contains( 'mw-body-content' ) ?
			section :
			section.querySelector( HEADLINE_SELECTOR );
		if ( headline ) {
			changeActiveSection( `${ TOC_SECTION_ID_PREFIX }${ headline.id }` );
		}
	};

/**
 * Updates TOC's location in the DOM depending on if the TOC is collapsed and if the
 * sticky header is visible
 *
 * @param {HTMLElement|null} pinnableHeader
 * @return {void}
 */
const updateTocLocation = ( pinnableHeader ) => {
	if ( !pinnableHeader ) {
		return;
	}

	const isStickyHeaderVisible = document.body.classList.contains( STICKY_HEADER_VISIBLE_CLASS );
	const isBelowDesktop = belowDesktopMedia.matches;

	// Default is for the TOC unpinned container to be in the page titlebar
	const stickyHeaderUnpinnedContainerId = 'vector-sticky-header-toc-unpinned-container';
	const pageTitlebarUnpinnedContainerId = 'vector-page-titlebar-toc-unpinned-container';
	const unpinnedContainerId = ( isStickyHeaderVisible && !isBelowDesktop ) ?
		stickyHeaderUnpinnedContainerId : pageTitlebarUnpinnedContainerId;
	pinnableHeader.dataset.unpinnedContainerId = unpinnedContainerId;

	// Call `updatePinnableState` to move the TOC to the correct container based on the
	// new unpinned container id
	const isPinned = features.isEnabled( 'toc-pinned' );
	pinnableElement.updatePinnableState( pinnableHeader, isPinned );
};

/**
 * Return the combined scroll offset for headings, adding together
 * the computed value of the `scroll-padding-top` CSS property of the document element
 * and the `scroll-margin-top` CSS property of the headings.
 * This is also used for the scroll intersection threshold (T317661).
 *
 * @return {number} Combined heading scroll offset
 */
function getHeadingScrollOffset() {
	// This value matches the @scroll-margin-heading LESS variable
	const scrollMarginHeading = 75;
	const documentStyles = getComputedStyle( document.documentElement );
	const scrollPaddingTopString = documentStyles.getPropertyValue( 'scroll-padding-top' );
	// 'auto' is the default value, '' is returned by browsers not supporting
	// this property (T398521).
	const scrollPaddingTop = ( scrollPaddingTopString === 'auto' || scrollPaddingTopString === '' ) ?
		0 : parseInt( scrollPaddingTopString, 10 );
	return scrollPaddingTop + scrollMarginHeading;
}

/**
 * @param {HTMLElement} tocElement
 * @param {HTMLElement} bodyContent
 * @param {initSectionObserver} initSectionObserverFn
 * @return {TableOfContents}
 */
const setupTableOfContents = ( tocElement, bodyContent, initSectionObserverFn ) => {
	const handleTocSectionChange = () => {
		// eslint-disable-next-line no-use-before-define
		sectionObserver.pause();

		// T297614: We want the link that the user has clicked inside the TOC or the
		// section that corresponds to the hashchange event to be "active" (e.g.
		// bolded) regardless of whether the browser's scroll position corresponds
		// to that section. Therefore, we need to temporarily ignore section
		// observer until the browser has finished scrolling to the section (if
		// needed).
		//
		// However, because the scroll event happens asynchronously after the user
		// clicks on a link and may not even happen at all (e.g. the user has
		// scrolled all the way to the bottom and clicks a section that is already
		// in the viewport), determining when we should resume section observer is a
		// bit tricky.
		//
		// Because a scroll event may not even be triggered after clicking the link,
		// we instead allow the browser to perform a maximum number of repaints
		// before resuming sectionObserver. Per T297614#7687656, Firefox 97.0 wasn't
		// consistently activating the table of contents section that the user
		// clicked even after waiting 2 frames. After further investigation, it
		// sometimes waits up to 3 frames before painting the new scroll position so
		// we have that as the limit.
		deferUntilFrame( () => {
			// eslint-disable-next-line no-use-before-define
			sectionObserver.resume();
		}, 3 );
	};

	const tableOfContents = initTableOfContents( {
		container: tocElement,
		onHeadingClick: handleTocSectionChange,
		onHashChange: handleTocSectionChange
	} );
	const elements = () => bodyContent.querySelectorAll( `${ HEADING_SELECTOR }, .mw-body-content` );

	const sectionObserver = initSectionObserverFn( {
		elements: elements(),
		topMargin: getHeadingScrollOffset(),
		onIntersection: getHeadingIntersectionHandler( tableOfContents.changeActiveSection )
	} );
	const updateElements = () => {
		sectionObserver.resume();
		sectionObserver.setElements( elements() );
	};
	mw.hook( 've.activationStart' ).add( () => {
		sectionObserver.pause();
	} );
	mw.hook( 'wikipage.tableOfContents' ).add( ( sections ) => {
		tableOfContents.reloadTableOfContents( sections ).then( () => {
			/**
			 * @stable for use in gadgets and extensions
			 * @since 1.40
			 */
			mw.hook( 'wikipage.tableOfContents.vector' ).fire( sections );
			updateElements();
		} );
	} );
	mw.hook( 've.deactivationComplete' ).add( updateElements );

	const setInitialActiveSection = () => {
		const hash = location.hash.slice( 1 );
		// If hash fragment is blank, determine the active section with section
		// observer.
		if ( hash === '' ) {
			sectionObserver.calcIntersection();
			return;
		}

		// T325086: If hash fragment is present and corresponds to a toc section,
		// expand the section.
		const hashSection = /** @type {HTMLElement|null} */ ( mw.util.getTargetFromFragment( `${ TOC_SECTION_ID_PREFIX }${ hash }` ) );
		if ( hashSection ) {
			tableOfContents.expandSection( hashSection.id );
		}

		// T325086: If hash fragment corresponds to a section AND the user is at
		// bottom of page, activate the section. Otherwise, use section observer to
		// calculate the active section.
		//
		// Note that even if a hash fragment is present, it's possible for the
		// browser to scroll to a position that is different from the position of
		// the section that corresponds to the hash fragment. This can happen when
		// the browser remembers a prior scroll position after refreshing the page,
		// for example.
		if (
			hashSection &&
			Math.round( window.innerHeight + window.scrollY ) >= document.body.scrollHeight
		) {
			tableOfContents.changeActiveSection( hashSection.id );
		} else {
			// Fallback to section observer's calculation for the active section.
			sectionObserver.calcIntersection();
		}
	};

	setInitialActiveSection();

	return tableOfContents;
};

/**
 * @return {void}
 */
const main = () => {
	//
	//  Table of contents
	//
	const tocElement = document.getElementById( TOC_ID );
	const bodyContent = document.getElementById( BODY_CONTENT_ID );
	const tocPinnableHeader = /** @type {HTMLElement|null} */ (
		document.querySelector( '.vector-toc-pinnable-header' )
	);

	/** @type {TableOfContents | null} */
	let tableOfContents = null;
	if ( tocElement && bodyContent && tocPinnableHeader ) {
		tableOfContents = setupTableOfContents( tocElement, bodyContent, initSectionObserver );
		updateTocLocation( tocPinnableHeader );
	}

	//
	// Sticky header
	//
	const
		stickyHeaderElement = document.getElementById( stickyHeader.STICKY_HEADER_ID ),
		stickyIntersection = document.getElementById( stickyHeader.FIRST_HEADING_ID ),
		userLinksDropdown = document.getElementById( stickyHeader.USER_LINKS_DROPDOWN_ID ),
		allowedNamespace = stickyHeader.isAllowedNamespace( mw.config.get( 'wgNamespaceNumber' ) ),
		allowedAction = stickyHeader.isAllowedAction( mw.config.get( 'wgAction' ) );

	const stickyHeaderAllowed =
		!mw.user.isAnon() &&
		!!stickyHeaderElement &&
		!!stickyIntersection &&
		!!userLinksDropdown &&
		allowedNamespace &&
		allowedAction;

	let scrolledPastPageTitle = false;

	// Set up intersection observer for page title
	// Used to show/hide sticky header and add class used by collapsible TOC (T307900)
	const observer = scrollObserver.initScrollObserver(
		() => {
			if ( stickyHeaderAllowed ) {
				scrolledPastPageTitle = true;
				if ( !belowDesktopMedia.matches ) {
					stickyHeader.show();
				}
				if ( tableOfContents ) {
					updateTocLocation( tocPinnableHeader );
				}
			}
			document.body.classList.add( PAGE_TITLE_INTERSECTION_CLASS );
			if ( tableOfContents ) {
				tableOfContents.updateTocToggleStyles( true );
			}
			scrollObserver.firePageTitleScrollHook( 'down' );
		},
		() => {
			if ( stickyHeaderAllowed ) {
				scrolledPastPageTitle = false;
				if ( !belowDesktopMedia.matches ) {
					stickyHeader.hide();
				}
				if ( tableOfContents ) {
					updateTocLocation( tocPinnableHeader );
				}
			}
			document.body.classList.remove( PAGE_TITLE_INTERSECTION_CLASS );
			if ( tableOfContents ) {
				tableOfContents.updateTocToggleStyles( false );
			}
			scrollObserver.firePageTitleScrollHook( 'up' );
		}
	);

	belowDesktopMedia.onchange = () => {
		// Handle show/hide of sticky header on viewport resize
		if ( !belowDesktopMedia.matches && scrolledPastPageTitle ) {
			stickyHeader.show();
		} else {
			stickyHeader.hide();
		}

		if ( tableOfContents ) {
			// Handle unpinned container when sticky header is hidden on lower viewports
			updateTocLocation( tocPinnableHeader );
		}
	};

	if ( !stickyHeaderAllowed ) {
		stickyHeader.hide();
		document.documentElement.classList.remove( STICKY_HEADER_ENABLED_CLASS );
	}

	if ( stickyHeaderAllowed ) {
		stickyHeader.initStickyHeader( {
			header: stickyHeaderElement,
			userLinksDropdown,
			observer,
			stickyIntersection
		} );
	} else if ( stickyIntersection ) {
		observer.observe( stickyIntersection );
	}
};

module.exports = {
	main,
	test: {
		setupTableOfContents,
		getHeadingIntersectionHandler
	}
};
