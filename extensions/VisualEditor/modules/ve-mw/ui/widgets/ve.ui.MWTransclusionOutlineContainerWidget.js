/*!
 * VisualEditor user interface MWTransclusionOutlineContainerWidget class.
 *
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Container for transclusion, may contain a single or multiple templates.
 *
 * @class
 * @extends OO.ui.Widget
 *
 * @constructor
 * @property {Object.<string,ve.ui.MWTransclusionOutlinePartWidget>} partWidgets Map of top-level
 *  items currently visible in this container, indexed by part id
 */
ve.ui.MWTransclusionOutlineContainerWidget = function VeUiMWTransclusionOutlineContainerWidget() {
	// Parent constructor
	ve.ui.MWTransclusionOutlineContainerWidget.super.call( this, {
		classes: [ 've-ui-mwTransclusionOutlineContainerWidget' ]
	} );

	// Initialization
	this.partWidgets = {};
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionOutlineContainerWidget, OO.ui.Widget );

/* Events */

/**
 * @event filterPagesByName
 * @param {Object.<string,boolean>} visibility Keyed by unique id of the {@see OO.ui.BookletLayout}
 *  page, e.g. something like "part_1/param1".
 */

/**
 * @event focusPageByName
 * @param {string} pageName Unique id of the {@see OO.ui.BookletLayout} page, e.g. something like
 *  "part_1" or "part_1/param1".
 */

/**
 * @event updateOutlineControlButtons
 * @param {string} pageName Unique id of the {@see OO.ui.BookletLayout} page, e.g. something like
 *  "part_1" or "part_1/param1".
 */

/**
 * @param {ve.dm.MWTransclusionPartModel|null} removed Removed part
 * @param {ve.dm.MWTransclusionPartModel|null} added Added part
 * @param {number} [newPosition]
 */
ve.ui.MWTransclusionOutlineContainerWidget.prototype.onReplacePart = function ( removed, added, newPosition ) {
	if ( removed ) {
		this.removePartWidget( removed );
	}
	// TODO: reselect if active part was in a removed template

	if ( added ) {
		this.addPartWidget( added, newPosition );
	}
};

/**
 * @param {ve.dm.MWTransclusionModel} transclusionModel
 */
ve.ui.MWTransclusionOutlineContainerWidget.prototype.onTransclusionModelChange = function ( transclusionModel ) {
	var newOrder = transclusionModel.getParts();

	for ( var i = 0; i < newOrder.length; i++ ) {
		var expectedWidget = this.partWidgets[ newOrder[ i ].getId() ],
			$expectedElement = expectedWidget && expectedWidget.$element,
			$currentElement = this.$element.children().eq( i );

		if ( !$currentElement.is( $expectedElement ) ) {
			// Move each widget to the correct position if it wasn't there before
			$currentElement.before( $expectedElement );
		}
	}
};

/**
 * @private
 * @param {string} partId
 * @fires focusPageByName
 */
ve.ui.MWTransclusionOutlineContainerWidget.prototype.onTransclusionPartSelected = function ( partId ) {
	this.selectPartById( partId );
	this.emit( 'focusPageByName', partId );
};

/* Methods */

/**
 * @private
 * @param {ve.dm.MWTransclusionPartModel} part
 */
ve.ui.MWTransclusionOutlineContainerWidget.prototype.removePartWidget = function ( part ) {
	var id = part.getId();
	if ( id in this.partWidgets ) {
		this.partWidgets[ id ]
			.disconnect( this )
			.$element.remove();
		delete this.partWidgets[ id ];
	}
};

/**
 * @private
 * @param {ve.dm.MWTransclusionPartModel} part
 * @param {number} [newPosition]
 * @fires filterPagesByName
 */
ve.ui.MWTransclusionOutlineContainerWidget.prototype.addPartWidget = function ( part, newPosition ) {
	var widget;

	if ( part instanceof ve.dm.MWTemplateModel ) {
		widget = new ve.ui.MWTransclusionOutlineTemplateWidget( part );
		// This forwards events from the nested ve.ui.MWTransclusionOutlineTemplateWidget upwards.
		// The array syntax is a way to call `this.emit( 'filterParameters' )`.
		widget.connect( this, {
			// We can forward these events as is. The parameter's unique ids are reused as page
			// names in {@see ve.ui.MWTemplateDialog.onAddParameter}.
			focusTemplateParameterById: [ 'emit', 'focusPageByName' ],
			filterParametersById: [ 'emit', 'filterPagesByName' ]
		} );
	} else if ( part instanceof ve.dm.MWTemplatePlaceholderModel ) {
		widget = new ve.ui.MWTransclusionOutlinePlaceholderWidget( part );
	} else if ( part instanceof ve.dm.MWTransclusionContentModel ) {
		widget = new ve.ui.MWTransclusionOutlineWikitextWidget( part );
	}

	widget.connect( this, {
		transclusionPartSoftSelected: 'selectPartById',
		transclusionPartSelected: 'onTransclusionPartSelected'
	} );

	this.partWidgets[ part.getId() ] = widget;
	if ( typeof newPosition === 'number' && newPosition < this.$element.children().length ) {
		this.$element.children().eq( newPosition ).before( widget.$element );
	} else {
		this.$element.append( widget.$element );
	}
};

/**
 * This is inspired by {@see OO.ui.SelectWidget.selectItem}, but isn't one.
 *
 * @param {string} partId Top-level part id, e.g. "part_1". Note this (currently) doesn't accept
 *  parameter ids like "part_1/param1".
 */
ve.ui.MWTransclusionOutlineContainerWidget.prototype.selectPartById = function ( partId ) {
	for ( var id in this.partWidgets ) {
		this.partWidgets[ id ].setSelected( id === partId );
	}
	this.emit( 'updateOutlineControlButtons', partId );
};

/**
 * @param {string} pageName
 */
ve.ui.MWTransclusionOutlineContainerWidget.prototype.highlightSubItemByPageName = function ( pageName ) {
	var partId = pageName.split( '/', 1 )[ 0 ],
		partWidget = this.partWidgets[ partId ];
	// Note this code-path (currently) doesn't care about top-level parts
	if ( partWidget instanceof ve.ui.MWTransclusionOutlineTemplateWidget &&
		pageName.length > partId.length
	) {
		var paramName = pageName.slice( partId.length + 1 );
		partWidget.highlightParameter( paramName );
	}
};

/**
 * This is inspired by {@see OO.ui.SelectWidget.findSelectedItem}, but isn't one.
 *
 * @return {string|undefined}
 */
ve.ui.MWTransclusionOutlineContainerWidget.prototype.findSelectedPartId = function () {
	for ( var id in this.partWidgets ) {
		var part = this.partWidgets[ id ];
		if ( part.isSelected() ) {
			return part.getData();
		}
	}
};

/**
 * Removes all {@see ve.ui.MWTransclusionOutlinePartWidget}, i.e. empties the list.
 */
ve.ui.MWTransclusionOutlineContainerWidget.prototype.clear = function () {
	for ( var id in this.partWidgets ) {
		this.partWidgets[ id ]
			.disconnect( this )
			.$element.remove();
	}
	this.partWidgets = {};
};
