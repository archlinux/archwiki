/*!
 * VisualEditor user interface MWParameterPlaceholderPage class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki transclusion dialog parameter placeholder page.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {ve.dm.MWParameterModel} parameter Reference to a placeholder parameter with an empty
 *  name, as well as to the template the parameter belongs to
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @cfg {jQuery} [$overlay] Overlay to render dropdowns in
 * @cfg {boolean} [expandedParamList=false] If the {@see ve.ui.MWParameterSearchWidget} results
 *  should be initially expanded
 */
ve.ui.MWParameterPlaceholderPage = function VeUiMWParameterPlaceholderPage( parameter, name, config ) {
	var veConfig = mw.config.get( 'wgVisualEditorConfig' );

	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWParameterPlaceholderPage.super.call( this, name, config );

	// Properties
	// TODO: the unique `name` seems to be a relic of when BookletLayout held
	// the parameters in separate pages rather than in a StackLayout.
	this.name = name;
	this.parameter = parameter;
	this.template = this.parameter.getTemplate();
	this.addParameterSearch = new ve.ui.MWParameterSearchWidget( this.template, {
		showAll: !!config.expandedParamList
	} )
		.connect( this, {
			choose: 'onParameterChoose',
			showAll: 'onParameterShowAll'
		} );

	this.addParameterFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'visualeditor-dialog-transclusion-add-param' ),
		icon: 'parameter',
		classes: [ 've-ui-mwTransclusionDialog-addParameterFieldset' ],
		$content: this.addParameterSearch.$element
	} );

	this.addParameterFieldset.$element.attr( 'aria-label', ve.msg( 'visualeditor-dialog-transclusion-add-param' ) );

	// Initialization
	this.$element
		.addClass( 've-ui-mwParameterPlaceholderPage' )
		.append( this.addParameterFieldset.$element );

	if ( !veConfig.transclusionDialogNewSidebar ) {
		var removeButton = new OO.ui.ButtonWidget( {
			framed: false,
			icon: 'trash',
			title: ve.msg( 'visualeditor-dialog-transclusion-remove-param' ),
			flags: [ 'destructive' ],
			classes: [ 've-ui-mwTransclusionDialog-removeButton' ]
		} )
			.connect( this, { click: 'onRemoveButtonClick' } );

		this.$element.append( removeButton.$element );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWParameterPlaceholderPage, OO.ui.PageLayout );

/* Methods */

/**
 * Respond to the parameter search widget showAll event
 *
 * @private
 * @fires showAll
 */
ve.ui.MWParameterPlaceholderPage.prototype.onParameterShowAll = function () {
	this.emit( 'showAll', this.name );
};

/**
 * @inheritdoc
 */
ve.ui.MWParameterPlaceholderPage.prototype.setupOutlineItem = function () {
	this.outlineItem
		.setIcon( 'parameter' )
		.setMovable( false )
		.setRemovable( true )
		.setLevel( 1 )
		.setFlags( [ 'placeholder' ] )
		.setLabel( ve.msg( 'visualeditor-dialog-transclusion-add-param' ) );
};

/**
 * @private
 * @param {string} name
 * @fires focusTemplateParameterById
 */
ve.ui.MWParameterPlaceholderPage.prototype.onParameterChoose = function ( name ) {
	this.addParameterSearch.query.setValue( '' );

	if ( !name || this.template.hasParameter( name ) ) {
		return;
	}

	// Note that every parameter is known after it is added
	var knownBefore = this.template.getSpec().isKnownParameterOrAlias( name );

	var param = new ve.dm.MWParameterModel( this.template, name );
	this.template.addParameter( param );
	this.emit( 'focusTemplateParameterById', param.getId() );

	ve.track( 'activity.transclusion', {
		action: knownBefore ? 'add-known-parameter' : 'add-unknown-parameter'
	} );
};

/**
 * @private
 */
ve.ui.MWParameterPlaceholderPage.prototype.onRemoveButtonClick = function () {
	this.parameter.remove();
};
