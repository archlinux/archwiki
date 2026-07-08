/*!
 * VisualEditor UserInterface MWCategoryWidget class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWCategoryWidget object.
 *
 * @class
 * @abstract
 * @extends OO.ui.Widget
 * @mixes OO.ui.mixin.GroupElement
 * @mixes OO.ui.mixin.DraggableGroupElement
 *
 * @constructor
 * @param {Object} [config] Configuration options
 * @param {jQuery} [config.$overlay] Overlay to render dropdowns in
 */
ve.ui.MWCategoryWidget = function VeUiMWCategoryWidget( config ) {
	// Parent constructor
	ve.ui.MWCategoryWidget.super.call( this, config );

	// Mixin constructors
	OO.ui.mixin.GroupElement.call( this, config );
	OO.ui.mixin.DraggableGroupElement.call( this, ve.extendObject( {}, config, { orientation: 'horizontal' } ) );

	const categoryNamespace = mw.config.get( 'wgNamespaceIds' ).category;
	// Properties
	this.fragment = null;
	this.categories = {};
	// Source -> target
	this.categoryRedirects = {};
	// Title cache - will contain entries even if title is already normalized
	this.normalizedTitles = {};
	this.popup = new ve.ui.MWCategoryPopupWidget();
	this.input = new ve.ui.MWCategoryInputWidget( this, { $overlay: config.$overlay } );
	this.forceCapitalization = !mw.config.get( 'wgCaseSensitiveNamespaces' ).includes( categoryNamespace );
	this.categoryPrefix = mw.config.get( 'wgFormattedNamespaces' )[ categoryNamespace ] + ':';
	this.expandedItem = null;

	// Events
	this.input.connect( this, { choose: 'onInputChoose' } );
	this.popup.connect( this, {
		removeCategory: 'onRemoveCategory',
		updateSortkey: 'onUpdateSortkey',
		ready: 'onPopupOpened',
		closing: 'onPopupClosing'
	} );
	this.connect( this, {
		drag: 'onDrag'
	} );

	// Initialization
	this.$element.addClass( 've-ui-mwCategoryWidget' )
		.append(
			this.$group.addClass( 've-ui-mwCategoryWidget-items' ).append(
				this.input.$element
			),
			this.popup.$element,
			$( '<div>' ).css( 'clear', 'both' )
		);
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCategoryWidget, OO.ui.Widget );

OO.mixinClass( ve.ui.MWCategoryWidget, OO.ui.mixin.GroupElement );
OO.mixinClass( ve.ui.MWCategoryWidget, OO.ui.mixin.DraggableGroupElement );

/* Events */

/**
 * @event ve.ui.MWCategoryWidget#newCategory
 * @param {Object} item Category item
 * @param {string} item.name Fully prefixed category name
 * @param {string} item.value Category value (name without prefix)
 * @param {ve.dm.MWCategoryMetaItem} item.metaItem
 * @param {ve.dm.MetaItem} [beforeCategory] Insert after this category; if unset, insert at the end
 */

/**
 * @event ve.ui.MWCategoryWidget#updateSortkey
 * @param {Object} item Category item
 * @param {string} item.name Fully prefixed category name
 * @param {string} item.value Category value (name without prefix)
 * @param {ve.dm.MWCategoryMetaItem} item.metaItem
 */

/* Methods */

/**
 * Surface fragment for modifying meta list
 *
 * @param {ve.dm.SurfaceFragment|null} fragment Surface fragment
 */
ve.ui.MWCategoryWidget.prototype.setFragment = function ( fragment ) {
	this.fragment = fragment;
};

/**
 * Handle input 'choose' event.
 *
 * @param {OO.ui.MenuOptionWidget} item Selected item
 */
ve.ui.MWCategoryWidget.prototype.onInputChoose = function ( item ) {
	const value = item.getData();

	if ( value && value !== '' ) {
		// Add new item
		const categoryItem = this.getCategoryItemFromValue( value );
		this.queryCategoryStatus( [ categoryItem.name ] ).then( () => {
			// Remove existing items by name
			const toRemove = mw.Title.newFromText( categoryItem.name ).getMainText();
			if ( Object.prototype.hasOwnProperty.call( this.categories, toRemove ) ) {
				this.fragment.removeMeta( this.categories[ toRemove ].metaItem );
			}
			categoryItem.name = this.normalizedTitles[ categoryItem.name ];
			this.emit( 'newCategory', categoryItem );
		} );
	}
};

/**
 * Hanle popup open event
 *
 */
ve.ui.MWCategoryWidget.prototype.onPopupOpened = function () {
	this.popup.removeButton.focus();
};

/**
 * Handle popup closing dialog
 */
ve.ui.MWCategoryWidget.prototype.onPopupClosing = function () {
	this.expandedItem.focus();
};

/**
 * Get a category item.
 *
 * @param {string} value Category name
 * @return {Object} Category item with name, value and metaItem properties
 */
ve.ui.MWCategoryWidget.prototype.getCategoryItemFromValue = function ( value ) {
	// Normalize
	const title = mw.Title.newFromText( this.categoryPrefix + value );
	if ( title ) {
		return {
			name: title.getPrefixedText(),
			value: title.getMainText(),
			metaItem: {}
		};
	}

	if ( this.forceCapitalization ) {
		value = value.slice( 0, 1 ).toUpperCase() + value.slice( 1 );
	}

	return {
		name: this.categoryPrefix + value,
		value,
		metaItem: {}
	};
};

/**
 * Focus the widget
 */
ve.ui.MWCategoryWidget.prototype.focus = function () {
	this.input.$input[ 0 ].focus();
};

/**
 * @param {ve.ui.MWCategoryItemWidget} item Item that was moved
 * @param {number} newIndex The new index of the item
 */
ve.ui.MWCategoryWidget.prototype.onDrag = function () {
	this.fitInput();
};

/**
 * @inheritdoc OO.ui.mixin.DraggableGroupElement
 * @fires ve.ui.MWCategoryWidget#newCategory
 */
ve.ui.MWCategoryWidget.prototype.reorder = function ( item, newIndex ) {
	// Compute beforeCategory before removing, otherwise newIndex
	// could be off by one
	const beforeCategory = this.items[ newIndex ] && this.items[ newIndex ].metaItem;
	if ( Object.prototype.hasOwnProperty.call( this.categories, item.value ) ) {
		this.fragment.removeMeta( this.categories[ item.value ].metaItem );
	}

	this.emit( 'newCategory', item, beforeCategory );
};

/**
 * Removes category from model.
 *
 * @param {string} name Removed category name
 */
ve.ui.MWCategoryWidget.prototype.onRemoveCategory = function ( name ) {
	this.fragment.removeMeta( this.categories[ name ].metaItem );
	delete this.categories[ name ];
};

/**
 * Update sortkey value, emit updateSortkey event
 *
 * @param {string} name
 * @param {string} value
 * @fires ve.ui.MWCategoryWidget#updateSortkey
 */
ve.ui.MWCategoryWidget.prototype.onUpdateSortkey = function ( name, value ) {
	this.categories[ name ].sortKey = value;
	this.emit( 'updateSortkey', this.categories[ name ] );
};

/**
 * @inheritdoc
 */
ve.ui.MWCategoryWidget.prototype.clearItems = function () {
	OO.ui.mixin.GroupElement.prototype.clearItems.call( this );
	this.categories = {};
};

/**
 * Toggles popup menu per category item
 *
 * @param {Object} item
 */
ve.ui.MWCategoryWidget.prototype.onTogglePopupMenu = function ( item ) {
	// Close open popup.
	if ( item.value !== this.popup.category ) {
		this.popup.openPopup( item );
		this.expandedItem = item;
		this.popup
			.$element
			.attr( 'aria-label',
				ve.msg( 'visualeditor-dialog-meta-categories-category' )
			);
	} else {
		// Handle toggle
		this.popup.closePopup();
	}
};

/**
 * Set the default sort key.
 *
 * @param {string} value Default sort key value
 */
ve.ui.MWCategoryWidget.prototype.setDefaultSortKey = function ( value ) {
	this.popup.setDefaultSortKey( value );
};

/**
 * @inheritdoc
 */
ve.ui.MWCategoryWidget.prototype.setDisabled = function () {
	// Parent method
	ve.ui.MWCategoryWidget.super.prototype.setDisabled.apply( this, arguments );

	const isDisabled = this.isDisabled();

	if ( this.input ) {
		this.input.setDisabled( isDisabled );
	}
	if ( this.items ) {
		this.items.forEach( ( item ) => {
			item.setDisabled( isDisabled );
		} );
	}
	if ( this.popup ) {
		this.popup.closePopup();
	}
};

/**
 * Get list of category names.
 *
 * @return {string[]} List of category names
 */
ve.ui.MWCategoryWidget.prototype.getCategories = function () {
	return Object.keys( this.categories );
};

/**
 * Starts a request to update the link cache's hidden and missing status for
 *  the given titles, following normalisation responses as necessary.
 *
 * @param {string[]} categoryNames
 * @return {jQuery.Promise}
 */
ve.ui.MWCategoryWidget.prototype.queryCategoryStatus = function ( categoryNames ) {
	// Get rid of any we already know the hidden status of, or have an entry
	// if normalizedTitles (i.e. have been fetched before)
	const categoryNamesToQuery = categoryNames.filter( ( name ) => {
		if ( this.normalizedTitles[ name ] ) {
			return false;
		}
		const cacheEntry = ve.init.platform.linkCache.getCached( name );
		if ( cacheEntry && cacheEntry.hidden ) {
			// As we aren't doing an API request for this category, mark it in the cache.
			this.normalizedTitles[ name ] = name;
			return false;
		}
		return true;
	} );

	if ( !categoryNamesToQuery.length ) {
		return ve.createDeferred().resolve( {} ).promise();
	}

	let index = 0;
	const batchSize = 50, promises = [];
	// Batch this up into groups of 50
	while ( index < categoryNamesToQuery.length ) {
		promises.push( ve.init.target.getContentApi().get( {
			action: 'query',
			prop: 'pageprops',
			titles: categoryNamesToQuery.slice( index, index + batchSize ),
			ppprop: 'hiddencat',
			redirects: ''
		} ).then( ( result ) => {
			const linkCacheUpdate = {},
				normalizedTitles = {};
			if ( result && result.query && result.query.pages ) {
				result.query.pages.forEach( ( pageInfo ) => {
					linkCacheUpdate[ pageInfo.title ] = {
						missing: Object.prototype.hasOwnProperty.call( pageInfo, 'missing' ),
						hidden: pageInfo.pageprops &&
							Object.prototype.hasOwnProperty.call( pageInfo.pageprops, 'hiddencat' )
					};
				} );
			}
			if ( result && result.query && result.query.redirects ) {
				result.query.redirects.forEach( ( redirectInfo ) => {
					this.categoryRedirects[ redirectInfo.from ] = redirectInfo.to;
				} );
			}
			ve.init.platform.linkCache.set( linkCacheUpdate );

			if ( result.query && result.query.normalized ) {
				result.query.normalized.forEach( ( normalisation ) => {
					normalizedTitles[ normalisation.from ] = normalisation.to;
				} );
			}

			categoryNames.forEach( ( name ) => {
				this.normalizedTitles[ name ] = normalizedTitles[ name ] || name;
			} );
		} ) );
		index += batchSize;
	}

	return ve.promiseAll( promises );
};

/**
 * Adds category items.
 *
 * @param {Object[]} items Items to add
 * @param {number} [index] Index to insert items after
 * @return {jQuery.Promise}
 */
ve.ui.MWCategoryWidget.prototype.addItems = function ( items, index ) {
	const categoryItems = [],
		// eslint-disable-next-line no-jquery/no-map-util
		categoryNames = $.map( items, ( item ) => item.name );

	return this.queryCategoryStatus( categoryNames ).then( () => {
		let config;
		const checkValueMatches = function ( existingCategoryItem ) {
			return config.item.value === existingCategoryItem.value;
		};

		items.forEach( ( item ) => {
			item.name = this.normalizedTitles[ item.name ];

			const itemTitle = new mw.Title( item.name, mw.config.get( 'wgNamespaceIds' ).category );
			// Create a widget using the item data
			config = { item };
			let cachedData;
			if ( Object.prototype.hasOwnProperty.call( this.categoryRedirects, itemTitle.getPrefixedText() ) ) {
				config.redirectTo = new mw.Title(
					this.categoryRedirects[ itemTitle.getPrefixedText() ],
					mw.config.get( 'wgNamespaceIds' ).category
				).getMainText();
				cachedData = ve.init.platform.linkCache.getCached( this.categoryRedirects[ itemTitle.getPrefixedText() ] );
			} else {
				cachedData = ve.init.platform.linkCache.getCached( item.name );
			}
			config.hidden = cachedData.hidden;
			config.missing = cachedData.missing;
			config.disabled = this.disabled;

			const categoryItem = new ve.ui.MWCategoryItemWidget( config );
			categoryItem.connect( this, {
				togglePopupMenu: 'onTogglePopupMenu'
			} );

			// Index item
			this.categories[ itemTitle.getMainText() ] = categoryItem;
			// Copy sortKey from old item when "moving"
			const existingCategoryItems = this.items.filter( checkValueMatches );
			if ( existingCategoryItems.length ) {
				// There should only be one element in existingCategoryItems
				categoryItem.sortKey = existingCategoryItems[ 0 ].sortKey;
				this.removeItems( [ existingCategoryItems[ 0 ] ] );
			}

			categoryItems.push( categoryItem );
		} );

		OO.ui.mixin.DraggableGroupElement.prototype.addItems.call( this, categoryItems, index );

		// Ensure the input remains the last item in the list, and preserve focus
		const hadFocus = this.getElementDocument().activeElement === this.input.$input[ 0 ];
		this.$group.append( this.input.$element );
		if ( hadFocus ) {
			this.input.$input[ 0 ].focus();
		}
		this.fitInput();
	} );
};

/**
 * @inheritdoc
 */
ve.ui.MWCategoryWidget.prototype.removeItems = function ( items ) {
	for ( let i = 0, len = items.length; i < len; i++ ) {
		const categoryItem = items[ i ];
		if ( categoryItem ) {
			categoryItem.disconnect( this );
			items.push( categoryItem );
			delete this.categories[ categoryItem.value ];
		}
	}

	OO.ui.mixin.DraggableGroupElement.prototype.removeItems.call( this, items );

	this.fitInput();
};

/**
 * Auto-fit the input.
 */
ve.ui.MWCategoryWidget.prototype.fitInput = function () {
	const $input = this.input.$element;

	// eslint-disable-next-line no-jquery/no-sizzle
	if ( !this.items.length || !$input.is( ':visible' ) ) {
		return;
	}

	// Measure the input's natural size
	$input.css( 'width', '' );
	const inputWidth = $input.outerWidth( true );

	// this.items hasn't been updated if this was triggered by a drag event,
	// so look at document order
	const $lastItem = this.$group.find( '.ve-ui-mwCategoryItemWidget' ).last();
	// Try to fit to the right of the last item
	const availableSpace = Math.floor( this.$group.width() - ( $lastItem.position().left + $lastItem.outerWidth( true ) ) );
	if ( availableSpace > inputWidth ) {
		$input.css( 'width', availableSpace );
	}
};
