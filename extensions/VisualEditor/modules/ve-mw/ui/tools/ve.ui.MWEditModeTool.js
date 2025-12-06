/*!
 * VisualEditor MediaWiki UserInterface edit mode tool classes.
 *
 * Used for making edit mode switcher tools within VE.
 *
 * When building a toolbar for use outside of VE you can use
 * the mw.libs.ve.MWEditModeTool classes.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki UserInterface edit mode tool.
 *
 * @class
 * @abstract
 */
ve.ui.MWEditModeTool = function VeUiMWEditModeTool() {
};

/* Inheritance */

OO.initClass( ve.ui.MWEditModeTool );

/* Methods */

/**
 * @inheritdoc mw.libs.ve.MWEditModeTool
 */
ve.ui.MWEditModeTool.prototype.getMode = function () {
	if ( !this.toolbar.getSurface() ) {
		return 'source';
	}
	return this.toolbar.getSurface().getMode();
};

/**
 * @inheritdoc mw.libs.ve.MWEditModeTool
 */
ve.ui.MWEditModeTool.prototype.isModeAvailable = function ( mode ) {
	const target = this.toolbar.getTarget();
	if ( !target.getSurface() ) {
		// Disable switching before surface is loaded
		return false;
	}
	if ( target.getSurface().getModel().isMultiUser() ) {
		// Disable switching in multi-user mode
		return false;
	}
	if ( mode === 'source' ) {
		// A fallback source mode should always available (e.g. EditPage.php)
		return true;
	}
	return target.isModeAvailable( mode );
};

/**
 * MediaWiki UserInterface edit mode visual tool.
 *
 * @class
 * @extends mw.libs.ve.MWEditModeVisualTool
 * @mixes ve.ui.MWEditModeTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Config options
 */
ve.ui.MWEditModeVisualTool = function VeUiMWEditModeVisualTool() {
	// Parent constructor
	ve.ui.MWEditModeVisualTool.super.apply( this, arguments );
	// Mixin constructor
	ve.ui.MWEditModeTool.call( this );
};
OO.inheritClass( ve.ui.MWEditModeVisualTool, mw.libs.ve.MWEditModeVisualTool );
OO.mixinClass( ve.ui.MWEditModeVisualTool, ve.ui.MWEditModeTool );

/**
 * @inheritdoc
 */
ve.ui.MWEditModeVisualTool.prototype.switch = function () {
	this.toolbar.getTarget().switchToVisualEditor();
};
ve.ui.toolFactory.register( ve.ui.MWEditModeVisualTool );

/**
 * MediaWiki UserInterface edit mode source tool.
 *
 * @class
 * @extends mw.libs.ve.MWEditModeSourceTool
 * @mixes ve.ui.MWEditModeTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Config options
 */
ve.ui.MWEditModeSourceTool = function VeUiMWEditModeSourceTool() {
	// Parent constructor
	ve.ui.MWEditModeSourceTool.super.apply( this, arguments );
	// Mixin constructor
	ve.ui.MWEditModeTool.call( this );
};
OO.inheritClass( ve.ui.MWEditModeSourceTool, mw.libs.ve.MWEditModeSourceTool );
OO.mixinClass( ve.ui.MWEditModeSourceTool, ve.ui.MWEditModeTool );
/**
 * @inheritdoc
 */
ve.ui.MWEditModeSourceTool.prototype.switch = function () {
	this.toolbar.getTarget().switchToWikitextEditor();
};
ve.ui.toolFactory.register( ve.ui.MWEditModeSourceTool );
