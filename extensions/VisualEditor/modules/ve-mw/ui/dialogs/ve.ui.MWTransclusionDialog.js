/*!
 * VisualEditor user interface MWTransclusionDialog class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for inserting and editing MediaWiki transclusions, i.e. a sequence of one or more template
 * invocations that strictly belong to each other (e.g. because they are unbalanced), possibly
 * mixed with raw wikitext snippets.
 *
 * Note the base class {@see ve.ui.MWTemplateDialog} alone does not allow to manage more than a
 * single template invocation. Most of the code for this feature set is exclusive to this subclass.
 *
 * @class
 * @extends ve.ui.MWTemplateDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWTransclusionDialog = function VeUiMWTransclusionDialog( config ) {
	var veConfig = mw.config.get( 'wgVisualEditorConfig' );

	// Parent constructor
	ve.ui.MWTransclusionDialog.super.call( this, config );

	// Properties
	this.isSidebarExpanded = null;

	// Temporary feature flags
	this.useInlineDescriptions = veConfig.transclusionDialogInlineDescriptions;
	this.useBackButton = veConfig.transclusionDialogBackButton;
	this.useSearchImprovements = veConfig.templateSearchImprovements;
	this.useNewSidebar = veConfig.transclusionDialogNewSidebar;

	if ( this.useInlineDescriptions ) {
		this.$element.addClass( 've-ui-mwTransclusionDialog-bigger' );
	}
	if ( this.useSearchImprovements ) {
		this.$element.addClass( 've-ui-mwTransclusionDialog-enhancedSearch' );
	}
	if ( this.useNewSidebar ) {
		this.$element.addClass( 've-ui-mwTransclusionDialog-newSidebar' );
	}

	this.hotkeyTriggers = {};
	this.$element.on( 'keydown', this.onKeyDown.bind( this ) );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionDialog, ve.ui.MWTemplateDialog );

/* Static Properties */

ve.ui.MWTransclusionDialog.static.name = 'transclusion';

ve.ui.MWTransclusionDialog.static.actions = ve.ui.MWTemplateDialog.static.actions.concat( [
	{
		action: 'mode',
		modes: [ 'edit', 'insert' ],
		// HACK: Will be set later, but we want measurements to be accurate in the mean time, this
		// will not be needed when T93290 is resolved
		label: $( document.createTextNode( '\u00a0' ) )
	},
	{
		action: 'back',
		label: OO.ui.deferMsg( 'visualeditor-dialog-action-goback' ),
		modes: [ 'edit', 'insert' ],
		flags: [ 'safe', 'back' ]
	}
] );

/** @inheritdoc */
ve.ui.MWTransclusionDialog.static.bookletLayoutConfig = ve.extendObject(
	{},
	ve.ui.MWTemplateDialog.static.bookletLayoutConfig,
	{ outlined: true, editable: true }
);

ve.ui.MWTransclusionDialog.static.smallScreenMaxWidth = 540;

/* Methods */

/**
 * Handle outline controls move events.
 *
 * @private
 * @param {number} places Number of places to move the selected item
 */
ve.ui.MWTransclusionDialog.prototype.onOutlineControlsMove = function ( places ) {
	var part = this.transclusionModel.getPartFromId( this.findSelectedItemId() );
	if ( !part ) {
		return;
	}

	var newPlace = this.transclusionModel.getParts().indexOf( part ) + places;
	if ( newPlace < 0 || newPlace >= this.transclusionModel.getParts().length ) {
		return;
	}

	// Move part to new location, and if dialog is loaded switch to new part page
	var promise = this.transclusionModel.addPart( part, newPlace );
	if ( this.loaded && !this.preventReselection ) {
		promise.done( this.focusPart.bind( this, part.getId() ) );
	}
};

/**
 * Handle outline controls remove events.
 *
 * @private
 */
ve.ui.MWTransclusionDialog.prototype.onOutlineControlsRemove = function () {
	var controls = this.bookletLayout.getOutlineControls();
	// T301914: Safe-guard for when a keyboard shortcut triggers this, instead of the actual button
	if ( !controls.isVisible() ||
		!controls.removeButton.isVisible() ||
		controls.removeButton.isDisabled()
	) {
		return;
	}

	var itemId = this.findSelectedItemId(),
		part = this.transclusionModel.getPartFromId( itemId );
	// Check if the part is the actual template, or one of its parameters
	// TODO: This applies to the old sidebar only and can be removed later
	if ( part instanceof ve.dm.MWTemplateModel && itemId !== part.getId() ) {
		var param = part.getParameterFromId( itemId );
		if ( param ) {
			param.remove();
		}
	} else if ( part instanceof ve.dm.MWTransclusionPartModel ) {
		this.transclusionModel.removePart( part );
	}
};

/**
 * Create a new template part at the end of the transclusion.
 *
 * @private
 */
ve.ui.MWTransclusionDialog.prototype.addTemplatePlaceholder = function () {
	this.addPart( new ve.dm.MWTemplatePlaceholderModel( this.transclusionModel ) );
};

/**
 * Handle add content button click events.
 *
 * @private
 */
ve.ui.MWTransclusionDialog.prototype.addContent = function () {
	this.addPart( new ve.dm.MWTransclusionContentModel( this.transclusionModel ) );
};

/**
 * Handle add parameter button click events.
 *
 * @private
 */
ve.ui.MWTransclusionDialog.prototype.addParameter = function () {
	var part = this.transclusionModel.getPartFromId( this.findSelectedItemId() );
	if ( !( part instanceof ve.dm.MWTemplateModel ) ) {
		return;
	}

	// TODO: Use a distinct class for placeholder model rather than
	// these magical "empty" constants.
	var placeholderParameter = new ve.dm.MWParameterModel( part );
	part.addParameter( placeholderParameter );
	this.focusPart( placeholderParameter.getId() );

	if ( this.useInlineDescriptions ) {
		this.autoExpandSidebar();
	}
};

/**
 * Handle booklet layout page set events.
 *
 * @private
 * @param {OO.ui.PageLayout} page Active page
 */
ve.ui.MWTransclusionDialog.prototype.onBookletLayoutSetPage = function ( page ) {
	var isLastPlaceholder = page instanceof ve.ui.MWTemplatePlaceholderPage &&
			this.transclusionModel.isSingleTemplate(),
		acceptsNewParameters = page instanceof ve.ui.MWTemplatePage ||
			page instanceof ve.ui.MWParameterPage;

	this.addParameterButton.setDisabled( !acceptsNewParameters || this.isReadOnly() );
	this.bookletLayout.getOutlineControls().removeButton.toggle( !isLastPlaceholder );

	if ( this.pocSidebar ) {
		this.pocSidebar.setSelectionByPageName( page.getName() );
	}
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.onReplacePart = function ( removed, added ) {
	ve.ui.MWTransclusionDialog.super.prototype.onReplacePart.call( this, removed, added );
	var parts = this.transclusionModel.getParts();

	if ( parts.length === 0 ) {
		this.addParameterButton.setDisabled( true );
		this.addPart( new ve.dm.MWTemplatePlaceholderModel( this.transclusionModel ) );
	} else if ( parts.length > 1 ) {
		this.bookletLayout.getOutlineControls().toggle( true );
		this.$element.removeClass( 've-ui-mwTransclusionDialog-single-transclusion' );
	}

	// multipart message
	if ( this.useNewSidebar ) {
		this.bookletLayout.stackLayout.$element.prepend( this.multipartMessage.$element );
		this.multipartMessage.toggle( parts.length > 1 );
	}

	this.autoExpandSidebar();
	this.updateModeActionState();
	this.updateActionSet();
};

/**
 * @private
 */
ve.ui.MWTransclusionDialog.prototype.setupHotkeyTriggers = function () {
	// Lower-case modifier and key names as specified in {@see ve.ui.Trigger}
	var isMac = ve.getSystemPlatform() === 'mac',
		meta = isMac ? 'meta+' : 'ctrl+';
	var hotkeys = {
		addTemplate: meta + 'd',
		addWikitext: meta + 'shift+y',
		addParameter: meta + 'shift+d',
		moveUp: meta + 'shift+up',
		moveDown: meta + 'shift+down',
		remove: meta + 'delete',
		removeBackspace: meta + 'backspace'
	};

	var notInTextFields = /^(?!INPUT|TEXTAREA)/i;
	this.connectHotKeyBinding( hotkeys.addTemplate, this.addTemplatePlaceholder.bind( this ) );
	this.connectHotKeyBinding( hotkeys.addWikitext, this.addContent.bind( this ) );
	this.connectHotKeyBinding( hotkeys.addParameter, this.addParameter.bind( this ) );
	this.connectHotKeyBinding( hotkeys.moveUp, this.onOutlineControlsMove.bind( this, -1 ), notInTextFields );
	this.connectHotKeyBinding( hotkeys.moveDown, this.onOutlineControlsMove.bind( this, 1 ), notInTextFields );
	this.connectHotKeyBinding( hotkeys.remove, this.onOutlineControlsRemove.bind( this ), notInTextFields );
	if ( isMac ) {
		this.connectHotKeyBinding( hotkeys.removeBackspace, this.onOutlineControlsRemove.bind( this ), notInTextFields );
	}

	this.addHotkeyToTitle( this.addTemplateButton, hotkeys.addTemplate );
	this.addHotkeyToTitle( this.addContentButton, hotkeys.addWikitext );
	this.addHotkeyToTitle( this.addParameterButton, hotkeys.addParameter );

	var controls = this.bookletLayout.getOutlineControls();
	this.addHotkeyToTitle( controls.upButton, hotkeys.moveUp );
	this.addHotkeyToTitle( controls.downButton, hotkeys.moveDown );
	this.addHotkeyToTitle( controls.removeButton, hotkeys.remove );
};

/**
 * @private
 * @param {string} hotkey
 * @param {Function} handler
 * @param {RegExp} [validTypes]
 */
ve.ui.MWTransclusionDialog.prototype.connectHotKeyBinding = function ( hotkey, handler, validTypes ) {
	this.hotkeyTriggers[ hotkey ] = {
		handler: handler,
		validTypes: validTypes
	};
};

/**
 * @private
 * @param {OO.ui.mixin.TitledElement} element
 * @param {string} hotkey
 */
ve.ui.MWTransclusionDialog.prototype.addHotkeyToTitle = function ( element, hotkey ) {
	// Separated with a space as in {@see OO.ui.Tool.updateTitle}
	element.setTitle( element.getTitle() + ' ' + new ve.ui.Trigger( hotkey ).getMessage() );
};

/**
 * Handles key down events.
 *
 * @protected
 * @param {jQuery.Event} e Key down event
 */
ve.ui.MWTransclusionDialog.prototype.onKeyDown = function ( e ) {
	var hotkey = new ve.ui.Trigger( e ).toString(),
		trigger = this.hotkeyTriggers[ hotkey ];

	if ( trigger && ( !trigger.validTypes || trigger.validTypes.test( e.target.nodeName ) ) ) {
		trigger.handler();
		e.preventDefault();
		e.stopPropagation();
	}
};

/**
 * @return {string|undefined} Any id, including slash-delimited template parameter ids
 */
ve.ui.MWTransclusionDialog.prototype.findSelectedItemId = function () {
	if ( this.pocSidebar ) {
		// TODO: This can't return parameter ids any more when the old sidebar is gone
		return this.pocSidebar.findSelectedPartId();
	}

	var item = this.bookletLayout.getOutline().findSelectedItem();
	return item && item.getData();
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.getPageFromPart = function ( part ) {
	var page = ve.ui.MWTransclusionDialog.super.prototype.getPageFromPart.call( this, part );
	if ( !page && part instanceof ve.dm.MWTransclusionContentModel ) {
		return new ve.ui.MWTransclusionContentPage( part, part.getId(), { $overlay: this.$overlay, isReadOnly: this.isReadOnly() } );
	}
	return page;
};

/**
 * Automatically expand or collapse the sidebar according to default logic.
 *
 * @protected
 */
ve.ui.MWTransclusionDialog.prototype.autoExpandSidebar = function () {
	var expandSidebar;

	if ( this.useInlineDescriptions ) {
		var isSmallScreen = this.isNarrowScreen();

		var showOtherActions = isSmallScreen ||
			this.actions.getOthers().some( function ( action ) {
				// Check for unknown actions, show the toolbar if any are available.
				return action.action !== 'mode';
			} );
		this.actions.forEach( { actions: [ 'mode' ] }, function ( action ) {
			action.toggle( isSmallScreen );
		} );
		this.$otherActions.toggleClass( 'oo-ui-element-hidden', !showOtherActions );

		if ( isSmallScreen && this.transclusionModel.isEmpty() ) {
			expandSidebar = false;
		} else if ( isSmallScreen &&
			// eslint-disable-next-line no-jquery/no-class-state
			this.$content.hasClass( 've-ui-mwTransclusionDialog-small-screen' )
		) {
			// We did this already. If the sidebar is visible or not is now the user's decision.
			return;
		} else {
			expandSidebar = !isSmallScreen;
		}

		this.$content.toggleClass( 've-ui-mwTransclusionDialog-small-screen', isSmallScreen );
	} else {
		expandSidebar = !this.transclusionModel.isSingleTemplate();
	}

	this.toggleSidebar( expandSidebar );
};

/**
 * Set if the sidebar is visible (which means the dialog is expanded), or collapsed.
 *
 * @param {boolean} expandSidebar
 * @private
 */
ve.ui.MWTransclusionDialog.prototype.toggleSidebar = function ( expandSidebar ) {
	if ( this.isSidebarExpanded === expandSidebar ) {
		return;
	}

	this.isSidebarExpanded = expandSidebar;
	this.$content
		.toggleClass( 've-ui-mwTransclusionDialog-collapsed', !expandSidebar )
		.toggleClass( 've-ui-mwTransclusionDialog-expanded', expandSidebar );

	var dialogSizeSidebarExpanded = this.useInlineDescriptions ? 'larger' : 'large';
	var dialogSizeSidebarCollapsed = this.useNewSidebar ? dialogSizeSidebarExpanded : 'medium';
	this.ignoreNextWindowResizeEvent = true;
	this.setSize( expandSidebar ? dialogSizeSidebarExpanded : dialogSizeSidebarCollapsed );

	this.bookletLayout.toggleOutline( expandSidebar );
	this.updateTitle();
	this.updateModeActionState();

	// HACK blur any active input so that its dropdown will be hidden and won't end
	// up being mispositioned
	this.$content.find( 'input:focus' ).trigger( 'blur' );

	if ( this.useInlineDescriptions && this.pocSidebar && this.loaded && this.isNarrowScreen() ) {
		// Reapply selection and scrolling when switching between panes.
		// FIXME: decouple from descendants
		var selectedPage = this.bookletLayout.stackLayout.getCurrentItem();
		if ( selectedPage ) {
			var name = selectedPage.getName();
			var dialog = this;
			// Align whichever panel is becoming visible, after animation completes.
			// TODO: Should hook onto an animation promise—but is this possible when pure CSS?
			setTimeout( function () {
				if ( expandSidebar ) {
					dialog.pocSidebar.setSelectionByPageName( name );
				} else {
					selectedPage.scrollElementIntoView();
					// TODO: Find a reliable way to refocus.
					// dialog.focusPart( name );
				}
			}, OO.ui.theme.getDialogTransitionDuration() );
		}
	}
};

ve.ui.MWTransclusionDialog.prototype.isNarrowScreen = function () {
	return $( window ).width() <= this.constructor.static.smallScreenMaxWidth;
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.updateTitle = function () {
	if ( !this.transclusionModel.isSingleTemplate() ) {
		this.title.setLabel( ve.msg( 'visualeditor-dialog-transclusion-title-edit-transclusion' ) );
	} else {
		// Parent method
		ve.ui.MWTransclusionDialog.super.prototype.updateTitle.call( this );
	}
};

/**
 * Update the state of the 'mode' action
 *
 * @private
 */
ve.ui.MWTransclusionDialog.prototype.updateModeActionState = function () {
	var isExpanded = this.isSidebarExpanded,
		label = ve.msg( isExpanded ?
			'visualeditor-dialog-transclusion-collapse-options' :
			'visualeditor-dialog-transclusion-expand-options' );

	this.actions.forEach( { actions: [ 'mode' ] }, function ( action ) {
		action.setLabel( label );
		action.$button.attr( 'aria-expanded', isExpanded ? 1 : 0 );
	} );

	// Old sidebar: Only a single template can be collapsed, except it's still the initial
	// placeholder.
	// New sidebar: The button is only visible on very narrow screens, {@see autoExpandSidebar}.
	// It's always needed, except in the initial placeholder state.
	var isInitialState = !isExpanded && this.transclusionModel.isEmpty(),
		canCollapse = ( this.transclusionModel.isSingleTemplate() || this.useInlineDescriptions ) &&
			!isInitialState;
	this.actions.setAbilities( { mode: canCollapse } );
};

/**
 * Add a part to the transclusion.
 *
 * @param {ve.dm.MWTransclusionPartModel} part Part to add
 */
ve.ui.MWTransclusionDialog.prototype.addPart = function ( part ) {
	var parts = this.transclusionModel.getParts(),
		selectedPart = this.transclusionModel.getPartFromId( this.findSelectedItemId() );
	// Insert after selected part, or at the end if nothing is selected
	var index = selectedPart ? parts.indexOf( selectedPart ) + 1 : parts.length;
	// Add the part, and if dialog is loaded switch to part page
	var promise = this.transclusionModel.addPart( part, index );
	if ( this.loaded && !this.preventReselection ) {
		promise.done( this.focusPart.bind( this, part.getId() ) );
	}
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.getActionProcess = function ( action ) {
	var willLoseProgress = this.getMode() === 'insert' ?
		// A new template with no parameters is not considered valuable.
		this.transclusionModel.containsValuableData() :
		// The user has changed a parameter, and is not on the template search page.
		( this.altered && !this.transclusionModel.isEmpty() );

	switch ( action ) {
		case 'back':
			return new OO.ui.Process( function () {
				if ( willLoseProgress ) {
					ve.ui.MWConfirmationDialog.static.confirm(
						'visualeditor-dialog-transclusion-back-confirmation-prompt',
						this.resetDialog.bind( this )
					);
				} else {
					this.resetDialog();
				}
			}, this );
		case 'mode':
			return new OO.ui.Process( function () {
				this.toggleSidebar( !this.isSidebarExpanded );
			}, this );
		case '':
			// close action
			if ( willLoseProgress ) {
				return new OO.ui.Process( function () {
					ve.ui.MWConfirmationDialog.static.confirm(
						'visualeditor-dialog-transclusion-close-confirmation-prompt',
						this.close.bind( this ) );
				}, this );
			}
	}
	return ve.ui.MWTransclusionDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * Update the widgets in the dialog's action bar.
 *
 * @private
 */
ve.ui.MWTransclusionDialog.prototype.updateActionSet = function () {
	var backButton = this.actions.get( { flags: [ 'back' ] } ).pop(),
		saveButton = this.actions.get( { actions: [ 'done' ] } ).pop();

	if ( saveButton && this.getMode() === 'edit' ) {
		saveButton.setLabel( ve.msg( 'visualeditor-dialog-transclusion-action-save' ) );
	}

	if ( this.useBackButton ) {
		var closeButton = this.actions.get( { flags: [ 'close' ] } ).pop(),
			canGoBack = this.getMode() === 'insert' && !this.transclusionModel.isEmpty();

		closeButton.toggle( !canGoBack );
		backButton.toggle( canGoBack );
	} else {
		backButton.toggle( false );
	}
};

/**
 * Revert the dialog back to its initial state.
 *
 * @private
 */
ve.ui.MWTransclusionDialog.prototype.resetDialog = function () {
	var target = this;
	this.transclusionModel.reset();
	if ( this.pocSidebar ) {
		this.pocSidebar.clear();
	}
	this.bookletLayout.clearPages();
	this.transclusionModel
		.addPart( new ve.dm.MWTemplatePlaceholderModel( this.transclusionModel ), 0 )
		.done( function () {
			target.autoExpandSidebar();
		} );
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWTransclusionDialog.super.prototype.initialize.call( this );

	// Properties
	this.addTemplateButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'puzzle',
		title: ve.msg( 'visualeditor-dialog-transclusion-add-template' )
	} );
	this.addContentButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'wikiText',
		title: ve.msg( this.useNewSidebar ?
			'visualeditor-dialog-transclusion-add-wikitext' :
			'visualeditor-dialog-transclusion-add-content' )
	} );
	this.addParameterButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'parameter',
		title: ve.msg( 'visualeditor-dialog-transclusion-add-param' )
	} );

	this.bookletLayout.getOutlineControls().addItems( [ this.addTemplateButton, this.addContentButton ] );
	if ( !this.useNewSidebar ) {
		this.bookletLayout.getOutlineControls().addItems( [ this.addParameterButton ] );
	}

	this.setupHotkeyTriggers();

	// multipart message gets attached in onReplacePart()
	this.multipartMessage = new OO.ui.MessageWidget( {
		label: mw.message( 'visualeditor-dialog-transclusion-multipart-message' ).parseDom(),
		classes: [ 've-ui-mwTransclusionDialog-multipart-message' ]
	} );
	ve.targetLinksToNewWindow( this.multipartMessage.$element[ 0 ] );

	if ( this.useNewSidebar || this.useInlineDescriptions ) {
		var helpPopup = new ve.ui.MWFloatingHelpElement( {
			label: mw.message( 'visualeditor-dialog-transclusion-help-title' ).text(),
			title: mw.message( 'visualeditor-dialog-transclusion-help-title' ).text(),
			$message: new OO.ui.FieldsetLayout( {
				items: [
					new OO.ui.LabelWidget( {
						label: mw.message( 'visualeditor-dialog-transclusion-help-message' ).text()
					} ),
					this.getMessageButton( 'visualeditor-dialog-transclusion-help-page-help', 'helpNotice' ),
					this.getMessageButton( 'visualeditor-dialog-transclusion-help-page-shortcuts', 'keyboard' ),
					this.getMessageButton( 'visualeditor-dialog-transclusion-help-page-feedback', 'feedback' )
				],
				classes: [ 've-ui-mwTransclusionDialog-floatingHelpElement-fieldsetLayout' ]
			} ).$element
		} );
		helpPopup.$element.addClass( 've-ui-mwTransclusionDialog-floatingHelpElement' );
		helpPopup.$element.appendTo( this.$body );
	}

	// Events
	if ( this.useInlineDescriptions ) {
		this.getManager().connect( this, { resize: ve.debounce( this.onWindowResize.bind( this ) ) } );
	}
	this.bookletLayout.connect( this, { set: 'onBookletLayoutSetPage' } );
	this.bookletLayout.$menu.find( '[ role="listbox" ]' ).first()
		.attr( 'aria-label', ve.msg( 'visualeditor-dialog-transclusion-templates-menu-aria-label' ) );
	this.addTemplateButton.connect( this, { click: 'addTemplatePlaceholder' } );
	this.addContentButton.connect( this, { click: 'addContent' } );
	this.addParameterButton.connect( this, { click: 'addParameter' } );
	this.bookletLayout.getOutlineControls().connect( this, {
		move: 'onOutlineControlsMove',
		remove: 'onOutlineControlsRemove'
	} );
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.getSetupProcess = function ( data ) {
	this.onTearDownCallback = data && data.onTearDownCallback;

	return ve.ui.MWTransclusionDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var isReadOnly = this.isReadOnly();
			this.addTemplateButton.setDisabled( isReadOnly );
			this.addContentButton.setDisabled( isReadOnly );
			this.addParameterButton.setDisabled( isReadOnly );
			this.bookletLayout.getOutlineControls().setAbilities( {
				move: !isReadOnly,
				remove: !isReadOnly
			} );

			if ( this.useNewSidebar ) {
				this.bookletLayout.getOutlineControls().toggle( !this.transclusionModel.isSingleTemplate() );
				this.$element.toggleClass(
					've-ui-mwTransclusionDialog-single-transclusion',
					this.transclusionModel.isSingleTemplate()
				);
			}

			this.updateModeActionState();
			this.autoExpandSidebar();
		}, this );
};

/** @inheritdoc */
ve.ui.MWTransclusionDialog.prototype.getTeardownProcess = function () {
	if ( this.onTearDownCallback ) {
		this.onTearDownCallback();
	}

	return ve.ui.MWTransclusionDialog.super.prototype.getTeardownProcess.apply( this, arguments );
};

/**
 * @private
 */
ve.ui.MWTransclusionDialog.prototype.onWindowResize = function () {
	if ( this.transclusionModel && !this.ignoreNextWindowResizeEvent ) {
		this.autoExpandSidebar();
	}
	this.ignoreNextWindowResizeEvent = false;
};

/**
 * @inheritdoc
 *
 * Temporary override to increase dialog size when a feature flag is enabled.
 */
ve.ui.MWTransclusionDialog.prototype.getSizeProperties = function () {
	var sizeProps = ve.ui.MWTransclusionDialog.super.prototype.getSizeProperties.call( this );

	if ( this.useInlineDescriptions ) {
		sizeProps = ve.extendObject( { height: '90%' }, sizeProps );
	}

	return sizeProps;
};

/**
 * Converts a message link into an OO.ui.ButtonWidget with an icon.
 *
 * @private
 * @param {string} message i18n message key
 * @param {string} icon icon name
 * @return {OO.ui.ButtonWidget}
 */
ve.ui.MWTemplateDialog.prototype.getMessageButton = function ( message, icon ) {
	// Messages that can be used here:
	// * visualeditor-dialog-transclusion-help-page-help
	// * visualeditor-dialog-transclusion-help-page-feedback
	var $link = mw.message( message ).parseDom(),
		button = new OO.ui.ButtonWidget( {
			label: $link.text(),
			href: $link.attr( 'href' ),
			target: '_blank',
			flags: 'progressive',
			icon: icon,
			framed: false
		} );
	button.$button.attr( 'role', 'link' );
	return button;
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWTransclusionDialog );
