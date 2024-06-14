'use strict';
var EXPIRY_PREFIX = '_EXPIRY_';

/**
 * @classdesc
 * A wrapper for the HTML5 Storage interface (`localStorage` or `sessionStorage`)
 * that is safe to call in all browsers.
 *
 * The constructor is not publicly accessible. An instance can be accessed from
 * {@link mw.storage} or {@link module:mediawiki.storage}.
 *
 * @class
 * @param {Object|undefined} store The Storage instance to wrap around
 * @hideconstructor
 * @memberof module:mediawiki.storage
 * @inner
 */
function SafeStorage( store ) {
	this.store = store;

	// Purge expired items once per page session
	if ( !window.QUnit ) {
		var storage = this;
		setTimeout( function () {
			storage.clearExpired();
		}, 2000 );
	}
}

/**
 * Retrieve value from device storage.
 *
 * @param {string} key Key of item to retrieve
 * @return {string|null|boolean} String value, null if no value exists, or false
 *  if storage is not available.
 */
SafeStorage.prototype.get = function ( key ) {
	if ( this.isExpired( key ) ) {
		return null;
	}
	try {
		return this.store.getItem( key );
	} catch ( e ) {}
	return false;
};

/**
 * Set a value in device storage.
 *
 * @param {string} key Key name to store under
 * @param {string} value Value to be stored
 * @param {number} [expiry] Number of seconds after which this item can be deleted
 * @return {boolean} The value was set
 */
SafeStorage.prototype.set = function ( key, value, expiry ) {
	if ( key.slice( 0, EXPIRY_PREFIX.length ) === EXPIRY_PREFIX ) {
		throw new Error( 'Key can\'t have a prefix of ' + EXPIRY_PREFIX );
	}
	// Compare to `false` instead of checking falsiness to tolerate subclasses and mocks in
	// extensions that weren't updated to add a return value to setExpires().
	if ( this.setExpires( key, expiry ) === false ) {
		// If we failed to set the expiration time, don't try to set the value,
		// otherwise it might end up set with no expiration.
		return false;
	}
	try {
		this.store.setItem( key, value );
		return true;
	} catch ( e ) {}
	return false;
};

/**
 * Remove a value from device storage.
 *
 * @param {string} key Key of item to remove
 * @return {boolean} Whether the key was removed
 */
SafeStorage.prototype.remove = function ( key ) {
	try {
		this.store.removeItem( key );
		this.setExpires( key );
		return true;
	} catch ( e ) {}
	return false;
};

/**
 * Retrieve JSON object from device storage.
 *
 * @param {string} key Key of item to retrieve
 * @return {Object|null|boolean} Object, null if no value exists or value
 *  is not JSON-parseable, or false if storage is not available.
 */
SafeStorage.prototype.getObject = function ( key ) {
	var json = this.get( key );

	if ( json === false ) {
		return false;
	}

	try {
		return JSON.parse( json );
	} catch ( e ) {}

	return null;
};

/**
 * Set an object value in device storage by JSON encoding.
 *
 * @param {string} key Key name to store under
 * @param {Object} value Object value to be stored
 * @param {number} [expiry] Number of seconds after which this item can be deleted
 * @return {boolean} The value was set
 */
SafeStorage.prototype.setObject = function ( key, value, expiry ) {
	var json;
	try {
		json = JSON.stringify( value );
		return this.set( key, json, expiry );
	} catch ( e ) {}
	return false;
};

/**
 * Set the expiry time for an item in the store.
 *
 * @param {string} key Key name
 * @param {number} [expiry] Number of seconds after which this item can be deleted,
 *  omit to clear the expiry (either making the item never expire, or to clean up
 *  when deleting a key).
 * @return {boolean} The expiry was set (or cleared) [since 1.41]
 */
SafeStorage.prototype.setExpires = function ( key, expiry ) {
	if ( expiry ) {
		try {
			this.store.setItem(
				EXPIRY_PREFIX + key,
				Math.floor( Date.now() / 1000 ) + expiry
			);
			return true;
		} catch ( e ) {}
	} else {
		try {
			this.store.removeItem( EXPIRY_PREFIX + key );
			return true;
		} catch ( e ) {}
	}
	return false;
};

// Minimum amount of time (in milliseconds) for an iteration involving localStorage access.
var MIN_WORK_TIME = 3;

/**
 * Clear any expired items from the store
 *
 * @private
 * @return {jQuery.Promise} Resolves when items have been expired
 */
SafeStorage.prototype.clearExpired = function () {
	var storage = this;
	return this.getExpiryKeys().then( function ( keys ) {
		return $.Deferred( function ( d ) {
			mw.requestIdleCallback( function iterate( deadline ) {
				while ( keys[ 0 ] !== undefined && deadline.timeRemaining() > MIN_WORK_TIME ) {
					var key = keys.shift();
					if ( storage.isExpired( key ) ) {
						storage.remove( key );
					}
				}
				if ( keys[ 0 ] !== undefined ) {
					// Ran out of time with keys still to remove, continue later
					mw.requestIdleCallback( iterate );
				} else {
					return d.resolve();
				}
			} );
		} );
	} );
};

/**
 * Get all keys with expiry values
 *
 * @private
 * @return {jQuery.Promise} Promise resolving with all the keys which have
 *  expiry values (unprefixed), or as many could be retrieved in the allocated time.
 */
SafeStorage.prototype.getExpiryKeys = function () {
	var store = this.store;
	return $.Deferred( function ( d ) {
		mw.requestIdleCallback( function ( deadline ) {
			var prefixLength = EXPIRY_PREFIX.length;
			var keys = [];
			var length = 0;
			try {
				length = store.length;
			} catch ( e ) {}

			// Optimization: If time runs out, degrade to checking fewer keys.
			// We will get another chance during a future page view. Iterate forward
			// so that older keys are checked first and increase likelihood of recovering
			// from key exhaustion.
			//
			// We don't expect to have more keys than we can handle in 50ms long-task window.
			// But, we might still run out of time when other tasks run before this,
			// or when the device receives UI events (especially on low-end devices).
			for ( var i = 0; ( i < length && deadline.timeRemaining() > MIN_WORK_TIME ); i++ ) {
				var key = null;
				try {
					key = store.key( i );
				} catch ( e ) {}
				if ( key !== null && key.slice( 0, prefixLength ) === EXPIRY_PREFIX ) {
					keys.push( key.slice( prefixLength ) );
				}
			}
			d.resolve( keys );
		} );
	} ).promise();
};

/**
 * Check if a given key has expired
 *
 * @private
 * @param {string} key Key name
 * @return {boolean} Whether key is expired
 */
SafeStorage.prototype.isExpired = function ( key ) {
	var expiry;
	try {
		expiry = this.store.getItem( EXPIRY_PREFIX + key );
	} catch ( e ) {
		return false;
	}
	return !!expiry && expiry < Math.floor( Date.now() / 1000 );
};

module.exports = SafeStorage;
