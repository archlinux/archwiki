/**
 * Container for a template as rendered in the template dialog sidebar.
 * Contains search and visibility inputs, and a list of parameters when available.
 *
 * @class
 * @extends ve.ui.MWTransclusionOutlinePartWidget
 *
 * @constructor
 * @param {ve.dm.MWTemplateModel} template
 * @param {boolean} [replacesPlaceholder=false]
 * @property {ve.dm.MWTemplateModel} templateModel
 * @property {ve.ui.MWTransclusionOutlineParameterSelectWidget} parameterList
 */
ve.ui.MWTransclusionOutlineTemplateWidget = function VeUiMWTransclusionOutlineTemplateWidget( template, replacesPlaceholder ) {
	const spec = template.getSpec();

	// Parent constructor
	ve.ui.MWTransclusionOutlineTemplateWidget.super.call( this, template, {
		icon: 'puzzle',
		label: spec.getLabel(),
		ariaDescriptionUnselected: ve.msg( 'visualeditor-dialog-transclusion-template-widget-aria' ),
		ariaDescriptionSelected: ve.msg( 'visualeditor-dialog-transclusion-template-widget-aria-selected' ),
		ariaDescriptionSelectedSingle: ve.msg( 'visualeditor-dialog-transclusion-template-widget-aria-selected-single' )
	} );

	this.$element.addClass( 've-ui-mwTransclusionOutlineTemplateWidget' );

	// Initialization
	this.templateModel = template.connect( this, {
		add: 'onParameterAddedToTemplateModel',
		remove: 'onParameterRemovedFromTemplateModel'
	} );

	const canFilter = this.shouldFiltersBeShown(),
		initiallyHideUnused = canFilter && !replacesPlaceholder && !this.transclusionModel.isSingleTemplate();

	const parameterNames = this.getRelevantTemplateParameters( initiallyHideUnused ? 'used' : 'all' );
	if ( parameterNames.length ) {
		this.initializeParameterList();
		this.parameterList.addItems( parameterNames.map( this.createCheckbox.bind( this ) ) );
	} else if ( !canFilter ) {
		this.$noParametersNote = $( '<div>' )
			.text( ve.msg( 'visualeditor-dialog-transclusion-no-template-parameters' ) )
			.addClass( 've-ui-mwTransclusionOutlineTemplateWidget-no-template-parameters' );
		this.$element.append( this.$noParametersNote );
	}

	this.toggleFilters();
	if ( initiallyHideUnused ) {
		// This is only to update the label of the "Hide unused" button
		this.toggleUnusedWidget.toggleUnusedParameters( false );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionOutlineTemplateWidget, ve.ui.MWTransclusionOutlinePartWidget );

/* Events */

/**
 * Triggered when the user uses the search widget at the top to filter the list of parameters.
 *
 * @event ve.ui.MWTransclusionOutlineTemplateWidget#filterParametersById
 * @param {Object.<string,boolean>} visibility Keyed by unique id of the parameter, e.g. something
 *  like "part_1/param1". Note this lists only parameters that are currently shown as a checkbox.
 *  The spec might contain more parameters (e.g. deprecated).
 */

/**
 * @event ve.ui.MWTransclusionOutlineTemplateWidget#transclusionOutlineItemSelected
 * @param {string} id Item ID
 * @param {boolean} soft If true, focus should stay in the sidebar.
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
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.initializeParameterList = function () {
	if ( this.parameterList ) {
		return;
	}

	const $parametersAriaDescription = $( '<span>' )
		.text( ve.msg( 'visualeditor-dialog-transclusion-param-selection-aria-description' ) )
		.addClass( 've-ui-mwTransclusionOutline-ariaHidden' );

	this.parameterList = new ve.ui.MWTransclusionOutlineParameterSelectWidget( {
		ariaLabel: ve.msg( 'visualeditor-dialog-transclusion-param-selection-aria-label', this.templateModel.getSpec().getLabel() ),
		$ariaDescribedBy: $parametersAriaDescription
	} ).connect( this, {
		choose: 'onTemplateParameterChoose',
		templateParameterSpaceDown: 'onTemplateParameterSpaceDown'
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
		this.initializeParameterList();
		this.getRelevantTemplateParameters().forEach( ( paramName ) => {
			if ( !this.parameterList.findItemFromData( paramName ) ) {
				this.parameterList.addItems(
					[ this.createCheckbox( paramName ) ],
					this.findCanonicalPosition( paramName )
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
	const template = this.templateModel;

	let parameterNames;
	switch ( filter ) {
		case 'used':
			parameterNames = template.getOrderedParameterNames();
			break;
		case 'unused':
			parameterNames = template.getAllParametersOrdered().filter( ( name ) => !( name in template.getParameters() ) );
			break;
		default:
			parameterNames = template.getAllParametersOrdered();
	}

	return parameterNames.filter( ( name ) => {
		// Don't offer deprecated parameters, unless they are already used
		if ( template.getSpec().isParameterDeprecated( name ) && !template.hasParameter( name ) ) {
			return false;
		}
		// Never create a checkbox for a not yet named parameter placeholder
		return !!name;
	} );
};

/**
 * @private
 * @param {string} paramName Parameter name or alias as used in the model
 * @return {OO.ui.OptionWidget}
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.createCheckbox = function ( paramName ) {
	const spec = this.templateModel.getSpec(),
		parameterModel = this.templateModel.getParameter( paramName );

	return ve.ui.MWTransclusionOutlineParameterSelectWidget.static.createItem( {
		required: spec.isParameterRequired( paramName ),
		label: spec.getParameterLabel( paramName ),
		data: paramName,
		selected: !!parameterModel,
		hasValue: !!parameterModel && !!parameterModel.getValue()
	} );
};

/**
 * @private
 * @param {string} paramName
 * @return {number}
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.findCanonicalPosition = function ( paramName ) {
	// Note this might include parameters that don't have a checkbox, e.g. deprecated
	const allParamNames = this.templateModel.getAllParametersOrdered();
	let insertAt = 0;
	for ( let i = 0; i < allParamNames.length; i++ ) {
		if ( allParamNames[ i ] === paramName || !this.parameterList.items[ insertAt ] ) {
			break;
		} else if ( this.parameterList.items[ insertAt ].getData() === allParamNames[ i ] ) {
			insertAt++;
		}
	}
	return insertAt;
};

/**
 * @param {string} [paramName] Parameter name to set, e.g. "param1". Omit to remove setting.
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.setParameter = function ( paramName ) {
	if ( this.parameterList ) {
		this.parameterList.setActiveParameter( paramName );
	}
};

/**
 * @param {string} paramName
 * @param {boolean} hasValue
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.toggleHasValue = function ( paramName, hasValue ) {
	if ( this.parameterList ) {
		const item = this.parameterList.findItemFromData( paramName );
		if ( item ) {
			item.toggleHasValue( hasValue );
		}
	}
};

/**
 * @inheritDoc
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.setSelected = function ( state ) {
	// FIXME: This is a super-specific hack; should be replaced with a more generic solution
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
	const paramName = param.getName();
	// The placeholder (currently) doesn't get a corresponding item in the sidebar
	if ( !paramName ) {
		return;
	}

	if ( this.$noParametersNote ) {
		this.$noParametersNote.remove();
		delete this.$noParametersNote;
	}

	this.initializeParameterList();

	// All parameters known via the spec already have a checkbox
	let item = this.parameterList.findItemFromData( paramName );
	if ( item ) {
		// Reset the "hide unused" filter for this field, it's going to be used
		item.toggle( true );
	} else {
		item = this.createCheckbox( paramName );
		this.parameterList.addItems( [ item ], this.findCanonicalPosition( paramName ) );

		this.toggleFilters();

		// Make sure an active filter is applied to the new checkbox as well
		const filter = this.searchWidget && this.searchWidget.getValue();
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
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onTemplateParameterChoose = function ( item, selected ) {
	this.toggleParameter( item, selected, false );
};

/**
 * @private
 * @param {OO.ui.OptionWidget} item
 * @param {boolean} selected
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onTemplateParameterSpaceDown = function ( item, selected ) {
	this.toggleParameter( item, selected, true );
};

/**
 * @private
 * @param {OO.ui.OptionWidget} item
 * @param {boolean} selected
 * @param {boolean} soft If true, focus should stay in the sidebar.
 * @fires ve.ui.MWTransclusionOutlineTemplateWidget#transclusionOutlineItemSelected
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.toggleParameter = function ( item, selected, soft ) {
	const paramName = item.getData();
	let param = this.templateModel.getParameter( paramName );
	if ( !selected ) {
		this.templateModel.removeParameter( param );
	} else if ( !param ) {
		param = new ve.dm.MWParameterModel( this.templateModel, paramName );
		this.templateModel.addParameter( param );
	}

	this.updateUnusedParameterToggleState();

	if ( param && selected ) {
		this.emit( 'transclusionOutlineItemSelected', param.getId(), soft );
	}
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
 * @return {boolean}
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.shouldFiltersBeShown = function () {
	const min = this.constructor.static.searchableParameterCount,
		existingParameterWidgets = this.parameterList && this.parameterList.getItemCount();
	// Avoid expensive calls when there are already enough items in the parameter list
	return existingParameterWidgets >= min || this.getRelevantTemplateParameters().length >= min;
};

/**
 * @private
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.toggleFilters = function () {
	const visible = this.shouldFiltersBeShown();
	if ( this.searchWidget ) {
		this.searchWidget.toggle( visible );
		this.toggleUnusedWidget.toggle( visible );
	} else if ( visible ) {
		this.initializeFilters();
		this.updateUnusedParameterToggleState();
	}

	this.recalculateStickyHeaderHeight();
};

ve.ui.MWTransclusionOutlineTemplateWidget.prototype.recalculateStickyHeaderHeight = function () {
	// A template with no used parameters might have a sticky header, but no paramater list yet
	if ( this.$stickyHeader && this.parameterList ) {
		this.parameterList.stickyHeaderHeight = Math.floor( this.$stickyHeader.outerHeight() );
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

	this.$stickyHeader = $( '<div>' )
		.addClass( 've-ui-mwTransclusionOutlineTemplateWidget-sticky' )
		.append(
			this.header.$element,
			this.searchWidget.$element,
			this.toggleUnusedWidget.$element
		);

	this.$element.prepend(
		this.$stickyHeader,
		this.infoWidget.$element
	);
};

/**
 * Narrows the list of checkboxes down to parameters that match the user's input. We search the
 * parameter's primary name, aliases, label, and description. But not e.g. the example value.
 *
 * @private
 * @param {string} query user input
 * @fires ve.ui.MWTransclusionOutlineTemplateWidget#filterParametersById
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.filterParameters = function ( query ) {
	const template = this.templateModel,
		spec = this.templateModel.getSpec(),
		visibility = {};
	let nothingFound = true;

	query = query.trim().toLowerCase();
	this.createAllParameterCheckboxes();

	// Note: We can't really cache this because the list of know parameters can change any time
	this.parameterList.items.forEach( ( item ) => {
		const paramName = item.getData(),
			placesToSearch = [
				spec.getPrimaryParameterName( paramName ),
				spec.getParameterLabel( paramName ),
				spec.getParameterDescription( paramName )
			].concat( spec.getParameterAliases( paramName ) );

		const foundSomeMatch = placesToSearch.some(
			// Aliases missed validation for a long time and aren't guaranteed to be strings
			( term ) => term && typeof term === 'string' && term.toLowerCase().indexOf( query ) !== -1
		);

		item.toggle( foundSomeMatch );

		nothingFound = nothingFound && !foundSomeMatch;

		const param = template.getParameter( paramName );
		if ( param ) {
			visibility[ param.getId() ] = foundSomeMatch;
		}
	} );

	this.toggleUnusedWidget.toggle( !query );
	this.infoWidget.toggle( nothingFound );
	this.parameterList.setTabIndex( nothingFound ? -1 : 0 );
	// The "hide unused" button might be hidden now, which changes the height of the sticky header
	this.recalculateStickyHeaderHeight();
	this.emit( 'filterParametersById', visibility );
};

/**
 * @private
 * @param {boolean} visibility
 * @param {boolean} [fromClick]
 */
ve.ui.MWTransclusionOutlineTemplateWidget.prototype.onToggleUnusedFields = function ( visibility, fromClick ) {
	if ( visibility ) {
		this.createAllParameterCheckboxes();
	}

	if ( this.parameterList ) {
		this.parameterList.items.forEach( ( item ) => {
			item.toggle( visibility || item.isSelected() );
		} );
	}

	if ( !visibility && fromClick ) {
		this.header.scrollElementIntoView().then( () => {
			if ( this.parameterList ) {
				this.parameterList.ensureVisibilityOfFirstCheckedParameter();
			}
		} );
	}
};
