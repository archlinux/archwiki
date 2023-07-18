/**
 * createMemoryStorage creates a wrapper around mw.SafeStorage objects, duplicating
 * their contents in memory, so that even if the underlying storage mechanism
 * fails (e.g. quota exceeded), the storage can be relied on before the
 * page has been reloaded.
 *
 * @example
 * var sessionStorage = createMemoryStorage( mw.storage.session );
 * var localStorage = createMemoryStorage( mw.storage );
 *
 * @param {mw.SafeStorage} storage
 * @return {MemoryStorage}
 */
var createMemoryStorage = function ( storage ) {

	/**
	 * @class
	 * @extends mw.SafeStorage
	 *
	 * @constructor
	 * @param {Storage|undefined} store The Storage instance to wrap around
	 */
	function MemoryStorage( store ) {
		this.data = {};

		// Attempt to populate memory cache with existing data.
		// Ignore any errors accessing native Storage object, as in mw.SafeStorage.
		try {
			for ( var i = 0, l = store.length; i < l; i++ ) {
				var key = store.key( i );
				this.data[ key ] = store.getItem( key );
			}
		} catch ( e ) {}

		// Parent constructor
		MemoryStorage.super.apply( this, arguments );
	}

	/* Inheritance */

	var ParentStorage = storage.constructor;
	OO.inheritClass( MemoryStorage, ParentStorage );

	/* Methods */

	/**
	 * @inheritdoc
	 */
	MemoryStorage.prototype.get = function ( key ) {
		if ( Object.prototype.hasOwnProperty.call( this.data, key ) ) {
			return this.data[ key ];
		} else {
			// Parent method
			return MemoryStorage.super.prototype.get.apply( this, arguments );
		}
	};

	/**
	 * @inheritdoc
	 */
	MemoryStorage.prototype.set = function ( key, value ) {
		// Parent method
		MemoryStorage.super.prototype.set.apply( this, arguments );

		this.data[ key ] = value;
		return true;
	};

	/**
	 * @inheritdoc
	 */
	MemoryStorage.prototype.remove = function ( key ) {
		// Parent method
		MemoryStorage.super.prototype.remove.apply( this, arguments );

		delete this.data[ key ];
		return true;
	};

	return new MemoryStorage( storage.store );
};

module.exports = createMemoryStorage;
