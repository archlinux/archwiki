/**
 * Common base class for top-level items (a.k.a. "parts") in the template editor sidebar. Subclasses
 * should exist for all subclasses of {@link ve.dm.MWTransclusionPartModel}:
 * - {@link ve.dm.MWTemplateModel}
 * - {@link ve.dm.MWTemplatePlaceholderModel}
 * - {@link ve.dm.MWTransclusionContentModel}
 *
 * This is inspired by and meant to replace {@link OO.ui.DecoratedOptionWidget} in the context of the
 * template dialog. Also see {@link OO.ui.ButtonWidget} for inspiration.
 *
 * @abstract
 * @class
 * @extends OO.ui.Widget
 *
 * @constructor
 * @param {ve.dm.MWTransclusionPartModel} part
 * @param {Object} config
 * @param {string} [config.icon=''] Symbolic name of an icon, e.g. "puzzle" or "wikiText"
 * @param {string} config.label
 * @param {string} config.ariaDescriptionUnselected
 * @param {string} config.ariaDescriptionSelected
 * @param {string} config.ariaDescriptionSelectedSingle
 */
ve.ui.MWTransclusionOutlinePartWidget = function VeUiMWTransclusionOutlinePartWidget( part, config ) {
	this.part = part;

	// Parent constructor
	ve.ui.MWTransclusionOutlinePartWidget.super.call( this, ve.extendObject( config, {
		classes: [ 've-ui-mwTransclusionOutlinePartWidget' ],
		data: part.getId()
	} ) );

	this.header = new ve.ui.MWTransclusionOutlineButtonWidget( config )
		.connect( this, {
			keyPressed: 'onHeaderKeyPressed',
			// The array syntax is a way to call `this.emit( 'transclusionOutlineItemSelected', … )`.
			click: [ 'emit', 'transclusionOutlineItemSelected', part.getId() ]
		} );

	if ( config.ariaDescriptionUnselected ) {
		this.$ariaDescriptionUnselected = $( '<span>' )
			.text( config.ariaDescriptionUnselected )
			.addClass( 've-ui-mwTransclusionOutline-ariaHidden' );

		this.$ariaDescriptionSelected = $( '<span>' )
			.text( config.ariaDescriptionSelected )
			.addClass( 've-ui-mwTransclusionOutline-ariaHidden' );

		this.$ariaDescriptionSelectedSingle = $( '<span>' )
			.text( config.ariaDescriptionSelectedSingle )
			.addClass( 've-ui-mwTransclusionOutline-ariaHidden' );

		this.header
			.setAriaDescribedBy( this.$ariaDescriptionUnselected )
			.$element.prepend(
				this.$ariaDescriptionUnselected,
				this.$ariaDescriptionSelected,
				this.$ariaDescriptionSelectedSingle
			);
	}

	this.transclusionModel = this.part.getTransclusion().connect( this, {
		replace: 'updateButtonAriaDescription'
	} );

	this.$element.append( this.header.$element );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionOutlinePartWidget, OO.ui.Widget );

/* Events */

/**
 * "Soft" selection with space.
 *
 * @event ve.ui.MWTransclusionOutlinePartWidget#transclusionPartSoftSelected
 * @param {string} partId Unique id of the {@link ve.dm.MWTransclusionPartModel}, e.g. something like
 *  "part_1".
 */

/**
 * Triggered when the user interacts with any sidebar element in a meaningful way, and that should
 * be reflected in the content pane of the dialog. This includes e.g. selecting something that was
 * already selected.
 *
 * @event ve.ui.MWTransclusionOutlinePartWidget#transclusionOutlineItemSelected
 * @param {string} pageName Unique id of the {@link OO.ui.BookletLayout} page, e.g. something like
 *  "part_1" or "part_1/param1".
 * @param {boolean} [soft] If true, focus should stay in the sidebar. Defaults to false.
 */

/* Methods */

/**
 * @private
 * @param {number} key
 * @fires ve.ui.MWTransclusionOutlinePartWidget#transclusionPartSoftSelected
 */
ve.ui.MWTransclusionOutlinePartWidget.prototype.onHeaderKeyPressed = function ( key ) {
	if ( key === OO.ui.Keys.SPACE ) {
		this.emit( 'transclusionPartSoftSelected', this.getData() );
	}
};

/**
 * Convenience method, modelled after {@link OO.ui.OptionWidget}, but this isn't one.
 *
 * @return {boolean}
 */
ve.ui.MWTransclusionOutlinePartWidget.prototype.isSelected = function () {
	return this.header.isSelected();
};

/**
 * Convenience method, modelled after {@link OO.ui.OptionWidget}, but this isn't one.
 *
 * @param {boolean} state
 */
ve.ui.MWTransclusionOutlinePartWidget.prototype.setSelected = function ( state ) {
	if ( state !== this.isSelected() ) {
		this.updateButtonAriaDescription( state );
		this.header
			.setSelected( state )
			.setFlags( { progressive: state } );
	}
};

/**
 * @private
 * @param {boolean} state
 */
ve.ui.MWTransclusionOutlinePartWidget.prototype.updateButtonAriaDescription = function ( state ) {
	this.header.setAriaDescribedBy( !state ? this.$ariaDescriptionUnselected :
		( this.transclusionModel.isSingleTemplate() ? this.$ariaDescriptionSelectedSingle : this.$ariaDescriptionSelected )
	);
};
