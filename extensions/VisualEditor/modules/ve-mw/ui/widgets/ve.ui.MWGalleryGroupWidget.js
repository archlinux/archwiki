/*!
 * VisualEditor user interface MWGalleryGroupWidget class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Draggable group widget for reordering images in the MWGalleryDialog.
 *
 * @class
 * @extends OO.ui.Widget
 * @mixes OO.ui.mixin.DraggableGroupElement
 *
 * @constructor
 * @param {Object} [config] Configuration options
 * @param {string} [config.orientation='vertical']
 */
ve.ui.MWGalleryGroupWidget = function VeUiMWGalleryGroupWidget( config = {} ) {
	// Parent constructor
	ve.ui.MWGalleryGroupWidget.super.apply( this, arguments );

	// Mixin constructors
	OO.ui.mixin.DraggableGroupElement.call( this, ve.extendObject( {}, config, { $group: this.$element } ) );

	// Events
	this.aggregate( {
		edit: 'editItem'
	} );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWGalleryGroupWidget, OO.ui.Widget );

OO.mixinClass( ve.ui.MWGalleryGroupWidget, OO.ui.mixin.DraggableGroupElement );

/* Events */

/**
 * @event ve.ui.MWGalleryGroupWidget#editItem
 */
