/*!
 * VisualEditor user interface MWParameterPage class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki transclusion dialog template page.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {ve.dm.MWParameterModel} parameter Template parameter
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @cfg {jQuery} [$overlay] Overlay to render dropdowns in
 * @cfg {boolean} [readOnly] Parameter is read-only
 */
ve.ui.MWParameterPage = function VeUiMWParameterPage( parameter, name, config ) {
	var paramName = parameter.getName(),
		veConfig = mw.config.get( 'wgVisualEditorConfig' );

	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWParameterPage.super.call( this, name, config );

	// Properties
	this.edited = false;
	this.parameter = parameter;
	this.originalValue = parameter.getValue();
	this.spec = parameter.getTemplate().getSpec();
	this.defaultValue = parameter.getDefaultValue();
	this.exampleValue = parameter.getExampleValue();

	this.$info = $( '<div>' );
	this.$field = $( '<div>' );

	// Temporary feature flags
	this.useInlineDescriptions = veConfig.transclusionDialogInlineDescriptions;
	this.useSuggestedValues = veConfig.transclusionDialogSuggestedValues;
	this.useNewSidebar = veConfig.transclusionDialogNewSidebar;

	// Construct the field docs for the template description
	var $doc = $( '<div>' )
		.attr( 'id', OO.ui.generateElementId() )
		.addClass( 've-ui-mwParameterPage-doc' );
	var description = this.spec.getParameterDescription( paramName );
	if ( description ) {
		$( '<p>' ).text( description ).appendTo( $doc );
	}

	// Note: Calling createValueInput() sets some properties we rely on later in this function
	this.valueInput = this.createValueInput()
		.setValue( this.parameter.getValue() )
		.connect( this, { change: 'onValueInputChange' } );

	this.valueInput.$input.attr( 'aria-describedby', $doc.attr( 'id' ) );

	if ( config.readOnly && this.valueInput.setReadOnly ) {
		this.valueInput.setReadOnly( true );
	}

	var labelElement = new OO.ui.LabelWidget( {
		input: this.valueInput,
		label: this.spec.getParameterLabel( paramName ),
		classes: [ 've-ui-mwParameterPage-label' ]
	} );

	var statusIndicator;
	if ( this.parameter.isRequired() ) {
		if ( !this.useNewSidebar ) {
			statusIndicator = new OO.ui.IndicatorWidget( {
				classes: [ 've-ui-mwParameterPage-statusIndicator' ],
				indicator: 'required',
				title: ve.msg( 'visualeditor-dialog-transclusion-required-parameter' )
			} );
		}
		$( '<p>' )
			.addClass( 've-ui-mwParameterPage-doc-required' )
			.text( ve.msg( 'visualeditor-dialog-transclusion-required-parameter-description' ) )
			.appendTo( $doc );
	} else if ( this.parameter.isDeprecated() ) {
		statusIndicator = new OO.ui.IndicatorWidget( {
			classes: [ 've-ui-mwParameterPage-statusIndicator' ],
			indicator: 'alert',
			title: ve.msg( 'visualeditor-dialog-transclusion-deprecated-parameter' )
		} );
		$( '<p>' )
			.addClass( 've-ui-mwParameterPage-doc-deprecated' )
			.text( ve.msg(
				'visualeditor-dialog-transclusion-deprecated-parameter-description',
				this.spec.getParameterDeprecationDescription( paramName )
			) )
			.appendTo( $doc );
	}

	if ( this.defaultValue ) {
		$( '<p>' )
			.addClass( 've-ui-mwParameterPage-doc-default' )
			.text( ve.msg( 'visualeditor-dialog-transclusion-param-default', this.defaultValue ) )
			.appendTo( $doc );
	}

	if ( this.exampleValue ) {
		$( '<p>' )
			.addClass( 've-ui-mwParameterPage-doc-example' )
			.text( ve.msg(
				this.useInlineDescriptions ?
					'visualeditor-dialog-transclusion-param-example-long' :
					'visualeditor-dialog-transclusion-param-example',
				this.exampleValue
			) )
			.appendTo( $doc );
	}

	// Construct the action buttons
	var $actions = $( '<div>' );

	if ( !this.rawValueInput && !this.useNewSidebar ) {
		this.rawFallbackButton = new OO.ui.ButtonWidget( {
			framed: false,
			icon: 'wikiText',
			title: ve.msg( 'visualeditor-dialog-transclusion-raw-fallback' )
		} )
			.connect( this, { click: 'onRawFallbackButtonClick' } );
		this.rawFallbackButton.$element.appendTo( $actions );
	}

	if ( !this.useInlineDescriptions ) {
		if ( !$doc.children().length ) {
			this.infoButton = new OO.ui.ButtonWidget( {
				disabled: true,
				title: ve.msg( 'visualeditor-dialog-transclusion-param-info-missing' ),
				framed: false,
				icon: 'info',
				classes: [ 've-ui-mwParameterPage-infoButton' ]
			} );
		} else {
			this.infoButton = new OO.ui.PopupButtonWidget( {
				$overlay: config.$overlay,
				popup: {
					$content: $doc
				},
				title: ve.msg( 'visualeditor-dialog-transclusion-param-info' ),
				framed: false,
				icon: 'info',
				classes: [ 've-ui-mwParameterPage-infoButton' ]
			} );
		}
		this.infoButton.$element.appendTo( $actions );
	}

	if ( !this.parameter.isRequired() && !config.readOnly && !this.useNewSidebar ) {
		var removeButton = new OO.ui.ButtonWidget( {
			framed: false,
			icon: 'trash',
			title: ve.msg( 'visualeditor-dialog-transclusion-remove-param' ),
			flags: [ 'destructive' ],
			classes: [ 've-ui-mwParameterPage-removeButton' ]
		} )
			.connect( this, { click: 'onRemoveButtonClick' } );
		removeButton.$element.appendTo( $actions );
	}

	// Initialization
	this.$info
		.addClass( 've-ui-mwParameterPage-info' )
		.append( labelElement.$element );
	if ( statusIndicator ) {
		this.$info.append( ' ', statusIndicator.$element );
	}
	this.$field
		.addClass( 've-ui-mwParameterPage-field' )
		.append(
			this.valueInput.$element
		);

	if ( this.useNewSidebar && !this.parameter.isDocumented() ) {
		$( '<span>' )
			.addClass( 've-ui-mwParameterPage-undocumentedLabel' )
			.text( ve.msg( 'visualeditor-dialog-transclusion-param-undocumented' ) )
			.insertAfter( labelElement.$element );
	}

	this.$element
		.addClass( 've-ui-mwParameterPage' )
		.append( this.$info, this.$field );
	if ( $actions.children().length ) {
		$actions
			.addClass( 've-ui-mwParameterPage-actions' )
			.appendTo( this.$element );
	}

	if ( this.useInlineDescriptions && $doc.children().length ) {
		this.$field.addClass( 've-ui-mwParameterPage-inlineDescription' );
		this.collapsibleDoc = new ve.ui.MWExpandableContentElement( {
			classes: [ 've-ui-mwParameterPage-inlineDescription' ],
			$content: $doc
		} );
		this.$info.after( this.collapsibleDoc.$element );
	}

	// FIXME this and the addPlaceholderParameter can be remove when the feature flag is gone
	if ( !config.readOnly && !this.useNewSidebar ) {
		// This button is only shown when this …ParameterPage is neither followed by another
		// …TemplatePage (i.e. it's the last template in the transclusion) nor a
		// …ParameterPlaceholderPage (i.e. the parameter search widget isn't shown). This state
		// should be unreachable, but isn't. Hiding this is done via CSS.
		var addButton = new OO.ui.ButtonWidget( {
			framed: false,
			icon: 'parameter',
			label: this.useNewSidebar ?
				ve.msg( 'visualeditor-dialog-transclusion-add-undocumented-param' ) :
				ve.msg( 'visualeditor-dialog-transclusion-add-param' )
		} )
			.connect( this, { click: 'addPlaceholderParameter' } );
		$( '<div>' )
			.addClass( 've-ui-mwParameterPage-addUndocumented' )
			.append( addButton.$element )
			.appendTo( this.$element );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWParameterPage, OO.ui.PageLayout );

/* Methods */

/**
 * Get default configuration for an input widget.
 *
 * @private
 * @return {Object}
 */
ve.ui.MWParameterPage.prototype.getDefaultInputConfig = function () {
	var required = this.parameter.isRequired(),
		valueInputConfig = {
			autosize: true,
			required: required,
			validate: required ? 'non-empty' : null
		};

	if ( this.defaultValue ) {
		valueInputConfig.placeholder = ve.msg(
			'visualeditor-dialog-transclusion-param-default',
			this.defaultValue
		);
	} else if ( this.exampleValue && !this.useInlineDescriptions ) {
		valueInputConfig.placeholder = ve.msg(
			this.useInlineDescriptions ?
				'visualeditor-dialog-transclusion-param-example-long' :
				'visualeditor-dialog-transclusion-param-example',
			this.exampleValue
		);
	}

	return valueInputConfig;
};

/**
 * Create a value input widget based on the parameter type and whether it is
 * required or not.
 *
 * @private
 * @return {OO.ui.InputWidget}
 */
ve.ui.MWParameterPage.prototype.createValueInput = function () {
	var type = this.parameter.getType(),
		value = this.parameter.getValue(),
		valueInputConfig = this.getDefaultInputConfig();

	this.rawValueInput = false;
	delete valueInputConfig.validate;

	// TODO:
	// * date - T100206
	// * number - T124850
	// * unbalanced-wikitext/content - T106242
	// * string? - T124917
	if (
		type === 'wiki-page-name' &&
		( value === '' || mw.Title.newFromText( value ) )
	) {
		return new mw.widgets.TitleInputWidget( ve.extendObject( {
			api: ve.init.target.getContentApi()
		}, valueInputConfig ) );
	} else if (
		type === 'wiki-file-name' &&
		( value === '' || mw.Title.newFromText( value ) )
	) {
		return new mw.widgets.TitleInputWidget( ve.extendObject( {}, valueInputConfig, {
			api: ve.init.target.getContentApi(),
			namespace: 6,
			showImages: true
		} ) );
	} else if (
		type === 'wiki-user-name' &&
		( value === '' || mw.Title.newFromText( value ) )
	) {
		valueInputConfig.validate = function ( val ) {
			// TODO: Check against wgMaxNameChars
			// TODO: Check against unicode validation regex from MW core's User::isValidUserName
			return !!mw.Title.newFromText( val );
		};
		return new mw.widgets.UserInputWidget( ve.extendObject( {
			api: ve.init.target.getContentApi()
		}, valueInputConfig ) );
	} else if (
		type === 'wiki-template-name' &&
		( value === '' || mw.Title.newFromText( value ) )
	) {
		return new mw.widgets.TitleInputWidget( ve.extendObject( {
			api: ve.init.target.getContentApi()
		}, valueInputConfig, {
			namespace: mw.config.get( 'wgNamespaceIds' ).template
		} ) );
	} else if ( type === 'boolean' && ( value === '1' || value === '0' ) ) {
		return new ve.ui.MWParameterCheckboxInputWidget( valueInputConfig );
	} else if (
		type === 'url' &&
		(
			value === '' ||
			ve.init.platform.getExternalLinkUrlProtocolsRegExp().exec( value.trim() )
		)
	) {
		return ve.ui.MWExternalLinkAnnotationWidget.static.createExternalLinkInputWidget( valueInputConfig );
	} else if (
		this.useSuggestedValues &&
		this.parameter.getSuggestedValues().length &&
		this.isSuggestedValueType( type )
	) {
		valueInputConfig.menu = { filterFromInput: true, highlightOnFilter: true };
		valueInputConfig.options =
			this.parameter.getSuggestedValues().filter( function ( suggestedValue ) {
				// This wasn't validated for a while, existing templates can do anything here
				return typeof suggestedValue === 'string';
			} ).map( function ( suggestedValue ) {
				return { data: suggestedValue, label: suggestedValue || '\xA0' };
			} );
		this.rawValueInput = true;
		return new OO.ui.ComboBoxInputWidget( valueInputConfig );
	} else if ( type !== 'line' || value.indexOf( '\n' ) !== -1 ) {
		// If the type is line, but there are already newlines in the provided
		// value, don't break the existing content by only providing a single-
		// line field. (This implies that the TemplateData for the field isn't
		// complying with its use in practice...)
		this.rawValueInput = true;
		return new ve.ui.MWLazyMultilineTextInputWidget( valueInputConfig );
	}

	return new OO.ui.TextInputWidget( valueInputConfig );
};

/**
 * Whether or not to show suggested values for a given parameter type
 *
 * @private
 * @param {string} type Parameter type
 * @return {boolean} True if suggested values should be shown
 */
ve.ui.MWParameterPage.prototype.isSuggestedValueType = function ( type ) {
	return [ 'unknown', 'content', 'line', 'string', 'number', 'unbalanced-wikitext' ].indexOf( type ) > -1;
};

/**
 * @private
 * @return {boolean} True if there is either user-provided input or a default value
 */
ve.ui.MWParameterPage.prototype.containsSomeValue = function () {
	// Note: For templates that allow overriding a default value with nothing, the empty string is
	// meaningful user input. For templates that don't, the parameter can never be truly empty.
	return !!( this.valueInput.getValue() || this.defaultValue );
};

/**
 * Handle change events from the value input
 *
 * @private
 * @param {string} value
 */
ve.ui.MWParameterPage.prototype.onValueInputChange = function () {
	var value = this.valueInput.getValue();

	if ( !this.edited ) {
		ve.track( 'activity.transclusion', { action: 'edit-parameter-value' } );
	}
	this.edited = true;
	this.parameter.setValue( value );

	if ( this.outlineItem ) {
		this.outlineItem.setFlags( { empty: !this.containsSomeValue() } );
	}
};

/**
 * Handle click events from the remove button
 *
 * @private
 */
ve.ui.MWParameterPage.prototype.onRemoveButtonClick = function () {
	this.parameter.remove();
};

/**
 * Handle click events from the raw fallback button
 *
 * @private
 */
ve.ui.MWParameterPage.prototype.onRawFallbackButtonClick = function () {
	this.valueInput.$element.detach();
	if ( this.rawValueInput ) {
		this.valueInput = this.createValueInput()
			.setValue( this.valueInput.getValue() );
	} else {
		this.valueInput = new OO.ui.TextInputWidget( this.getDefaultInputConfig() )
			.setValue( this.edited ? this.valueInput.getValue() : this.originalValue );
		this.valueInput.$input.addClass( 've-ui-mwParameter-wikitextFallbackInput' );
		this.rawValueInput = true;
	}
	this.valueInput.connect( this, { change: 'onValueInputChange' } );
	this.$field.append( this.valueInput.$element );
};

/**
 * Handle click events from the add button
 *
 * @private
 */
ve.ui.MWParameterPage.prototype.addPlaceholderParameter = function () {
	var template = this.parameter.getTemplate();
	template.addParameter( new ve.dm.MWParameterModel( template ) );
};

/**
 * @inheritdoc
 */
ve.ui.MWParameterPage.prototype.setupOutlineItem = function () {
	this.outlineItem
		.setIcon( 'parameter' )
		.setMovable( false )
		.setRemovable( !this.parameter.isRequired() && !this.useNewSidebar )
		.setLevel( 1 )
		.setFlags( { empty: !this.containsSomeValue() } )
		.setLabel( this.spec.getParameterLabel( this.parameter.getName() ) );

	if ( this.parameter.isRequired() ) {
		this.outlineItem
			.setIndicator( 'required' )
			.setTitle(
				ve.msg( 'visualeditor-dialog-transclusion-required-parameter' )
			);
	}
	if ( this.parameter.isDeprecated() ) {
		this.outlineItem
			.setIndicator( 'alert' )
			.setTitle(
				ve.msg( 'visualeditor-dialog-transclusion-deprecated-parameter' )
			);
	}
};

/**
 * @inheritdoc
 */
ve.ui.MWParameterPage.prototype.focus = function () {
	this.valueInput.focus();
};

/**
 * Refresh collapsible children.
 */
ve.ui.MWParameterPage.prototype.updateSize = function () {
	if ( this.collapsibleDoc ) {
		this.collapsibleDoc.updateSize();
	}
};
