/**
 * @param {Function} handler A deterministic asynchronous function taking a string and returning Any
 * @return {Function} Memoized version (returns the original promise on subsequent calls)
 */
mw.editcheck.memoize = function ( handler ) {
	const memory = new Map();
	return ( arg, bypass ) => {
		if ( typeof arg !== 'string' ) {
			throw new Error( 'Argument must be a string' );
		}

		if ( bypass || !memory.has( arg ) ) {
			memory.set( arg, handler( arg ) );
		}
		return memory.get( arg );
	};
};

mw.editcheck.fetchTimeout = function ( resource, options = {} ) {
	// eslint-disable-next-line compat/compat
	const abortController = window.AbortController ? new AbortController() :
		{ signal: undefined, abort: () => {} };
	const timeoutID = setTimeout( () => abortController.abort(), options.timeout || 6000 );

	options.signal = abortController.signal;
	return fetch( resource, options ).then( ( response ) => {
		clearTimeout( timeoutID );
		return response;
	} ).catch( ( error ) => {
		clearTimeout( timeoutID );
		if ( error instanceof DOMException && error.name === 'AbortError' ) {
			throw new Error( `fetch failed: ${ resource }` );
		}
		throw error;
	} );
};

/**
 * Fetch pages that are expected to be JSON from the mediawiki API
 *
 * @param {string[]} pagenames
 * @return {mw.Api~AbortablePromise} Resolves to a Map of pagename to parsed JSON
 */
mw.editcheck.getMediaWikiJSON = function ( pagenames ) {
	// TODO: we *could* enforce that these be `MediaWiki:*.json`
	return new mw.Api().get( {
		action: 'query',
		format: 'json',
		prop: 'revisions',
		titles: pagenames.join( '|' ),
		formatversion: '2',
		rvprop: 'content'
	} ).then( ( response ) => {
		const pageMap = new Map();
		const pages = response.query.pages || [];
		pages.forEach( ( page ) => {
			if ( !page || !page.revisions ) {
				mw.log.warn( ' Could not fetch imported config: ' + page.title );
				return;
			}
			try {
				pageMap.set( page.title, JSON.parse( page.revisions[ 0 ].content ) );
			} catch ( err ) {
				mw.log.error( ' Failed to parse imported config: ' + page.title, err );
			}
		} );
		return pageMap;
	} );
};

/**
 * Add click tracking to all links in an element
 *
 * @param {jQuery} $element Element containing links
 * @param {string} name Name of the edit check
 * @param {string} action Action name for tracking
 */
mw.editcheck.trackActionLinks = function ( $element, name, action ) {
	$element.find( 'a' ).on( 'click', () => {
		ve.track( 'activity.editCheck-' + name, { action } );
	} );
};

/**
 * Polyfill for Promise.allSettled
 *
 * @param {Promise[]} promises
 * @return {Promise}
 */
mw.editcheck.allSettled = function ( promises ) {
	/* eslint-disable es-x/no-promise-all-settled */
	if ( Promise.allSettled ) {
		return Promise.allSettled( promises );
	}
	/* eslint-enable es-x/no-promise-all-settled */
	return Promise.all( promises.map( ( promise ) => Promise.resolve( promise ).then(
		( value ) => ( {
			status: 'fulfilled',
			value
		} ),
		( reason ) => ( {
			status: 'rejected',
			reason
		} )
	) ) );
};

/**
 * Once all promises are settled, return only the results of resolved promises
 *
 * @param {Array} promises
 * @return {Promise}
 */
mw.editcheck.allSettledFulfilledOnly = function ( promises ) {
	return mw.editcheck.allSettled( promises ).then( ( results ) => {
		const fulfilled = [];
		for ( const result of results ) {
			if ( result.status === 'fulfilled' ) {
				fulfilled.push( result.value );
			}
		}
		return fulfilled;
	} );
};

/**
 * Polyfill for Array.prototype.flat
 *
 * @param {Array} arr
 * @param {number} [depth = 1]
 * @return {Array}
 */
mw.editcheck.flattenArray = function ( arr, depth = 1 ) {
	const result = [];
	// Stack entries are [ array, currentDepth ]
	const stack = [ [ arr, 0 ] ];

	while ( stack.length > 0 ) {
		const [ current, d ] = stack.pop();

		for ( let i = current.length - 1; i >= 0; i-- ) {
			const item = current[ i ];
			if ( Array.isArray( item ) && d < depth ) {
				stack.push( [ item, d + 1 ] );
			} else {
				result.push( item );
			}
		}
	}

	return result;
};
