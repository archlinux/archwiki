/**
 * Common base class for top-level items (a.k.a. "parts") in the template editor sidebar. Subclasses
 * should exist for all subclasses of {@see ve.dm.MWTransclusionPartModel}:
 * - {@see ve.dm.MWTemplateModel}
 * - {@see ve.dm.MWTemplatePlaceholderModel}
 * - {@see ve.dm.MWTransclusionContentModel}
 *
 * This is inspired by and meant to replace {@see OO.ui.DecoratedOptionWidget} in the context of the
 * template dialog. Also see {@see OO.ui.ButtonWidget} for inspiration.
 *
 * @abstract
 * @class
 * @extends OO.ui.Widget
 *
 * @constructor
 * @param {ve.dm.MWTransclusionPartModel} part
 * @param {Object} config
 * @cfg {string} [icon='']
 * @cfg {string} label
 */
ve.ui.MWTransclusionOutlinePartWidget = function VeUiMWTransclusionOutlinePartWidget( part, config ) {
	// Parent constructor
	ve.ui.MWTransclusionOutlinePartWidget.super.call( this, ve.extendObject( config, {
		classes: [ 've-ui-mwTransclusionOutlinePartWidget' ],
		data: part.getId()
	} ) );

	this.header = new ve.ui.MWTransclusionOutlineButtonWidget( config )
		.connect( this, {
			spacePressed: [ 'emit', 'transclusionPartSoftSelected', part.getId() ],
			click: [ 'emit', 'transclusionPartSelected', part.getId() ]
		} );

	this.$element
		.append( this.header.$element );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionOutlinePartWidget, OO.ui.Widget );

/* Events */

/**
 * "Soft" selection with space.
 *
 * @event transclusionPartSoftSelected
 * @param {string} partId Unique id of the {@see ve.dm.MWTransclusionPartModel}, e.g. something like
 *  "part_1".
 */

/**
 * "Hard" selection with enter or mouse click.
 *
 * @event transclusionPartSelected
 * @param {string} partId Unique id of the {@see ve.dm.MWTransclusionPartModel}, e.g. something like
 *  "part_1".
 */

/* Methods */

/**
 * Convenience method, modelled after {@see OO.ui.OptionWidget}, but this isn't one.
 *
 * @return {boolean}
 */
ve.ui.MWTransclusionOutlinePartWidget.prototype.isSelected = function () {
	return this.header.isSelected();
};

/**
 * Convenience method, modelled after {@see OO.ui.OptionWidget}, but this isn't one.
 *
 * @param {boolean} state
 */
ve.ui.MWTransclusionOutlinePartWidget.prototype.setSelected = function ( state ) {
	this.header
		.setSelected( state )
		.setFlags( { progressive: state } );
};
