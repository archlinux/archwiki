/**
 * Animate watch/unwatch links to use asynchronous API requests to
 * watch pages, rather than navigating to a different URI.
 *
 * Usage:
 *
 *     var watch = require( 'mediawiki.page.watch.ajax' );
 *     watch.updateWatchLink(
 *         $node,
 *         'watch',
 *         'loading'
 *     );
 *     // When the watch status of the page has been updated:
 *     watch.updatePageWatchStatus( true );
 *
 * @class mw.plugin.page.watch.ajax
 * @singleton
 */
( function () {
	// The name of the page to watch or unwatch
	var pageTitle = mw.config.get( 'wgRelevantPageName' ),
		isWatchlistExpiryEnabled = require( './config.json' ).WatchlistExpiry,
		watchstarsByTitle = {};

	/**
	 * Update the link text, link href attribute and (if applicable)
	 * "loading" class.
	 *
	 * @param {jQuery} $link Anchor tag of (un)watch link
	 * @param {string} action One of 'watch', 'unwatch'
	 * @param {string} [state="idle"] 'idle' or 'loading'. Default is 'idle'
	 * @param {string} [expiry='infinity'] The expiry date if a page is being watched temporarily.
	 */
	function updateWatchLinkAttributes( $link, action, state, expiry ) {
		// A valid but empty jQuery object shouldn't throw a TypeError
		if ( !$link.length ) {
			return;
		}

		expiry = expiry || 'infinity';

		// Invalid actions shouldn't silently turn the page in an unrecoverable state
		if ( action !== 'watch' && action !== 'unwatch' ) {
			throw new Error( 'Invalid action' );
		}

		var otherAction = action === 'watch' ? 'unwatch' : 'watch';
		var $li = $link.closest( 'li' );

		if ( state !== 'loading' ) {
			// jQuery event, @deprecated in 1.38
			// Trigger a 'watchpage' event for this List item.
			// NB: A expiry of 'infinity' is cast to null here, but not above
			$li.trigger( 'watchpage.mw', [ otherAction, expiry === 'infinity' ? null : expiry ] );
		}

		var tooltipAction = action;
		var daysLeftExpiry = null;
		// Checking to see what if the expiry is set or indefinite to display the correct message
		if ( isWatchlistExpiryEnabled && action === 'unwatch' ) {
			if ( expiry === 'infinity' ) {
				// Resolves to tooltip-ca-unwatch message
				tooltipAction = 'unwatch';
			} else {
				var expiryDate = new Date( expiry );
				var currentDate = new Date();
				// Using the Math.ceil function instead of floor so when, for example, a user selects one week
				// the tooltip shows 7 days instead of 6 days (see Phab ticket T253936)
				daysLeftExpiry = Math.ceil( ( expiryDate - currentDate ) / ( 1000 * 60 * 60 * 24 ) );
				if ( daysLeftExpiry > 0 ) {
					// Resolves to tooltip-ca-unwatch-expiring message
					tooltipAction = 'unwatch-expiring';
				} else {
					// Resolves to tooltip-ca-unwatch-expiring-hours message
					tooltipAction = 'unwatch-expiring-hours';
				}
			}
		}

		var msgKey = state === 'loading' ? action + 'ing' : action;
		$link
			// The following messages can be used here:
			// * watch
			// * watching
			// * unwatch
			// * unwatching
			.text( mw.msg( msgKey ) )
			// The following messages can be used here:
			// * tooltip-ca-watch
			// * tooltip-ca-unwatch
			// * tooltip-ca-unwatch-expiring
			// * tooltip-ca-unwatch-expiring-hours
			.attr( 'title', mw.msg( 'tooltip-ca-' + tooltipAction, daysLeftExpiry ) )
			.updateTooltipAccessKeys()
			.attr( 'href', mw.util.getUrl( pageTitle, { action: action } ) );

		if ( expiry === null || expiry === 'infinity' ) {
			$li.removeClass( 'mw-watchlink-temp' );
		} else {
			$li.addClass( 'mw-watchlink-temp' );
		}

		if ( state === 'loading' ) {
			$link.addClass( 'loading' );
		} else {
			$link.removeClass( 'loading' );

			// Most common ID style
			if ( $li.prop( 'id' ) === 'ca-' + otherAction ) {
				$li.prop( 'id', 'ca-' + action );
			}
		}
	}

	/**
	 * Notify hooks listeners of the new page watch status
	 *
	 * Watchstars should not need to use this hook, as they are updated via
	 * callback, and automatically kept in sync if a watchstar with the same
	 * title is changed.
	 *
	 * This hook should by used by other interfaces that care if the watch
	 * status of the page has changed, e.g. an edit form which wants to
	 * update a 'watch this page' checkbox.
	 *
	 * Users which change the watch status of the page without using a
	 * watchstar (e.g edit forms again) should use the updatePageWatchStatus
	 * method to ensure watchstars are updated and this hook is fired.
	 *
	 * @param {boolean} isWatched The page is watched
	 * @param {string} [expiry='infinity'] The expiry date if a page is being watched temporarily.
	 * @param {string} [expirySelected='infinite'] The expiry length that was just selected from a dropdown, e.g. '1 week'
	 */
	function notifyPageWatchStatus( isWatched, expiry, expirySelected ) {
		expiry = expiry || 'infinity';
		expirySelected = expirySelected || 'infinite';

		mw.hook( 'wikipage.watchlistChange' ).fire(
			isWatched,
			expiry,
			expirySelected
		);
	}

	/**
	 * Update the page watch status
	 *
	 * @param {boolean} isWatched The page is watched
	 * @param {string} [expiry='infinity'] The expiry date if a page is being watched temporarily.
	 * @param {string} [expirySelected='infinite'] The expiry length that was just selected from a dropdown, e.g. '1 week'
	 */
	function updatePageWatchStatus( isWatched, expiry, expirySelected ) {
		// Update all watchstars associated with the current page
		( watchstarsByTitle[ pageTitle ] || [] ).forEach( function ( w ) {
			w.update( isWatched, expiry );
		} );

		notifyPageWatchStatus( isWatched, expiry, expirySelected );
	}

	/**
	 * Update the link text, link href attribute and (if applicable) "loading" class.
	 *
	 * For an individual link being set to 'loading', the first
	 * argument can be a jQuery collection. When updating to a
	 * "idle" state, an mw.Title object should be passed to that
	 * all watchstars associated with that title are updated.
	 *
	 * @param {mw.Title|jQuery} titleOrLink Title of watchlinks to update (when state is idle), or an individual watchlink
	 * @param {string} action One of 'watch', 'unwatch'
	 * @param {string} [state="idle"] 'idle' or 'loading'. Default is 'idle'
	 * @param {string} [expiry='infinity'] The expiry date if a page is being watched temporarily.
	 * @param {string} [expirySelected='infinite'] The expiry length that was just selected from a dropdown, e.g. '1 week'
	 */
	function updateWatchLink( titleOrLink, action, state, expiry, expirySelected ) {
		if ( titleOrLink instanceof $ ) {
			updateWatchLinkAttributes( titleOrLink, action, state, expiry );
		} else {
			// Assumed state is 'idle' when update a group of watchstars by title
			var isWatched = action === 'unwatch';
			var normalizedTitle = titleOrLink.getPrefixedDb();
			( watchstarsByTitle[ normalizedTitle ] || [] ).forEach( function ( w ) {
				w.update( isWatched, expiry, expirySelected );
			} );
			if ( normalizedTitle === pageTitle ) {
				notifyPageWatchStatus( isWatched, expiry, expirySelected );
			}
		}
	}

	/**
	 * TODO: This should be moved somewhere more accessible.
	 *
	 * @private
	 * @param {string} url
	 * @return {string} The extracted action, defaults to 'view'
	 */
	function mwUriGetAction( url ) {
		// TODO: Does MediaWiki give action path or query param
		// precedence? If the former, move this to the bottom
		var action = mw.util.getParamValue( 'action', url );
		if ( action !== null ) {
			return action;
		}

		var actionPaths = mw.config.get( 'wgActionPaths' );
		for ( var key in actionPaths ) {
			var parts = actionPaths[ key ].split( '$1' );
			parts = parts.map( mw.util.escapeRegExp );
			var m = new RegExp( parts.join( '(.+)' ) ).exec( url );
			if ( m && m[ 1 ] ) {
				return key;
			}
		}

		return 'view';
	}

	/**
	 * @private
	 */
	function init() {
		var $pageWatchLinks = $( '.mw-watchlink a[data-mw="interface"], a.mw-watchlink[data-mw="interface"]' );
		if ( !$pageWatchLinks.length ) {
			// Fallback to the class-based exclusion method for backwards-compatibility
			$pageWatchLinks = $( '.mw-watchlink a, a.mw-watchlink' );
			// Restrict to core interfaces, ignore user-generated content
			$pageWatchLinks = $pageWatchLinks.filter( ':not( #bodyContent *, #content * )' );
		}
		if ( $pageWatchLinks.length ) {
			watchstar( $pageWatchLinks, pageTitle );
		}
	}

	/**
	 * Class representing an individual watchstar
	 *
	 * @class mw.plugin.page.watch.ajax.Watchstar
	 * @constructor
	 * @param {jQuery} $link Watch element
	 * @param {mw.Title} title Title
	 * @param {Function} [callback] Callback to run when updating
	 */
	function Watchstar( $link, title, callback ) {
		this.$link = $link;
		this.title = title;
		this.callback = callback;
	}

	/**
	 * Update the watchstar
	 *
	 * @param {boolean} isWatched The page is watched
	 * @param {string} [expiry='infinity'] The expiry date if a page is being watched temporarily.
	 */
	Watchstar.prototype.update = function ( isWatched, expiry ) {
		expiry = expiry || 'infinity';
		updateWatchLinkAttributes( this.$link, isWatched ? 'unwatch' : 'watch', 'idle', expiry );
		if ( this.callback ) {
			this.callback( this.$link, isWatched, expiry );
		}
	};

	/**
	 * Bind a given watchstar element to make it interactive.
	 *
	 * NOTE: This is meant to allow binding of watchstars for arbitrary page titles,
	 * especially if different from the currently viewed page. As such, this function
	 * will *not* synchronise its state with any "Watch this page" checkbox such as
	 * found on the "Edit page" and "Publish changes" forms. The caller should either make
	 * "current page" watchstars picked up by #init (and not use #watchstar) sync it manually
	 * from the callback #watchstar provides.
	 *
	 * @param {jQuery} $links One or more anchor elements that must have an href
	 *  with a url containing a `action=watch` or `action=unwatch` query parameter,
	 *  from which the current state will be learned (e.g. link to unwatch is currently watched)
	 * @param {string} title Title of page that this watchstar will affect
	 * @param {Function} [callback] Callback to run after the action has been processed and API
	 *  request completed. The callback receives two parameters:
	 * @param {jQuery} callback.$link The element being manipulated
	 * @param {boolean} callback.isWatched Whether the article is now watched
	 * @param {string} callback.expiry The expiry date if a page is being watched temporarily.
	 */
	function watchstar( $links, title, callback ) {
		// Set up the ARIA connection between the watch link and the notification.
		// This is set outside the click handler so that it's already present when the user clicks.
		var notificationId = 'mw-watchlink-notification';
		var mwTitle = mw.Title.newFromText( title );

		if ( !mwTitle ) {
			return;
		}

		var normalizedTitle = mwTitle.getPrefixedDb();
		watchstarsByTitle[ normalizedTitle ] = watchstarsByTitle[ normalizedTitle ] || [];

		$links.each( function () {
			watchstarsByTitle[ normalizedTitle ].push(
				new Watchstar( $( this ), mwTitle, callback )
			);
		} );

		$links.attr( 'aria-controls', notificationId );

		// Add click handler.
		$links.on( 'click', function ( e ) {
			var action = mwUriGetAction( this.href );

			if ( !mwTitle || ( action !== 'watch' && action !== 'unwatch' ) ) {
				// Let native browsing handle the link
				return true;
			}
			e.preventDefault();
			e.stopPropagation();

			var $link = $( this );

			// eslint-disable-next-line no-jquery/no-class-state
			if ( $link.hasClass( 'loading' ) ) {
				return;
			}

			updateWatchLinkAttributes( $link, action, 'loading' );

			// Preload the notification module for mw.notify
			var modulesToLoad = [ 'mediawiki.notification' ];

			// Preload watchlist expiry widget so it runs in parallel with the api call
			if ( isWatchlistExpiryEnabled ) {
				modulesToLoad.push( 'mediawiki.watchstar.widgets' );
			}

			mw.loader.load( modulesToLoad );

			var api = new mw.Api();
			api[ action ]( title )
				.done( function ( watchResponse ) {
					var isWatched = watchResponse.watched === true;

					var message;
					if ( mwTitle.isTalkPage() ) {
						message = isWatched ? 'addedwatchtext-talk' : 'removedwatchtext-talk';
					} else {
						message = isWatched ? 'addedwatchtext' : 'removedwatchtext';
					}

					var notifyPromise;
					var watchlistPopup;
					// @since 1.35 - pop up notification will be loaded with OOUI
					// only if Watchlist Expiry is enabled
					if ( isWatchlistExpiryEnabled ) {

						if ( isWatched ) { // The message should include `infinite` watch period
							message = mwTitle.isTalkPage() ? 'addedwatchindefinitelytext-talk' : 'addedwatchindefinitelytext';
						}

						notifyPromise = mw.loader.using( 'mediawiki.watchstar.widgets' ).then( function ( require ) {
							var WatchlistExpiryWidget = require( 'mediawiki.watchstar.widgets' );

							if ( !watchlistPopup ) {
								watchlistPopup = new WatchlistExpiryWidget(
									action,
									title,
									updateWatchLink,
									{
										// The following messages can be used here:
										// * addedwatchindefinitelytext-talk
										// * addedwatchindefinitelytext
										// * removedwatchtext-talk
										// * removedwatchtext
										message: mw.message( message, mwTitle.getPrefixedText() ).parseDom(),
										$link: $link
									} );
							}

							mw.notify( watchlistPopup.$element, {
								tag: 'watch-self',
								id: notificationId,
								autoHideSeconds: 'short'
							} );
						} );
					} else {
						// The following messages can be used here:
						// * addedwatchtext-talk
						// * addedwatchtext
						// * removedwatchtext-talk
						// * removedwatchtext
						notifyPromise = mw.notify(
							mw.message( message, mwTitle.getPrefixedText() ).parseDom(), {
								tag: 'watch-self',
								id: notificationId
							}
						);
					}

					// The notifications are stored as a promise and the watch link is only updated
					// once it is resolved. Otherwise, if $wgWatchlistExpiry set, the loading of
					// OOUI could cause a race condition and the link is updated before the popup
					// actually is shown. See T263135
					notifyPromise.then( function () {

						// Update all watchstars associated with this title
						watchstarsByTitle[ normalizedTitle ].forEach( function ( w ) {
							w.update( isWatched );
						} );

						// For the current page, also trigger the hook
						if ( normalizedTitle === pageTitle ) {
							notifyPageWatchStatus( isWatched );
						}
					} );
				} )
				.fail( function ( code, data ) {
					// Reset link to non-loading mode
					updateWatchLinkAttributes( $link, action );

					// Format error message
					var $msg = api.getErrorMessage( data );

					// Report to user about the error
					mw.notify( $msg, {
						tag: 'watch-self',
						type: 'error',
						id: notificationId
					} );
				} );
		} );
	}

	$( init );

	// Expose public methods.
	module.exports = {
		watchstar: watchstar,
		updateWatchLink: updateWatchLink,
		updatePageWatchStatus: updatePageWatchStatus
	};

}() );
