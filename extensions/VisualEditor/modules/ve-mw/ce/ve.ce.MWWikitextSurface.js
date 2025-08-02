/*!
 * VisualEditor ContentEditable MWWikitextSurface class.
 *
 * @copyright See AUTHORS.txt
 */

/**
 * @class
 * @extends ve.ce.Surface
 *
 * @constructor
 * @param {ve.dm.Surface} model
 * @param {ve.ui.Surface} ui
 * @param {Object} [config]
 */
ve.ce.MWWikitextSurface = function VeCeMwWikitextSurface() {
	// Parent constructors
	ve.ce.MWWikitextSurface.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.MWWikitextSurface, ve.ce.Surface );

/**
 * @inheritdoc
 */
ve.ce.MWWikitextSurface.prototype.createClipboardHandler = function () {
	return new ve.ce.MWWikitextClipboardHandler( this );
};
