/*!
 * VisualEditor user interface MWTemplatePlaceholderPage class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * The placeholder is shown in the template dialog content pane, and allows the
 * user to enter a template name.  Once a name is chosen, the placeholder is
 * replaced with elements for the concrete template.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {ve.dm.MWTemplatePlaceholderModel} placeholder Template placeholder
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @param {jQuery} [config.$overlay] Overlay to render dropdowns in
 */
ve.ui.MWTemplatePlaceholderPage = function VeUiMWTemplatePlaceholderPage( placeholder, name, config ) {
	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWTemplatePlaceholderPage.super.call( this, name, config );

	// Properties
	this.placeholder = placeholder;

	this.usingTemplateDiscovery = mw.templateData !== undefined && mw.templateData.TemplateSearchLayout !== undefined;
	if ( this.usingTemplateDiscovery ) {
		// This variable name is slightly misleading here as this isn't a fieldset.
		this.addTemplateFieldset = new mw.templateData.TemplateSearchLayout( { padded: false } );
		this.addTemplateFieldset.connect( this, { choose: this.onAddTemplate } );
		// Expose the internal widget for now, but this will be removed once we've switched to the new widget.
		this.addTemplateInput = this.addTemplateFieldset.searchWidget;

	} else {
		this.addTemplateInput = new ve.ui.MWTemplateTitleInputWidget( {
			$overlay: config.$overlay,
			showDescriptions: true,
			api: ve.init.target.getContentApi()
		} )
			.connect( this, {
				change: 'onTemplateInputChange',
				enter: 'onAddTemplate'
			} );

		this.addTemplateInput.getLookupMenu().connect( this, {
			choose: 'onAddTemplate'
		} );

		this.addTemplateButton = new OO.ui.ButtonWidget( {
			label: ve.msg( 'visualeditor-dialog-transclusion-add-template-save' ),
			flags: [ 'progressive' ],
			classes: [ 've-ui-mwTransclusionDialog-addButton' ],
			disabled: true
		} )
			.connect( this, { click: 'onAddTemplate' } );

		const addTemplateActionFieldLayout = new OO.ui.ActionFieldLayout(
			this.addTemplateInput,
			this.addTemplateButton,
			{
				label: ve.msg( 'visualeditor-dialog-transclusion-template-search-help' ),
				align: 'top'
			}
		);

		const dialogTitle = this.placeholder.getTransclusion().isSingleTemplate() ?
			'visualeditor-dialog-transclusion-template-search' :
			'visualeditor-dialog-transclusion-add-template';

		const addTemplateFieldsetConfig = {
			// The following messages are used here:
			// * visualeditor-dialog-transclusion-template-search
			// * visualeditor-dialog-transclusion-add-template
			label: ve.msg( dialogTitle ),
			icon: 'puzzle',
			classes: [ 've-ui-mwTransclusionDialog-addTemplateFieldset' ],
			items: [ addTemplateActionFieldLayout ]
		};

		this.addTemplateFieldset = new OO.ui.FieldsetLayout( addTemplateFieldsetConfig );
	}

	// Initialization
	this.$element
		.addClass( 've-ui-mwTemplatePlaceholderPage' )
		.append( this.addTemplateFieldset.$element );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTemplatePlaceholderPage, OO.ui.PageLayout );

/* Methods */

/**
 * @inheritDoc OO.ui.PanelLayout
 */
ve.ui.MWTemplatePlaceholderPage.prototype.focus = function () {
	// The parent method would focus the first element, which might be the message widget
	this.addTemplateInput.focus();

	// HACK: Set the width of the lookupMenu to the width of the input
	// TODO: This should be handled upstream in OOUI
	this.addTemplateInput.lookupMenu.width = this.addTemplateInput.$input[ 0 ].clientWidth;
};

/**
 * @private
 * @param {Object|undefined} templateData The choosen template's data (if TemplateDiscovery is enabled).
 */
ve.ui.MWTemplatePlaceholderPage.prototype.onAddTemplate = function ( templateData ) {
	const transclusion = this.placeholder.getTransclusion();

	let name = null;
	if ( !this.usingTemplateDiscovery ) {
		const menu = this.addTemplateInput.getLookupMenu();
		if ( menu.isVisible() ) {
			menu.chooseItem( menu.findSelectedItem() );
		}
		name = this.addTemplateInput.getMWTitle();
		if ( !name ) {
			// Invalid titles return null, so abort here.
			return;
		}
	} else {
		name = mw.Title.newFromText( templateData.title );
	}

	// TODO tracking will only be implemented temporarily to answer questions on
	// template usage for the Technical Wishes topic area see T258917
	const event = {
		action: 'add-template',
		// eslint-disable-next-line camelcase
		template_names: [ name.getPrefixedText() ]
	};
	const editCountBucket = mw.config.get( 'wgUserEditCountBucket' );
	if ( editCountBucket !== null ) {
		// eslint-disable-next-line camelcase
		event.user_edit_count_bucket = editCountBucket;
	}
	mw.track( 'event.VisualEditorTemplateDialogUse', event );

	const part = ve.dm.MWTemplateModel.newFromName( transclusion, name );
	transclusion.replacePart( this.placeholder, part ).then(
		transclusion.addPromptedParameters.bind( transclusion )
	);
	if ( !this.usingTemplateDiscovery ) {
		this.addTemplateInput.pushPending();
		// abort pending lookups, also, so the menu can't appear after we've left the page
		this.addTemplateInput.closeLookupMenu();
		this.addTemplateButton.setDisabled( true );
	}
};

/**
 * @private
 */
ve.ui.MWTemplatePlaceholderPage.prototype.onTemplateInputChange = function () {
	this.addTemplateButton.setDisabled( this.addTemplateInput.getMWTitle() === null );
};
