( function () {
	/**
	 * Sorted list widget. This is a group widget that sorts its items
	 * according to a given sorting callback.
	 *
	 * @class
	 * @extends OO.ui.Widget
	 * @mixes OO.SortedEmitterList
	 *
	 * @constructor
	 * @param {Function} sortingCallback Callback that compares two items.
	 * @param {Object} [config] Configuration options
	 * @param {jQuery} [config.$group] The container element created by the class. If this configuration
	 *  is omitted, the group element will use a generated `<div>`.
	 * @param {jQuery} [config.$overlay] A jQuery element functioning as an overlay
	 *  for popups.
	 * @param {number} [config.timestamp=0] A fallback timestamp for the list, usually representing
	 *  the timestamp of the latest item.
	 * @param {boolean} [config.animated=false] Animate the sorting of items
	 */
	mw.echo.ui.SortedListWidget = function MwEchoUiSortedListWidget( sortingCallback, config ) {
		config = config || {};

		// Parent constructor
		mw.echo.ui.SortedListWidget.super.call( this, config );
		// Mixin constructor
		OO.SortedEmitterList.call( this, sortingCallback );

		// Properties
		this.$group = null;
		this.$overlay = config.$overlay;
		this.timestamp = config.timestamp || 0;

		this.animated = !!config.animated;

		// Initialization
		this.setGroupElement( config.$group || $( '<div>' ) );

		this.$group.addClass( 'mw-echo-ui-sortedListWidget-group' );
		this.$element
			.addClass( 'mw-echo-ui-sortedListWidget' )
			.append( this.$group );
	};

	/* Initialization */

	OO.inheritClass( mw.echo.ui.SortedListWidget, OO.ui.Widget );
	OO.mixinClass( mw.echo.ui.SortedListWidget, OO.SortedEmitterList );

	/* Methods */

	/**
	 * @inheritdoc
	 */
	mw.echo.ui.SortedListWidget.prototype.onItemSortChange = function ( item ) {
		if ( this.animated ) {
			// Create a fake widget with cloned contents
			const fakeWidget = new mw.echo.ui.ClonedNotificationItemWidget(
				item.$element.clone( true ),
				{
					id: item.getId() + '.42',
					// HACK: We are assuming that the item sort change
					// is triggered when the item is marked read/unread
					// This is a generally correct assumption, but it may
					// cause issues when the case is unclear. We should try
					// and come up with a good way to measure the previous
					// state of the item instead
					read: !item.isRead(),
					foreign: item.isForeign(),
					timestamp: item.getTimestamp()
				}
			);

			// remove real item from item list, without touching the DOM
			this.removeItems( [ item ] );

			// insert real item, hidden
			item.$element.hide();
			this.addItems( [ item, fakeWidget ] );

			// fade out fake
			// FIXME: Use CSS transition
			// eslint-disable-next-line no-jquery/no-fade
			fakeWidget.$element.fadeOut( 400, () => {
				// remove fake
				this.removeItems( [ fakeWidget ] );
				// fade-in real item
				// eslint-disable-next-line no-jquery/no-fade
				item.$element.fadeIn( 400 );
			} );
		} else {
			// Mixin method
			OO.SortedEmitterList.prototype.onItemSortChange.call( this, item );
		}
	};
	/**
	 * Set the group element.
	 *
	 * If an element is already set, items will be moved to the new element.
	 *
	 * @param {jQuery} $group Element to use as group
	 */
	mw.echo.ui.SortedListWidget.prototype.setGroupElement = function ( $group ) {
		this.$group = $group;
		for ( let i = 0, len = this.items.length; i < len; i++ ) {
			this.$group.append( this.items[ i ].$element );
		}
	};

	/**
	 * Get an item by its id.
	 *
	 * @param {number} id Item id to search for
	 * @return {OO.ui.Element|null} Item with equivalent data, `null` if none exists
	 */
	mw.echo.ui.SortedListWidget.prototype.getItemFromId = function ( id ) {
		const hash = OO.getHash( id );

		for ( let i = 0, len = this.items.length; i < len; i++ ) {
			const item = this.items[ i ];
			if ( hash === OO.getHash( item.getId() ) ) {
				return item;
			}
		}

		return null;
	};

	/**
	 * Get an item by its data.
	 *
	 * @param {string} data Item data to search for
	 * @return {OO.ui.Element|null} Item with equivalent data, `null` if none exists
	 */
	mw.echo.ui.SortedListWidget.prototype.findItemFromData = function ( data ) {
		const hash = OO.getHash( data );

		for ( let i = 0, len = this.items.length; i < len; i++ ) {
			const item = this.items[ i ];
			if ( hash === OO.getHash( item.getData() ) ) {
				return item;
			}
		}

		return null;
	};

	/**
	 * Remove items.
	 *
	 * @param {OO.EventEmitter[]} items Items to remove
	 * @chainable
	 * @return {mw.echo.ui.SortedListWidget}
	 * @fires OO.EmitterList#remove
	 */
	mw.echo.ui.SortedListWidget.prototype.removeItems = function ( items ) {
		if ( !Array.isArray( items ) ) {
			items = [ items ];
		}

		if ( items.length > 0 ) {
			// Remove specific items
			for ( let i = 0; i < items.length; i++ ) {
				const item = items[ i ];
				const index = this.items.indexOf( item );
				if ( index !== -1 ) {
					item.setElementGroup( null );
					item.$element.detach();
				}
			}
		}

		return OO.SortedEmitterList.prototype.removeItems.call( this, items );
	};

	/**
	 * Utility method to insert an item into the list, and
	 * connect it to aggregate events.
	 *
	 * Don't call this directly unless you know what you're doing.
	 * Use #addItems instead.
	 *
	 * @private
	 * @param {OO.EventEmitter} item Items to add
	 * @param {number} index Index to add items at
	 * @return {number} The index the item was added at
	 */
	mw.echo.ui.SortedListWidget.prototype.insertItem = function ( item, index ) {
		// Call parent and get the normalized index
		index = OO.SortedEmitterList.prototype.insertItem.call( this, item, index );

		item.setElementGroup( this );

		this.attachItemToDom( item, index );

		return index;
	};

	/**
	 * Move an item from its current position to a new index.
	 *
	 * The item is expected to exist in the list. If it doesn't,
	 * the method will throw an exception.
	 *
	 * @private
	 * @param {OO.EventEmitter} item Items to add
	 * @param {number} index Index to move the item to
	 * @return {number} The index the item was moved to
	 * @throws {Error} If item is not in the list
	 */
	mw.echo.ui.SortedListWidget.prototype.moveItem = function ( item, index ) {
		// Call parent and get the normalized index
		index = OO.SortedEmitterList.prototype.moveItem.call( this, item, index );

		this.attachItemToDom( item, index );

		return index;
	};

	/**
	 * Attach the item to the Dom in its intended position, based
	 * on the given index.
	 *
	 * @param {OO.EventEmitter} item Item
	 * @param {number} index Index to insert the item into
	 */
	mw.echo.ui.SortedListWidget.prototype.attachItemToDom = function ( item, index ) {
		if ( index === undefined || index < 0 || index >= this.items.length - 1 ) {
			this.$group.append( item.$element.get( 0 ) );
		} else if ( index === 0 ) {
			this.$group.prepend( item.$element.get( 0 ) );
		} else {
			this.items[ index + 1 ].$element.before( item.$element.get( 0 ) );
		}
	};

	/**
	 * Clear all items
	 *
	 * @chainable
	 * @return {mw.echo.ui.SortedListWidget}
	 * @fires OO.EmitterList#clear
	 */
	mw.echo.ui.SortedListWidget.prototype.clearItems = function () {
		for ( let i = 0, len = this.items.length; i < len; i++ ) {
			const item = this.items[ i ];
			item.setElementGroup( null );
			item.$element.detach();
		}

		// Mixin method
		return OO.SortedEmitterList.prototype.clearItems.call( this );
	};

	/**
	 * Get the timestamp of the list by taking the latest notification
	 * timestamp.
	 *
	 * @return {string} Latest timestamp
	 */
	mw.echo.ui.SortedListWidget.prototype.getTimestamp = function () {
		const items = this.getItems();

		return (
			items.length > 0 ?
				items[ 0 ].getTimestamp() :
				this.timestamp
		);
	};

}() );
