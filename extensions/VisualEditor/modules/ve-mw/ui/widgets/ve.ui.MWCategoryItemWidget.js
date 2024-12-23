/*!
 * VisualEditor UserInterface MWCategoryItemWidget class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWCategoryItemWidget object.
 *
 * @class
 * @abstract
 * @extends OO.ui.ButtonWidget
 * @mixes OO.ui.mixin.DraggableElement
 *
 * @constructor
 * @param {Object} config
 * @param {Object} config.item Category item
 * @param {string} config.item.name Category name
 * @param {string} config.item.value
 * @param {string} [config.item.sortKey='']
 * @param {ve.dm.MWCategoryMetaItem} config.item.metaItem
 * @param {boolean} [config.hidden] Whether the category is hidden or not
 * @param {boolean} [config.missing] Whether the category's description page is missing
 * @param {string} [config.redirectTo] The name of the category this category's page redirects to.
 */
ve.ui.MWCategoryItemWidget = function VeUiMWCategoryItemWidget( config ) {
	// Config initialization
	config = ve.extendObject( { indicator: 'down' }, config );

	// Parent constructor
	ve.ui.MWCategoryItemWidget.super.call( this, config );

	// Mixin constructors
	OO.ui.mixin.DraggableElement.call( this, config );

	// Properties
	this.name = config.item.name;
	this.value = config.item.value;
	this.sortKey = config.item.sortKey || '';
	this.metaItem = config.item.metaItem;
	this.isHidden = config.hidden;
	this.isMissing = config.missing;

	// Initialization
	this.setLabel( config.redirectTo || this.value );
	if ( config.redirectTo ) {
		ve.init.platform.linkCache.styleElement( mw.Title.newFromText(
			config.redirectTo,
			mw.config.get( 'wgNamespaceIds' ).category
		).getPrefixedText(), this.$label );
	} else {
		ve.init.platform.linkCache.styleElement( this.name, this.$label );
	}

	// Events
	this.on( 'click', this.onButtonClick.bind( this ) );

	this.$element.addClass( 've-ui-mwCategoryItemWidget' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCategoryItemWidget, OO.ui.ButtonWidget );

OO.mixinClass( ve.ui.MWCategoryItemWidget, OO.ui.mixin.DraggableElement );

/* Events */

/**
 * @event ve.ui.MWCategoryItemWidget#togglePopupMenu
 * @param {ve.ui.MWCategoryItemWidget} item Item to load into popup
 */

/* Methods */

/**
 * Handle button widget click events.
 *
 * @fires ve.ui.MWCategoryItemWidget#togglePopupMenu
 */
ve.ui.MWCategoryItemWidget.prototype.onButtonClick = function () {
	this.emit( 'togglePopupMenu', this );
};
