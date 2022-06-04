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
 * @param {boolean} [replacesPlaceholder]
 * @property {ve.dm.MWTemplateModel} templateModel
 * @property {ve.ui.MWTransclusionOutlineParameterSelectWidget} parameterList
 */
ve.ui.MWTransclusionOutlineTemplateWidget = function VeUiMWTransclusionOutlineTemplateWidget( template, replacesPlaceholder ) {
	var spec = template.getSpec();

	// Parent constructor
	ve.ui.MWTransclusionOutlineTemplateWidget.super.call( this, template, {
		icon: 'puzzle',
		label: spec.getLabel(),
		ariaDescriptionUnselected: ve.msg( 'visualeditor-dialog-transclusion-template-widget-aria' ),
		ariaDescriptionSelected: ve.msg( 'visualeditor-dialog-transclusion-template-widget-aria-selected' ),
		ariaDescriptionSelectedSingle: ve.msg( 'visualeditor-dialog-transclusion-template-widget-aria-selected-single' )
	} );

	// Initialization
	this.templateModel = template.connect( this, {
		add: 'onParameterAddedToTemplateModel',
		remove: 'onParameterRemovedFromTemplateModel'
	} );

	var initiallyHideUnused = !replacesPlaceholder && !this.transclusionModel.isSingleTemplate();
	this.initializeParameterList( initiallyHideUnused );
	this.toggleFilters( initiallyHideUnused );
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

/* Static Properties */

/**
 * Minimum number of parameters required before search and filter options appear.
 *
 * @static
 * @property {number}
 */
ve.ui.MWTransclusionOutlineTemplateWidget.static.searchableParameterCount = 4;

/* Methods */

/**
 * @private
 * @param {boolean} [initiallyHideUnused=false]
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.initializeParameterList = function ( initiallyHideUnused ) {
	if ( this.parameterList ) {
		return;
	}

	var parameterNames = this.getRelevantTemplateParameters( initiallyHideUnused ? 'used' : 'all' );
	if ( !parameterNames.length ) {
		return;
	}

	var $parametersAriaDescription = $( '<span>' )
		.text( ve.msg( 'visualeditor-dialog-transclusion-param-selection-aria-description' ) )
		.addClass( 've-ui-mwTransclusionOutline-ariaHidden' );

	this.parameterList = new ve.ui.MWTransclusionOutlineParameterSelectWidget( {
		items: parameterNames.map( this.createCheckbox.bind( this ) ),
		ariaLabel: ve.msg( 'visualeditor-dialog-transclusion-param-selection-aria-label', this.templateModel.getSpec().getLabel() ),
		$ariaDescribedBy: $parametersAriaDescription
	} ).connect( this, {
		choose: 'onTemplateParameterChoose',
		templateParameterSelectionChanged: 'onTemplateParameterSelectionChanged',
		change: 'onParameterWidgetListChanged'
	} );

	this.$element.append(
		$parametersAriaDescription,
		this.parameterList.$element
	);
};

/**
 * @private
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.createAllParameterCheckboxes = function () {
	if ( !this.parameterListComplete ) {
		var self = this;
		this.initializeParameterList();
		this.getRelevantTemplateParameters().forEach( function ( paramName ) {
			if ( !self.parameterList.findItemFromData( paramName ) ) {
				self.parameterList.addItems(
					[ self.createCheckbox( paramName ) ],
					self.findCanonicalPosition( paramName )
				);
			}
		} );
		this.parameterListComplete = true;
	}
};

/**
 * @private
 * @param {string} [filter='all'] Either "used", "unused", or "all"
 * @return {string[]}
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.getRelevantTemplateParameters = function ( filter ) {
	var parameterNames,
		template = this.templateModel;

	switch ( filter ) {
		case 'used':
			parameterNames = template.getOrderedParameterNames();
			break;
		case 'unused':
			parameterNames = template.getAllParametersOrdered().filter( function ( name ) {
				return !( name in template.getParameters() );
			} );
			break;
		default:
			parameterNames = template.getAllParametersOrdered();
	}

	return parameterNames.filter( function ( name ) {
		// Don't offer deprecated parameters, unless they are already used
		if ( template.getSpec().isParameterDeprecated( name ) && !template.hasParameter( name ) ) {
			return false;
		}
		// Never create a checkbox for a ve.ui.MWParameterPlaceholderPage placeholder
		return !!name;
	} );
};

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
		if ( allParamNames[ i ] === paramName || !this.parameterList.items[ insertAt ] ) {
			break;
		} else if ( this.parameterList.items[ insertAt ].getData() === allParamNames[ i ] ) {
			insertAt++;
		}
	}
	return insertAt;
};

/**
 * @param {string} [paramName] Parameter name to highlight, e.g. "param1". Omit for no highlight.
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.highlightParameter = function ( paramName ) {
	if ( this.parameterList ) {
		this.parameterList.highlightParameter( paramName );
	}
};

/**
 * @inheritDoc
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.setSelected = function ( state ) {
	if ( !state && this.isSelected() && this.parameterList ) {
		this.parameterList.highlightItem();
	}
	ve.ui.MWTransclusionOutlineTemplateWidget.super.prototype.setSelected.call( this, state );
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

	this.initializeParameterList();

	// All parameters known via the spec already have a checkbox
	var item = this.parameterList.findItemFromData( paramName );
	if ( item ) {
		// Reset the "hide unused" filter for this field, it's going to be used
		item.toggle( true );
	} else {
		item = this.createCheckbox( paramName );
		this.parameterList.addItems( [ item ], this.findCanonicalPosition( paramName ) );

		this.toggleFilters();

		// Make sure an active filter is applied to the new checkbox as well
		var filter = this.searchWidget && this.searchWidget.getValue();
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
	this.parameterList.markParameterAsUnused( param.getName() );
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
	if ( param && selected ) {
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

	this.updateUnusedParameterToggleState();
};

/**
 * @private
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.updateUnusedParameterToggleState = function () {
	if ( this.toggleUnusedWidget ) {
		this.toggleUnusedWidget.setDisabled( !this.getRelevantTemplateParameters( 'unused' ).length );
	}
};

/**
 * @private
 * @param {OO.ui.Element[]} items
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onParameterWidgetListChanged = function () {
	this.toggleFilters();
};

/**
 * @private
 * @param {boolean} [initiallyHideUnused=false]
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.toggleFilters = function ( initiallyHideUnused ) {
	var numParams = this.getRelevantTemplateParameters().length,
		visible = numParams >= this.constructor.static.searchableParameterCount;
	if ( this.searchWidget ) {
		this.searchWidget.toggle( visible );
		this.toggleUnusedWidget.toggle( visible );
	} else if ( visible ) {
		this.initializeFilters();
		this.updateUnusedParameterToggleState();
		if ( initiallyHideUnused ) {
			this.toggleUnusedWidget.toggleUnusedParameters( false );
		}
	}
};

/**
 * @private
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.initializeFilters = function () {
	this.searchWidget = new OO.ui.SearchInputWidget( {
		title: ve.msg( 'visualeditor-dialog-transclusion-filter-title', this.templateModel.getSpec().getLabel() ),
		placeholder: ve.msg( 'visualeditor-dialog-transclusion-filter-placeholder' ),
		classes: [ 've-ui-mwTransclusionOutlineTemplateWidget-searchWidget' ]
	} ).connect( this, {
		change: 'filterParameters'
	} );
	this.searchWidget.$element.attr( 'role', 'search' );

	this.toggleUnusedWidget = new ve.ui.MWTransclusionOutlineToggleUnusedWidget();
	this.toggleUnusedWidget.connect( this, {
		toggleUnusedFields: 'onToggleUnusedFields'
	} );

	this.infoWidget = new OO.ui.LabelWidget( {
		label: ve.msg( 'visualeditor-dialog-transclusion-filter-no-match' ),
		classes: [ 've-ui-mwTransclusionOutlineTemplateWidget-no-match' ]
	} ).toggle( false );

	var $stickyHeader = $( '<div>' )
		.addClass( 've-ui-mwTransclusionOutlineTemplateWidget-sticky' )
		.append(
			this.header.$element,
			this.searchWidget.$element,
			this.toggleUnusedWidget.$element
		);

	this.$element.prepend(
		$stickyHeader,
		this.infoWidget.$element
	);
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
	var template = this.templateModel,
		spec = this.templateModel.getSpec(),
		visibility = {},
		nothingFound = true;

	query = query.trim().toLowerCase();
	this.createAllParameterCheckboxes();

	// Note: We can't really cache this because the list of know parameters can change any time
	this.parameterList.items.forEach( function ( item ) {
		var paramName = item.getData(),
			placesToSearch = [
				spec.getPrimaryParameterName( paramName ),
				spec.getParameterLabel( paramName ),
				spec.getParameterDescription( paramName )
			].concat( spec.getParameterAliases( paramName ) );

		var foundSomeMatch = placesToSearch.some( function ( term ) {
			// Aliases missed validation for a long time and aren't guaranteed to be strings
			return term && typeof term === 'string' && term.toLowerCase().indexOf( query ) !== -1;
		} );

		item.toggle( foundSomeMatch );

		nothingFound = nothingFound && !foundSomeMatch;

		var param = template.getParameter( paramName );
		if ( param ) {
			visibility[ param.getId() ] = foundSomeMatch;
		}
	} );

	this.toggleUnusedWidget.toggle( !query );
	this.infoWidget.toggle( nothingFound );
	this.emit( 'filterParametersById', visibility );
};

/**
 * @private
 * @param {boolean} visibility
 * @param {boolean} fromClick
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onToggleUnusedFields = function ( visibility, fromClick ) {
	if ( visibility ) {
		this.createAllParameterCheckboxes();
	}

	var firstSelected;
	if ( this.parameterList ) {
		this.parameterList.items.forEach( function ( item ) {
			item.toggle( visibility || item.isSelected() );
			if ( fromClick && !firstSelected && item.isSelected() ) {
				firstSelected = item;
			}
		} );
	}

	if ( !visibility && fromClick ) {
		// make sure the header is still visibly after collapsing
		this.header.scrollElementIntoView();
		if ( firstSelected ) {
			// make sure parameters ( if any ) are still visible and scrolled underneath the sticky
			firstSelected.scrollElementIntoView( { padding: { top: 110 } } );
		}
	}
};
