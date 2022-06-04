/**
 * Functions and variables to implement sticky header.
 */
const
	STICKY_HEADER_ID = 'vector-sticky-header',
	header = document.getElementById( STICKY_HEADER_ID ),
	initSearchToggle = require( './searchToggle.js' ),
	STICKY_HEADER_APPENDED_ID = '-sticky-header',
	STICKY_HEADER_VISIBLE_CLASS = 'vector-sticky-header-visible',
	STICKY_HEADER_USER_MENU_CONTAINER_CLASS = 'vector-sticky-header-icon-end',
	FIRST_HEADING_ID = 'firstHeading',
	USER_MENU_ID = 'p-personal',
	ULS_STICKY_CLASS = 'uls-dialog-sticky',
	ULS_HIDE_CLASS = 'uls-dialog-sticky-hide',
	VECTOR_USER_LINKS_SELECTOR = '.vector-user-links',
	SEARCH_TOGGLE_SELECTOR = '.vector-sticky-header-search-toggle',
	STICKY_HEADER_EXPERIMENT_NAME = 'vector.sticky_header';

/**
 * Copies attribute from an element to another.
 *
 * @param {Element} from
 * @param {Element} to
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
	if ( header ) {
		header.classList.add( STICKY_HEADER_VISIBLE_CLASS );
	}
	document.body.classList.remove( ULS_HIDE_CLASS );
}

/**
 * Hide the sticky header.
 */
function hide() {
	if ( header ) {
		header.classList.remove( STICKY_HEADER_VISIBLE_CLASS );
		document.body.classList.add( ULS_HIDE_CLASS );
	}
}

/**
 * Copies attribute from an element to another.
 *
 * @param {Element} from
 * @param {Element} to
 */
function copyButtonAttributes( from, to ) {
	copyAttribute( from, to, 'href' );
	copyAttribute( from, to, 'title' );
}

/**
 * Suffixes an attribute with a value that indicates it
 * relates to the sticky header to support click tracking instrumentation.
 *
 * @param {Element} node
 * @param {string} attribute
 */
function suffixStickyAttribute( node, attribute ) {
	const value = node.getAttribute( attribute );
	if ( value ) {
		node.setAttribute( attribute, value + STICKY_HEADER_APPENDED_ID );
	}
}

/**
 * Makes a node trackable by our click tracking instrumentation.
 *
 * @param {Element} node
 */
function makeNodeTrackable( node ) {
	suffixStickyAttribute( node, 'id' );
	suffixStickyAttribute( node, 'data-event-name' );
}

/**
 * @param {Element} node
 */
function removeNode( node ) {
	if ( node.parentNode ) {
		node.parentNode.removeChild( node );
	}
}

/**
 * @param {NodeList} nodes
 * @param {string} className
 */
function removeClassFromNodes( nodes, className ) {
	Array.prototype.forEach.call( nodes, function ( node ) {
		node.classList.remove( className );
	} );
}

/**
 * @param {NodeList} nodes
 */
function removeNodes( nodes ) {
	Array.prototype.forEach.call( nodes, function ( node ) {
		node.parentNode.removeChild( node );
	} );
}

/**
 * Ensures a sticky header button has the correct attributes
 *
 * @param {Element} watchSticky
 * @param {string} status 'watched', 'unwatched', or 'temporary'
 */
function updateStickyWatchlink( watchSticky, status ) {
	watchSticky.classList.toggle( 'mw-ui-icon-wikimedia-star', status === 'unwatched' );
	watchSticky.classList.toggle( 'mw-ui-icon-wikimedia-unStar', status === 'watched' );
	watchSticky.classList.toggle( 'mw-ui-icon-wikimedia-halfStar', status === 'temporary' );
	watchSticky.setAttribute( 'data-event-name', status === 'unwatched' ? 'watch-sticky-header' : 'unwatch-sticky-header' );
}

/**
 * Callback for watchsar
 *
 * @param {jQuery} $link Watchstar link
 * @param {boolean} isWatched The page is watched
 * @param {string} [expiry] Optional expiry time
 */
function watchstarCallback( $link, isWatched, expiry ) {
	updateStickyWatchlink(
		// @ts-ignore
		$link[ 0 ],
		expiry !== 'infinity' ? 'temporary' :
			isWatched ? 'watched' : 'unwatched'
	);
}

/**
 * Makes sticky header icons functional for modern Vector.
 *
 * @param {Element} headerElement
 * @param {Element|null} history
 * @param {Element|null} talk
 * @param {Element|null} watch
 */
function prepareIcons( headerElement, history, talk, watch ) {
	const historySticky = headerElement.querySelector( '#ca-history-sticky-header' ),
		talkSticky = headerElement.querySelector( '#ca-talk-sticky-header' ),
		watchSticky = headerElement.querySelector( '#ca-watchstar-sticky-header' );

	if ( !historySticky || !talkSticky || !watchSticky ) {
		throw new Error( 'Sticky header has unexpected HTML' );
	}

	if ( history ) {
		copyButtonAttributes( history, historySticky );
	} else {
		removeNode( historySticky );
	}
	if ( talk ) {
		copyButtonAttributes( talk, talkSticky );
	} else {
		removeNode( talkSticky );
	}
	if ( watch && watch.parentNode instanceof Element ) {
		const watchContainer = watch.parentNode;
		copyButtonAttributes( watch, watchSticky );
		updateStickyWatchlink(
			watchSticky,
			watchContainer.classList.contains( 'mw-watchlink-temp' ) ? 'temporary' :
				watchContainer.getAttribute( 'id' ) === 'ca-watch' ? 'unwatched' : 'watched'
		);

		const watchLib = require( /** @type {string} */( 'mediawiki.page.watch.ajax' ) );
		watchLib.watchstar( $( watchSticky ), mw.config.get( 'wgRelevantPageName' ), watchstarCallback );
	} else {
		removeNode( watchSticky );
	}
}

/**
 * Render sticky header edit or protected page icons for modern Vector.
 *
 * @param {Element} headerElement
 * @param {Element|null} primaryEdit
 * @param {boolean} isProtected
 * @param {Element|null} secondaryEdit
 * @param {Function} disableStickyHeader function to call to disable the sticky
 *  header.
 */
function prepareEditIcons(
	headerElement,
	primaryEdit,
	isProtected,
	secondaryEdit,
	disableStickyHeader
) {
	const
		primaryEditSticky = headerElement.querySelector(
			'#ca-ve-edit-sticky-header'
		),
		protectedSticky = headerElement.querySelector(
			'#ca-viewsource-sticky-header'
		),
		wikitextSticky = headerElement.querySelector(
			'#ca-edit-sticky-header'
		);

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
	} else {
		removeNode( protectedSticky );
		copyButtonAttributes( primaryEdit, primaryEditSticky );

		primaryEditSticky.addEventListener( 'click', function ( ev ) {
			const target = ev.target;
			const $ve = $( primaryEdit );
			if ( target && $ve.length ) {
				const event = $.Event( 'click' );
				$ve.trigger( event );
				// The link has been progressively enhanced.
				if ( event.isDefaultPrevented() ) {
					disableStickyHeader();
					ev.preventDefault();
				}
			}
		} );
		if ( secondaryEdit ) {
			copyButtonAttributes( secondaryEdit, wikitextSticky );
			wikitextSticky.addEventListener( 'click', function ( ev ) {
				const target = ev.target;
				if ( target ) {
					const $edit = $( secondaryEdit );
					if ( $edit.length ) {
						const event = $.Event( 'click' );
						$edit.trigger( event );
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
 * @param {Element} element
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
 * @param {Element} targetIntersection intersection element
 * @param {IntersectionObserver} observer
 */
function addVisualEditorHooks( targetIntersection, observer ) {
	// When Visual Editor is activated, hide the sticky header.
	mw.hook( 've.activationStart' ).add( () => {
		hide();
		observer.unobserve( targetIntersection );
	} );

	// When Visual Editor is deactivated (by clicking "Read" tab at top of page), show sticky header
	// by re-triggering the observer.
	mw.hook( 've.deactivationComplete' ).add( () => {
		// Wait for the next repaint or we might calculate that
		// sticky header should not be visible (T299114)
		requestAnimationFrame( () => {
			observer.observe( targetIntersection );
		} );
	} );

	// After saving edits, re-apply the sticky header if the target is not in the viewport.
	mw.hook( 'postEdit.afterRemoval' ).add( () => {
		if ( !isInViewport( targetIntersection ) ) {
			show();
			observer.observe( targetIntersection );
		}
	} );
}

/**
 * @param {Element} userMenu
 * @return {Element} cloned userMenu
 */
function prepareUserMenu( userMenu ) {
	const
		// Type declaration needed because of https://github.com/Microsoft/TypeScript/issues/3734#issuecomment-118934518
		userMenuClone = /** @type {Element} */( userMenu.cloneNode( true ) ),
		userMenuStickyElementsWithIds = userMenuClone.querySelectorAll( '[ id ], [ data-event-name ]' );
	// Update all ids of the cloned user menu to make them unique.
	makeNodeTrackable( userMenuClone );
	userMenuStickyElementsWithIds.forEach( makeNodeTrackable );
	// Remove portlet links added by gadgets using mw.util.addPortletLink, T291426
	removeNodes( userMenuClone.querySelectorAll( '.mw-list-item-js' ) );
	removeClassFromNodes(
		userMenuClone.querySelectorAll( '.user-links-collapsible-item' ),
		'user-links-collapsible-item'
	);
	// Prevents user menu from being focusable, T290201
	const userMenuCheckbox = userMenuClone.querySelector( 'input' );
	if ( userMenuCheckbox ) {
		userMenuCheckbox.setAttribute( 'tabindex', '-1' );
	}
	return userMenuClone;
}

/**
 * Makes sticky header functional for modern Vector.
 *
 * @param {Element} headerElement
 * @param {Element} userMenu
 * @param {Element} userMenuStickyContainer
 * @param {IntersectionObserver} stickyObserver
 * @param {Element} stickyIntersection
 */
function makeStickyHeaderFunctional(
	headerElement,
	userMenu,
	userMenuStickyContainer,
	stickyObserver,
	stickyIntersection
) {
	const
		userMenuStickyContainerInner = userMenuStickyContainer
			.querySelector( VECTOR_USER_LINKS_SELECTOR );

	// Clone the updated user menu to the sticky header.
	if ( userMenuStickyContainerInner ) {
		userMenuStickyContainerInner.appendChild( prepareUserMenu( userMenu ) );
	}

	prepareIcons( headerElement,
		document.querySelector( '#ca-history a' ),
		document.querySelector( '#ca-talk a' ),
		document.querySelector( '#ca-watch a, #ca-unwatch a' )
	);

	const veEdit = document.querySelector( '#ca-ve-edit a' );
	const ceEdit = document.querySelector( '#ca-edit a' );
	const protectedEdit = document.querySelector( '#ca-viewsource a' );
	const isProtected = !!protectedEdit;
	const primaryEdit = protectedEdit || ( veEdit || ceEdit );
	const secondaryEdit = veEdit ? ceEdit : null;
	const disableStickyHeader = () => {
		headerElement.classList.remove( STICKY_HEADER_VISIBLE_CLASS );
		stickyObserver.unobserve( stickyIntersection );
	};

	prepareEditIcons(
		headerElement,
		primaryEdit,
		isProtected,
		secondaryEdit,
		disableStickyHeader
	);

	stickyObserver.observe( stickyIntersection );
}

/**
 * @param {Element} headerElement
 */
function setupSearchIfNeeded( headerElement ) {
	const
		searchToggle = headerElement.querySelector( SEARCH_TOGGLE_SELECTOR );

	if ( !document.body.classList.contains( 'skin-vector-search-vue' ) ) {
		return;
	}

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
	return allowedNamespaceNumbers.indexOf( namespaceNumber ) > -1;
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
	return disallowedActions.indexOf( action ) < 0 && !hasDiffId;
}

const
	stickyIntersection = document.getElementById(
		FIRST_HEADING_ID
	),
	userMenu = document.getElementById( USER_MENU_ID ),
	userMenuStickyContainer = document.getElementsByClassName(
		STICKY_HEADER_USER_MENU_CONTAINER_CLASS
	)[ 0 ],
	allowedNamespace = isAllowedNamespace( mw.config.get( 'wgNamespaceNumber' ) ),
	allowedAction = isAllowedAction( mw.config.get( 'wgAction' ) );

/**
 * Check if all conditions are met to enable sticky header
 *
 * @return {boolean}
 */
function isStickyHeaderAllowed() {
	return !!header &&
		!!stickyIntersection &&
		!!userMenu &&
		userMenuStickyContainer &&
		allowedNamespace &&
		allowedAction &&
		'IntersectionObserver' in window;
}

/**
 * @param {IntersectionObserver} observer
 */
function initStickyHeader( observer ) {
	if ( !isStickyHeaderAllowed() || !header || !userMenu || !stickyIntersection ) {
		return;
	}

	makeStickyHeaderFunctional(
		header,
		userMenu,
		userMenuStickyContainer,
		observer,
		stickyIntersection
	);
	setupSearchIfNeeded( header );
	addVisualEditorHooks( stickyIntersection, observer );

	// Make sure ULS outside sticky header disables the sticky header behaviour.
	// @ts-ignore
	mw.hook( 'mw.uls.compact_language_links.open' ).add( function ( $trigger ) {
		if ( $trigger.attr( 'id' ) !== 'p-lang-btn-sticky-header' ) {
			const bodyClassList = document.body.classList;
			bodyClassList.remove( ULS_HIDE_CLASS );
			bodyClassList.remove( ULS_STICKY_CLASS );
		}
	} );

	// Make sure ULS dialog is sticky.
	const langBtn = header.querySelector( '#p-lang-btn-sticky-header' );
	if ( langBtn ) {
		langBtn.addEventListener( 'click', function () {
			const bodyClassList = document.body.classList;
			bodyClassList.remove( ULS_HIDE_CLASS );
			bodyClassList.add( ULS_STICKY_CLASS );
		} );
	}
}

module.exports = {
	show,
	hide,
	prepareUserMenu,
	initStickyHeader,
	isStickyHeaderAllowed,
	header,
	stickyIntersection,
	STICKY_HEADER_EXPERIMENT_NAME
};
