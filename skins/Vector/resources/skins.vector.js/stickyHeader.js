/**
 * Functions and variables to implement sticky header.
 */
const
	initSearchToggle = require( './searchToggle.js' ),
	updateWatchIcon = require( './watchstar.js' ).updateWatchIcon,
	STICKY_HEADER_ID = 'vector-sticky-header',
	STICKY_HEADER_APPENDED_ID = '-sticky-header',
	STICKY_HEADER_APPENDED_PARAM = [ 'wvprov', 'sticky-header' ],
	STICKY_HEADER_VISIBLE_CLASS = 'vector-sticky-header-visible',
	STICKY_HEADER_USER_MENU_CONTAINER_SELECTOR = '.vector-sticky-header-icon-end .vector-user-links',
	FIRST_HEADING_ID = 'firstHeading',
	USER_LINKS_DROPDOWN_ID = 'vector-user-links-dropdown',
	ULS_STICKY_CLASS = 'uls-dialog-sticky',
	ULS_HIDE_CLASS = 'uls-dialog-sticky-hide',
	SEARCH_TOGGLE_SELECTOR = '.vector-sticky-header-search-toggle',
	STICKY_HEADER_EXPERIMENT_NAME = 'vector.sticky_header';

/**
 * Copies attribute from an element to another.
 *
 * @param {HTMLElement} from
 * @param {HTMLElement} to
 * @param {string} attribute
 */
function copyAttribute( from, to, attribute ) {
	const fromAttr = from.getAttribute( attribute );
	if ( fromAttr ) {
		to.setAttribute( attribute, fromAttr );
	}
}

/**
 * Show the sticky header.
 */
function show() {
	document.body.classList.add( STICKY_HEADER_VISIBLE_CLASS );
	document.body.classList.remove( ULS_HIDE_CLASS );
}

/**
 * Hide the sticky header.
 */
function hide() {
	document.body.classList.remove( STICKY_HEADER_VISIBLE_CLASS );
	document.body.classList.add( ULS_HIDE_CLASS );

	// Dismiss dropdown menus and search if active
	const stickyHeader = /** @type {HTMLElement} */ ( document.getElementById( STICKY_HEADER_ID ) );
	if ( stickyHeader && stickyHeader.contains( document.activeElement ) ) {
		document.body.click();
	}
}

/**
 * Copies attribute from an element to another.
 *
 * @param {HTMLElement} from
 * @param {HTMLElement} to
 */
function copyButtonAttributes( from, to ) {
	copyAttribute( from, to, 'href' );
	copyAttribute( from, to, 'title' );
	// Copy button labels
	if ( to.lastElementChild && from.lastElementChild ) {
		to.lastElementChild.textContent = from.lastElementChild.textContent || '';
	}
}

/**
 * Gets an anchor element from a parent element by selector.
 *
 * Helper function to avoid repeating type assertions.
 *
 * @param {HTMLDocument|HTMLElement} parent
 * @param {string} selector
 * @return {HTMLAnchorElement|null}
 */
function getAnchorElement( parent, selector ) {
	return parent.querySelector( selector );
}

/**
 * Suffixes an attribute with a value that indicates it
 * relates to the sticky header to support click tracking instrumentation.
 *
 * @param {HTMLElement} node
 * @param {string} attribute
 */
function suffixStickyAttribute( node, attribute ) {
	const value = node.getAttribute( attribute );
	if ( value ) {
		node.setAttribute( attribute, value + STICKY_HEADER_APPENDED_ID );
	}
}

/**
 * Suffixes the href attribute of a node with a value that indicates it
 * relates to the sticky header to support tracking instrumentation.
 *
 * Distinct from suffixStickyAttribute as it's intended to support followed
 * links recording their origin.
 *
 * @param {HTMLAnchorElement} node
 */
function suffixStickyHref( node ) {
	const url = new URL( node.href );
	if ( url && !url.searchParams.has( STICKY_HEADER_APPENDED_PARAM[ 0 ] ) ) {
		url.searchParams.append(
			STICKY_HEADER_APPENDED_PARAM[ 0 ], STICKY_HEADER_APPENDED_PARAM[ 1 ]
		);
		node.href = url.toString();
	}
}

/**
 * Undoes the effect of suffixStickyHref
 *
 * @param {HTMLAnchorElement} node
 */
function unsuffixStickyHref( node ) {
	const url = new URL( node.href );
	url.searchParams.delete( STICKY_HEADER_APPENDED_PARAM[ 0 ] );
	node.href = url.toString();
}

/**
 * Makes a node trackable by our click tracking instrumentation.
 *
 * @param {HTMLElement} node
 */
function makeNodeTrackable( node ) {
	suffixStickyAttribute( node, 'id' );
	suffixStickyAttribute( node, 'data-event-name' );
}

/**
 * @param {HTMLElement} node
 */
function removeNode( node ) {
	if ( node.parentNode ) {
		node.parentNode.removeChild( node );
	}
}

/**
 * Ensures a sticky header button has the correct attributes
 *
 * @param {HTMLElement} watchLink
 * @param {boolean} isWatched The page is watched
 */
function updateStickyWatchlink( watchLink, isWatched ) {
	watchLink.setAttribute( 'data-event-name', isWatched ? 'watch-sticky-header' : 'unwatch-sticky-header' );
}

/**
 * @param {NodeListOf<HTMLElement>} nodes
 * @param {string} className
 */
function removeClassFromNodes( nodes, className ) {
	Array.prototype.forEach.call( nodes, ( node ) => {
		node.classList.remove( className );
	} );
}

/**
 * @param {NodeListOf<HTMLElement>} nodes
 */
function removeNodes( nodes ) {
	Array.prototype.forEach.call( nodes, ( node ) => {
		node.parentNode.removeChild( node );
	} );
}

/**
 * Callback for watchsar
 *
 * @param {JQuery} $link Watchstar link
 * @param {boolean} isWatched The page is watched
 */
function watchstarCallback( $link, isWatched ) {
	updateStickyWatchlink( /** @type {HTMLAnchorElement} */( $link[ 0 ] ), isWatched );
}

/**
 * Makes sticky header icons functional for modern Vector.
 *
 * @param {HTMLElement} header
 * @param {HTMLAnchorElement|null} history
 * @param {HTMLAnchorElement|null} talk
 * @param {HTMLAnchorElement|null} subject
 * @param {HTMLAnchorElement|null} watch
 * @param {HTMLAnchorElement|null} bookmark
 */
function prepareIcons( header, history, talk, subject, watch, bookmark ) {
	const historySticky = getAnchorElement( header, '#ca-history-sticky-header' ),
		talkSticky = getAnchorElement( header, '#ca-talk-sticky-header' ),
		subjectSticky = getAnchorElement( header, '#ca-subject-sticky-header' ),
		watchSticky = getAnchorElement( header, '#ca-watchstar-sticky-header' ),
		bookmarkSticky = getAnchorElement( header, '#ca-bookmark-sticky-header' );

	if ( historySticky && history ) {
		copyButtonAttributes( history, historySticky );
	} else if ( historySticky ) {
		removeNode( historySticky );
	}

	if ( talkSticky && talk ) {
		copyButtonAttributes( talk, talkSticky );
	} else if ( talkSticky ) {
		removeNode( talkSticky );
	}

	if ( subjectSticky && subject ) {
		copyButtonAttributes( subject, subjectSticky );
	} else if ( subjectSticky ) {
		removeNode( subjectSticky );
	}

	if ( watchSticky && watch && watch.parentNode instanceof HTMLElement ) {
		const watchContainer = watch.parentNode;
		const isTemporaryWatch = watchContainer.classList.contains( 'mw-watchlink-temp' );
		const isWatched = isTemporaryWatch || watchContainer.getAttribute( 'id' ) === 'ca-unwatch';
		const watchIcon = /** @type {HTMLElement} */ ( watchSticky.querySelector( '.vector-icon' ) );

		// Initialize sticky watchlink
		copyButtonAttributes( watch, watchSticky );
		updateWatchIcon( watchIcon, isWatched, isTemporaryWatch ? '' : 'infinity' );
		updateStickyWatchlink( watchSticky, isWatched );

		const watchLib = require( /** @type {string} */( 'mediawiki.page.watch.ajax' ) );
		// jQuery required as parameter for external API:
		// eslint-disable-next-line no-jquery/no-jquery-constructor
		watchLib.watchstar( $( watchSticky ), mw.config.get( 'wgRelevantPageName' ), watchstarCallback );
	} else if ( watchSticky ) {
		removeNode( watchSticky );
	}

	if ( bookmarkSticky && bookmark ) {
		const icon = bookmark.querySelector( '.vector-icon' );
		if ( icon ) {
			copyButtonAttributes( bookmark, bookmarkSticky );
			const bookmarkIcon = /** @type {HTMLElement} */ ( bookmark.querySelector( '.vector-icon' ) );
			const bookmarkStickyIcon = /** @type {HTMLElement} */ ( bookmarkSticky.querySelector( '.vector-icon' ) );
			bookmarkStickyIcon.className = bookmarkIcon.className;
			/** @type {HTMLElement} */( bookmarkSticky ).dataset.mwListId = /** @type {HTMLElement} */( bookmark ).dataset.mwListId || '';
			/** @type {HTMLElement} */( bookmarkSticky ).dataset.mwEntryId = /** @type {HTMLElement} */( bookmark ).dataset.mwEntryId || '';
		}
	} else if ( bookmarkSticky ) {
		removeNode( bookmarkSticky );
	}
}

/**
 * Render sticky header edit or protected page icons for modern Vector.
 *
 * @param {HTMLElement} header
 * @param {HTMLElement|null} primaryEdit
 * @param {boolean} isProtected
 * @param {HTMLElement|null} secondaryEdit
 * @param {HTMLElement|null} addSection
 * @param {Function} disableStickyHeader function to call to disable the sticky
 *  header.
 */
function prepareEditIcons(
	header,
	primaryEdit,
	isProtected,
	secondaryEdit,
	addSection,
	disableStickyHeader
) {
	const primaryEditSticky = getAnchorElement( header, '#ca-ve-edit-sticky-header' ),
		protectedSticky = getAnchorElement( header, '#ca-viewsource-sticky-header' ),
		wikitextSticky = getAnchorElement( header, '#ca-edit-sticky-header' ),
		addSectionSticky = getAnchorElement( header, '#ca-addsection-sticky-header' );

	if ( addSectionSticky ) {
		if ( addSection ) {
			copyButtonAttributes( addSection, addSectionSticky );
			suffixStickyHref( addSectionSticky );
		} else {
			removeNode( addSectionSticky );
		}
	}

	// If no primary edit icon is present the feature is disabled.
	if ( !primaryEditSticky || !wikitextSticky || !protectedSticky ) {
		return;
	}

	if ( !primaryEdit ) {
		removeNode( protectedSticky );
		removeNode( wikitextSticky );
		removeNode( primaryEditSticky );
		return;
	} else if ( isProtected ) {
		removeNode( wikitextSticky );
		removeNode( primaryEditSticky );
		copyButtonAttributes( primaryEdit, protectedSticky );
		suffixStickyHref( protectedSticky );
	} else {
		removeNode( protectedSticky );
		copyButtonAttributes( primaryEdit, primaryEditSticky );
		suffixStickyHref( primaryEditSticky );

		primaryEditSticky.addEventListener( 'click', ( ev ) => {
			const target = ev.target;
			// T336639:
			// eslint-disable-next-line no-jquery/no-jquery-constructor
			const $ve = $( primaryEdit );
			if ( target && $ve.length ) {
				const link = /** @type {HTMLAnchorElement} */( $ve[ 0 ] );
				// eslint-disable-next-line no-jquery/no-other-utils
				const event = $.Event( 'click' );
				suffixStickyHref( link );
				// eslint-disable-next-line no-jquery/no-trigger
				$ve.trigger( event );
				unsuffixStickyHref( link );
				// The link has been progressively enhanced.
				if ( event.isDefaultPrevented() ) {
					disableStickyHeader();
					ev.preventDefault();
				}
			}
		} );
		if ( secondaryEdit ) {
			copyButtonAttributes( secondaryEdit, wikitextSticky );
			suffixStickyHref( wikitextSticky );
			wikitextSticky.addEventListener( 'click', ( ev ) => {
				const target = ev.target;
				if ( target ) {
					// T336639:
					// eslint-disable-next-line no-jquery/no-jquery-constructor
					const $edit = $( secondaryEdit );
					if ( $edit.length ) {
						const link = /** @type {HTMLAnchorElement} */( $edit[ 0 ] );
						// eslint-disable-next-line no-jquery/no-other-utils
						const event = $.Event( 'click' );
						suffixStickyHref( link );
						// eslint-disable-next-line no-jquery/no-trigger
						$edit.trigger( event );
						unsuffixStickyHref( link );
						// The link has been progressively enhanced.
						if ( event.isDefaultPrevented() ) {
							disableStickyHeader();
							ev.preventDefault();
						}
					}
				}
			} );
		} else {
			removeNode( wikitextSticky );
		}
	}
}

/**
 * Check if element is in viewport.
 *
 * @param {HTMLElement} element
 * @return {boolean}
 */
function isInViewport( element ) {
	const rect = element.getBoundingClientRect();
	return (
		rect.top >= 0 &&
		rect.left >= 0 &&
		rect.bottom <= ( window.innerHeight || document.documentElement.clientHeight ) &&
		rect.right <= ( window.innerWidth || document.documentElement.clientWidth )
	);
}

/**
 * Add hooks for sticky header when Visual Editor is used.
 *
 * @param {HTMLElement} stickyIntersection intersection element
 * @param {IntersectionObserver} observer
 */
function addVisualEditorHooks( stickyIntersection, observer ) {
	// When Visual Editor is activated, hide the sticky header.
	mw.hook( 've.activationStart' ).add( () => {
		hide();
		observer.unobserve( stickyIntersection );
	} );

	// When Visual Editor is deactivated (by clicking "Read" tab at top of page), show sticky header
	// by re-triggering the observer.
	mw.hook( 've.deactivationComplete' ).add( () => {
		// Wait for the next repaint or we might calculate that
		// sticky header should not be visible (T299114)
		requestAnimationFrame( () => {
			observer.observe( stickyIntersection );
		} );
	} );

	// After saving edits, re-apply the sticky header if the target is not in the viewport.
	mw.hook( 'postEdit.afterRemoval' ).add( () => {
		if ( !isInViewport( stickyIntersection ) ) {
			show();
			observer.observe( stickyIntersection );
		}
	} );
}

/**
 * Clones the existing user menu (excluding items added by gadgets) and adds to the sticky header
 * ensuring it is not focusable and that elements are no longer collapsible (since the sticky header
 * itself collapses at low resolutions) and updates click tracking event names. Also wires up the
 * logout link so it works in a single click.
 *
 * @param {HTMLElement} userLinksDropdown
 * @return {HTMLElement} cloned userLinksDropdown
 */
function prepareUserLinksDropdown( userLinksDropdown ) {
	const
		// Type declaration needed because of https://github.com/Microsoft/TypeScript/issues/3734#issuecomment-118934518
		userLinksDropdownClone = /** @type {HTMLElement} */ ( userLinksDropdown.cloneNode( true ) ),
		/** @type {NodeListOf<HTMLElement>} */
		userLinksDropdownStickyElementsWithIds = userLinksDropdownClone.querySelectorAll( '[ id ], [ data-event-name ]' );
	// Update all ids of the cloned user menu to make them unique.
	makeNodeTrackable( userLinksDropdownClone );
	userLinksDropdownStickyElementsWithIds.forEach( makeNodeTrackable );
	// Remove portlet links added by gadgets using mw.util.addPortletLink, T291426
	removeNodes( userLinksDropdownClone.querySelectorAll( '.mw-list-item-js' ) );
	removeClassFromNodes(
		userLinksDropdownClone.querySelectorAll( '.user-links-collapsible-item' ),
		'user-links-collapsible-item'
	);
	// Prevents user menu from being focusable, T290201
	const userLinksDropdownCheckbox = userLinksDropdownClone.querySelector( 'input' );
	if ( userLinksDropdownCheckbox ) {
		userLinksDropdownCheckbox.setAttribute( 'tabindex', '-1' );
	}

	// Make the logout go through the API (T324638)
	const logoutLink = /** @type {HTMLAnchorElement} */( userLinksDropdownClone.querySelector( '#pt-logout-sticky-header a' ) );
	if ( logoutLink ) {
		logoutLink.addEventListener( 'click', ( ev ) => {
			ev.preventDefault();
			mw.hook( 'skin.logout' ).fire( logoutLink.href );
		} );
	}
	return userLinksDropdownClone;
}

/**
 * Makes sticky header functional for modern Vector.
 *
 * @param {HTMLElement} header
 * @param {HTMLElement} userLinksDropdown
 * @param {IntersectionObserver} stickyObserver
 * @param {HTMLElement} stickyIntersection
 */
function makeStickyHeaderFunctional(
	header,
	userLinksDropdown,
	stickyObserver,
	stickyIntersection
) {
	const userLinksDropdownStickyContainer = document.querySelector(
		STICKY_HEADER_USER_MENU_CONTAINER_SELECTOR
	);

	// Clone the updated user menu to the sticky header.
	if ( userLinksDropdownStickyContainer ) {
		const clonedUserLinksDropdown = prepareUserLinksDropdown( userLinksDropdown );
		userLinksDropdownStickyContainer.appendChild( clonedUserLinksDropdown );
	}

	let namespaceName = mw.config.get( 'wgCanonicalNamespace' );
	const namespaceNumber = mw.config.get( 'wgNamespaceNumber' );
	if ( namespaceNumber >= 0 && namespaceNumber % 2 === 1 ) {
		// Remove '_talk' to get subject namespace
		namespaceName = namespaceName.slice( 0, -5 );
	}
	// Title::getNamespaceKey()
	let namespaceKey = namespaceName.toLowerCase() || 'main';
	if ( namespaceKey === 'file' ) {
		namespaceKey = 'image';
	}
	const namespaceTabId = 'ca-nstab-' + namespaceKey;

	prepareIcons( header,
		document.querySelector( '#ca-history a' ),
		document.querySelector( '#ca-talk:not( .selected ) a' ),
		document.querySelector( '#' + namespaceTabId + ':not( .selected ) a' ),
		document.querySelector( '#ca-watch a, #ca-unwatch a' ),
		document.querySelector( '#ca-bookmark a' )
	);

	const veEdit = getAnchorElement( document, '#ca-ve-edit a' );
	const ceEdit = getAnchorElement( document, '#ca-edit a' );
	const protectedEdit = getAnchorElement( document, '#ca-viewsource a' );
	const isProtected = !!protectedEdit;
	// For sticky header edit A/B test, conditionally remove the edit icon by setting null.
	// Otherwise, use either protected, ve, or source edit (in that order).
	const primaryEdit = protectedEdit || veEdit || ceEdit;
	const secondaryEdit = veEdit ? ceEdit : null;
	const disableStickyHeader = () => {
		document.body.classList.remove( STICKY_HEADER_VISIBLE_CLASS );
		stickyObserver.unobserve( stickyIntersection );
	};
	// When VectorPromoteAddTopic is set, #ca-addsection is the link itself
	/** @type {HTMLElement|null} */
	const addSection = document.querySelector( '#ca-addsection a' ) || document.querySelector( 'a#ca-addsection' );

	prepareEditIcons(
		header,
		primaryEdit,
		isProtected,
		secondaryEdit,
		addSection,
		disableStickyHeader
	);

	stickyObserver.observe( stickyIntersection );
}

/**
 * @param {HTMLElement} header
 */
function setupSearchIfNeeded( header ) {
	if ( !document.body.classList.contains( 'skin-vector-search-vue' ) ) {
		return;
	}

	/** @type {HTMLElement|null} */
	const searchToggle = header.querySelector( SEARCH_TOGGLE_SELECTOR );

	if ( searchToggle ) {
		initSearchToggle( searchToggle );
	}
}

/**
 * Determines if sticky header should be visible for a given namespace.
 *
 * @param {number} namespaceNumber
 * @return {boolean}
 */
function isAllowedNamespace( namespaceNumber ) {
	// Corresponds to Main, User, Wikipedia, Template, Help, Category, Portal, Module.
	const allowedNamespaceNumbers = [ 0, 2, 4, 10, 12, 14, 100, 828 ];
	// Also allow on all talk namespaces (compare NamespaceInfo::isTalk()).
	const isAllowedTalk = namespaceNumber > 0 && namespaceNumber % 2 !== 0;
	return isAllowedTalk || allowedNamespaceNumbers.includes( namespaceNumber );
}

/**
 * Determines if sticky header should be visible for a given action.
 *
 * @param {string} action
 * @return {boolean}
 */
function isAllowedAction( action ) {
	const disallowedActions = [ 'history', 'edit' ],
		hasDiffId = mw.config.get( 'wgDiffOldId' );
	return !disallowedActions.includes( action ) && !hasDiffId;
}

/**
 * @typedef {Object} StickyHeaderProps
 * @property {HTMLElement} header
 * @property {HTMLElement} userLinksDropdown
 * @property {IntersectionObserver} observer
 * @property {HTMLElement} stickyIntersection
 */

/**
 * @param {StickyHeaderProps} props
 */
function initStickyHeader( props ) {
	makeStickyHeaderFunctional(
		props.header,
		props.userLinksDropdown,
		props.observer,
		props.stickyIntersection
	);

	setupSearchIfNeeded( props.header );
	addVisualEditorHooks( props.stickyIntersection, props.observer );

	// Make sure ULS outside sticky header disables the sticky header behaviour.
	mw.hook( 'mw.uls.compact_language_links.open' ).add( ( $trigger ) => {
		const trigger = $trigger[ 0 ];
		if ( trigger.id !== 'p-lang-btn-sticky-header' ) {
			const bodyClassList = document.body.classList;
			bodyClassList.remove( ULS_HIDE_CLASS );
			bodyClassList.remove( ULS_STICKY_CLASS );
		}
	} );

	// Make sure ULS dialog is sticky.
	const langBtn = props.header.querySelector( '#p-lang-btn-sticky-header' );
	if ( langBtn ) {
		langBtn.addEventListener( 'click', () => {
			const bodyClassList = document.body.classList;
			bodyClassList.remove( ULS_HIDE_CLASS );
			bodyClassList.add( ULS_STICKY_CLASS );
		} );
	}
}

module.exports = {
	show,
	hide,
	prepareUserLinksDropdown,
	isAllowedNamespace,
	isAllowedAction,
	initStickyHeader,
	STICKY_HEADER_ID,
	FIRST_HEADING_ID,
	USER_LINKS_DROPDOWN_ID,
	STICKY_HEADER_EXPERIMENT_NAME
};
