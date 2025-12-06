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
		// If we want to specifically handle the abort case, check for an AbortError here
		throw error;
	} );
};
