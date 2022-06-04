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
 * @cfg {string} [icon=''] Symbolic name of an icon, e.g. "puzzle" or "wikiText"
 * @cfg {string} label
 * @cfg {string} ariaDescriptionUnselected
 * @cfg {string} ariaDescriptionSelected
 * @cfg {string} ariaDescriptionSelectedSingle
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
			click: 'onHeaderClick'
		} );

	if ( config.ariaDescriptionUnselected &&
		config.ariaDescriptionSelected &&
		config.ariaDescriptionSelectedSingle
	) {
		this.$ariaDescriptionUnselected = $( '<span>' )
			.text( config.ariaDescriptionUnselected )
			.addClass( 've-ui-mwTransclusionOutline-ariaHidden' );

		this.$ariaDescriptionSelected = $( '<span>' )
			.text( config.ariaDescriptionSelected )
			.addClass( 've-ui-mwTransclusionOutline-ariaHidden' );

		this.$ariaDescriptionSelectedSingle = $( '<span>' )
			.text( config.ariaDescriptionSelectedSingle )
			.addClass( 've-ui-mwTransclusionOutline-ariaHidden' );

		this.header.setAriaDescribedBy( this.$ariaDescriptionUnselected );
		this.header.$element.prepend(
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
 * @event transclusionPartSoftSelected
 * @param {string} partId Unique id of the {@see ve.dm.MWTransclusionPartModel}, e.g. something like
 *  "part_1".
 */

/**
 * "Hard" selection with enter or mouse click.
 *
 * @event transclusionPartSelected
 * @param {string} pageName Unique id of the {@see OO.ui.BookletLayout} page, e.g. something like
 *  "part_1" or "part_1/param1".
 */

/* Methods */

/**
 * @private
 * @param {number} key
 * @fires transclusionPartSoftSelected
 */
ve.ui.MWTransclusionOutlinePartWidget.prototype.onHeaderKeyPressed = function ( key ) {
	if ( key === OO.ui.Keys.SPACE ) {
		this.emit( 'transclusionPartSoftSelected', this.getData() );
	}
};

/**
 * @protected
 * @fires transclusionPartSelected
 */
ve.ui.MWTransclusionOutlinePartWidget.prototype.onHeaderClick = function () {
	this.emit( 'transclusionPartSelected', this.getData() );
};

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
	this.updateButtonAriaDescription( state );
	this.header
		.setSelected( state )
		.setFlags( { progressive: state } );
};

/**
 * @private
 * @param {boolean} state
 */
ve.ui.MWTransclusionOutlinePartWidget.prototype.updateButtonAriaDescription = function ( state ) {
	if ( !this.$ariaDescriptionUnselected ||
		!this.$ariaDescriptionSelected ||
		!this.$ariaDescriptionSelectedSingle
	) {
		return;
	}

	this.header.setAriaDescribedBy( !state ? this.$ariaDescriptionUnselected :
		( this.transclusionModel.isSingleTemplate() ? this.$ariaDescriptionSelectedSingle : this.$ariaDescriptionSelected )
	);
};
