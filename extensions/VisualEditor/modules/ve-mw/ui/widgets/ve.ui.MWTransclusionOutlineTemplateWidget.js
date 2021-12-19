/*!
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Container for template, as rendered in the template dialog sidebar.
 *
 * @class
 * @extends ve.ui.MWTransclusionOutlinePartWidget
 *
 * @constructor
 * @param {ve.dm.MWTemplateModel} template
 */
ve.ui.MWTransclusionOutlineTemplateWidget = function VeUiMWTransclusionOutlineTemplateWidget( template ) {
	var spec = template.getSpec();

	// Parent constructor
	ve.ui.MWTransclusionOutlineTemplateWidget.super.call( this, template, {
		icon: 'puzzle',
		label: spec.getLabel()
	} );

	// Initialization
	this.templateModel = template.connect( this, {
		add: 'onParameterAddedToTemplateModel',
		remove: 'onParameterRemovedFromTemplateModel'
	} );

	var parameterNames = this.templateModel
		.getAllParametersOrdered()
		.filter( function ( paramName ) {
			if ( spec.isParameterDeprecated( paramName ) && !template.hasParameter( paramName ) ) {
				return false;
			}
			// Don't create a checkbox for ve.ui.MWParameterPlaceholderPage
			return paramName;
		} );

	this.searchWidget = new OO.ui.SearchInputWidget( {
		placeholder: ve.msg( 'visualeditor-dialog-transclusion-filter-placeholder' ),
		classes: [ 've-ui-mwTransclusionOutlineTemplateWidget-searchWidget' ]
	} ).connect( this, {
		change: 'filterParameters'
	} ).toggle( parameterNames.length );
	this.infoWidget = new OO.ui.LabelWidget( {
		label: new OO.ui.HtmlSnippet( ve.msg( 'visualeditor-dialog-transclusion-filter-no-match' ) ),
		classes: [ 've-ui-mwTransclusionOutlineTemplateWidget-no-match' ]
	} ).toggle( false );

	this.parameters = new ve.ui.MWTransclusionOutlineParameterSelectWidget( {
		items: parameterNames.map( this.createCheckbox.bind( this ) )
	} )
		.connect( this, {
			choose: 'onTemplateParameterChoose',
			templateParameterSelectionChanged: 'onTemplateParameterSelectionChanged',
			// Note that choose implies focus, but not the other way around
			templateParameterClick: 'onTemplateParameterClick',
			change: 'onParameterWidgetListChanged'
		} );

	this.$element.append(
		this.searchWidget.$element,
		this.infoWidget.$element,
		this.parameters.$element
	);
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionOutlineTemplateWidget, ve.ui.MWTransclusionOutlinePartWidget );

/* Events */

/**
 * @event focusTemplateParameterById
 * @param {string} pageName Unique id of the {@see OO.ui.BookletLayout} page, e.g. something like
 *  "part_1" or "part_1/param1".
 */

/**
 * Triggered when the user uses the search widget at the top to filter the list of parameters.
 *
 * @event filterParametersById
 * @param {Object.<string,boolean>} visibility Keyed by unique id of the parameter, e.g. something
 *  like "part_1/param1". Note this lists only parameters that are currently shown as a checkbox.
 *  The spec might contain more parameters (e.g. deprecated).
 */

/* Methods */

/**
 * @private
 * @param {string} paramName Parameter name or alias as used in the model
 * @return {OO.ui.OptionWidget}
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.createCheckbox = function ( paramName ) {
	var spec = this.templateModel.getSpec();
	return ve.ui.MWTransclusionOutlineParameterSelectWidget.static.createItem( {
		required: spec.isParameterRequired( paramName ),
		label: spec.getParameterLabel( paramName ),
		data: paramName,
		selected: this.templateModel.hasParameter( paramName )
	} );
};

/**
 * @private
 * @param {string} paramName
 * @return {number}
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.findCanonicalPosition = function ( paramName ) {
	var insertAt = 0,
		// Note this might include parameters that don't have a checkbox, e.g. deprecated
		allParamNames = this.templateModel.getAllParametersOrdered();
	for ( var i = 0; i < allParamNames.length; i++ ) {
		if ( allParamNames[ i ] === paramName || !this.parameters.items[ insertAt ] ) {
			break;
		} else if ( this.parameters.items[ insertAt ].getData() === allParamNames[ i ] ) {
			insertAt++;
		}
	}
	return insertAt;
};

/**
 * @param {string} paramName
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.highlightParameter = function ( paramName ) {
	this.parameters.highlightParameter( paramName );
};

/**
 * @private
 * @param {ve.dm.MWParameterModel} param
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onParameterAddedToTemplateModel = function ( param ) {
	var paramName = param.getName();
	// The placeholder (currently) doesn't get a corresponding item in the sidebar
	if ( !paramName ) {
		return;
	}

	// All parameters known via the spec already have a checkbox
	var item = this.parameters.findItemFromData( paramName );
	if ( !item ) {
		item = this.createCheckbox( paramName );
		this.parameters.addItems( [ item ], this.findCanonicalPosition( paramName ) );

		// Make sure an active filter is applied to the new checkbox as well
		var filter = this.searchWidget.getValue();
		if ( filter ) {
			this.filterParameters( filter );
		}
	}

	item.setSelected( true, true );

	// Reset filter, but only if it hides the relevant checkbox
	if ( !item.isVisible() ) {
		this.searchWidget.setValue( '' );
	}
};

/**
 * @private
 * @param {ve.dm.MWParameterModel} param
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onParameterRemovedFromTemplateModel = function ( param ) {
	this.parameters.markParameterAsUnused( param.getName() );
};

/**
 * @private
 * @param {OO.ui.OptionWidget} item
 * @param {boolean} selected
 * @fires focusTemplateParameterById
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onTemplateParameterChoose = function ( item, selected ) {
	this.onTemplateParameterSelectionChanged( item, selected );

	var param = this.templateModel.getParameter( item.getData() );
	if ( param ) {
		this.emit( 'focusTemplateParameterById', param.getId() );
	}
};

/**
 * @private
 * @param {OO.ui.OptionWidget} item
 * @param {boolean} selected
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onTemplateParameterSelectionChanged = function ( item, selected ) {
	var paramName = item.getData(),
		param = this.templateModel.getParameter( paramName );
	if ( !selected ) {
		this.templateModel.removeParameter( param );
	} else if ( !param ) {
		param = new ve.dm.MWParameterModel( this.templateModel, paramName );
		this.templateModel.addParameter( param );
	}
};

/**
 * @private
 * @param {OO.ui.OptionWidget} item
 * @param {boolean} selected
 * @fires focusTemplateParameterById
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onTemplateParameterClick = function ( item, selected ) {
	// Fail-safe. There should be no code-path that calls this with false.
	if ( !selected ) {
		return;
	}

	var paramName = item.getData(),
		param = this.templateModel.getParameter( paramName );
	if ( param ) {
		this.emit( 'focusTemplateParameterById', param.getId() );
	}
};

/**
 * @private
 * @param {OO.ui.Element[]} items
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onParameterWidgetListChanged = function ( items ) {
	this.searchWidget.toggle( items.length >= 1 );
};

/**
 * Narrows the list of checkboxes down to parameters that match the user's input. The search
 * algorithm is modelled after {@see ve.ui.MWParameterSearchWidget.buildIndex}. We search the
 * parameter's primary name, aliases, label, and description. But not e.g. the example value.
 *
 * @private
 * @param {string} query user input
 * @fires filterParametersById
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.filterParameters = function ( query ) {
	var self = this,
		template = this.templateModel,
		spec = this.templateModel.getSpec(),
		visibility = {},
		nothingFound = true;

	query = query.trim().toLowerCase();

	// Note: We can't really cache this because the list of know parameters can change any time
	this.parameters.items.forEach( function ( item ) {
		var paramName = item.getData(),
			placesToSearch = [
				spec.getPrimaryParameterName( paramName ),
				spec.getParameterLabel( paramName ),
				spec.getParameterDescription( paramName )
			].concat( spec.getParameterAliases( paramName ) );

		var foundSomeMatch = placesToSearch.some( function ( term ) {
			return term && term.toLowerCase().indexOf( query ) !== -1;
		} );

		item.toggle( foundSomeMatch );

		nothingFound = nothingFound && !foundSomeMatch;

		var param = template.getParameter( paramName );
		if ( param ) {
			visibility[ param.getId() ] = foundSomeMatch;
		}
	} );

	this.infoWidget.toggle( nothingFound );
	self.emit( 'filterParametersById', visibility );
};
