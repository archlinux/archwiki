/*!
 * VisualEditor user interface MWTemplatePage class.
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
 * @param {ve.dm.MWTemplateModel} template Template model
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @cfg {jQuery} [$overlay] Overlay to render dropdowns in
 * @cfg {boolean} [isReadOnly] Page is read-only
 */
ve.ui.MWTemplatePage = function VeUiMWTemplatePage( template, name, config ) {
	var link = template.getTemplateDataQueryTitle(),
		veConfig = mw.config.get( 'wgVisualEditorConfig' );

	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWTemplatePage.super.call( this, name, config );

	// Properties
	this.template = template;
	this.spec = template.getSpec();
	this.$description = $( '<div>' )
		.addClass( 've-ui-mwTemplatePage-description' );
	this.infoFieldset = new OO.ui.FieldsetLayout( {
		label: this.spec.getLabel(),
		icon: 'puzzle'
	} );

	// Initialization
	this.$description
		.text( this.spec.getDescription() );

	// The transcluded page may be dynamically generated or unspecified in the DOM
	// for other reasons (T68724). In that case we can't tell the user what the
	// template is called, nor link to the template page. However, if we know for
	// certain that the template doesn't exist, be explicit about it (T162694).
	var linkData = ve.init.platform.linkCache.getCached( '_missing/' + link ),
		knownAsMissing = link && linkData && linkData.missing;

	var key,
		messageStyle = 've-ui-mwTemplatePage-description-missing',
		$addMessageHere = this.$description;
	if ( this.spec.getDescription() ) {
		key = !veConfig.transclusionDialogNewSidebar ?
			'visualeditor-dialog-transclusion-more-template-description' :
			'visualeditor-dialog-transclusion-see-template';
		messageStyle = 've-ui-mwTemplatePage-description-extra';
		$addMessageHere = $( '<span>' );
		this.$description.append( $( '<hr>' ), $addMessageHere );
	} else if ( !veConfig.transclusionDialogNewSidebar ) {
		if ( knownAsMissing ) {
			key = 'visualeditor-dialog-transclusion-absent-template';
		} else if ( link ) {
			key = 'visualeditor-dialog-transclusion-no-template-description';
		}
		// Note this leaves dynamic template names like {{ {{foo}} }} without a message.
	} else if ( !link || knownAsMissing ) {
		var title;
		try {
			title = link && new mw.Title( link );
		} catch ( e ) {
		}
		// When {{User:Foo}} can be parsed as "Foo", we know the ":" is not syntax.
		key = title && title.getMain().indexOf( ':' ) === -1 ?
			'visualeditor-dialog-transclusion-template-title-nonexistent' :
			'visualeditor-dialog-transclusion-template-title-modifier';
	} else {
		key = 'visualeditor-dialog-transclusion-no-template-description';
	}

	if ( key ) {
		// The following messages are used here:
		// * visualeditor-dialog-transclusion-absent-template
		// * visualeditor-dialog-transclusion-more-template-description
		// * visualeditor-dialog-transclusion-no-template-description
		// * visualeditor-dialog-transclusion-see-template
		// * visualeditor-dialog-transclusion-template-title-modifier
		// * visualeditor-dialog-transclusion-template-title-nonexistent
		var $msg = mw.message( key, this.spec.getLabel(), link ).parseDom();
		// The following classes are used here:
		// * ve-ui-mwTemplatePage-description-extra
		// * ve-ui-mwTemplatePage-description-missing
		$addMessageHere.addClass( messageStyle ).append( $msg );
		ve.targetLinksToNewWindow( $addMessageHere[ 0 ] );
	}

	this.$description.find( 'a[href]' )
		.on( 'click', function () {
			ve.track( 'activity.transclusion', { action: 'template-doc-link-click' } );
		} );

	this.infoFieldset.$element
		.append( this.$description );

	if ( veConfig.transclusionDialogNewSidebar && !knownAsMissing ) {
		var noticeWidget;

		if ( !this.template.getSpec().getDocumentedParameterOrder().length ) {
			noticeWidget = new OO.ui.MessageWidget( {
				label: mw.message( 'visualeditor-dialog-transclusion-no-parameters-description' ).parseDom(),
				classes: [ 've-ui-mwTransclusionDialog-template-note' ]
			} );
		} else if ( !this.template.getSpec().isDocumented() ) {
			noticeWidget = new OO.ui.MessageWidget( {
				label: mw.message( 'visualeditor-dialog-transclusion-no-template-data-description', link ).parseDom(),
				classes: [ 've-ui-mwTransclusionDialog-template-note' ],
				type: 'warning'
			} );
		}

		if ( noticeWidget && noticeWidget.$element ) {
			ve.targetLinksToNewWindow( noticeWidget.$element[ 0 ] );
			this.infoFieldset.$element.append( noticeWidget.$element );
		}
	}

	this.$element
		.addClass( 've-ui-mwTemplatePage' )
		.append( this.infoFieldset.$element );

	if ( !config.isReadOnly ) {
		if ( !veConfig.transclusionDialogBackButton &&
			!veConfig.transclusionDialogNewSidebar
		) {
			var removeButton = new OO.ui.ButtonWidget( {
				framed: false,
				icon: 'trash',
				title: ve.msg( 'visualeditor-dialog-transclusion-remove-template' ),
				flags: [ 'destructive' ],
				classes: [ 've-ui-mwTransclusionDialog-removeButton' ]
			} )
				.connect( this, { click: 'onRemoveButtonClick' } );
			removeButton.$element.appendTo( this.$element );
		}

		if ( !veConfig.transclusionDialogNewSidebar ) {
			// This button is only shown as a last resort when this …TemplatePage is neither followed by
			// a …ParameterPage (i.e. the template doesn't have parameters) nor a
			// …ParameterPlaceholderPage (i.e. the parameter search widget isn't shown). This state
			// should be unreachable, but isn't. Hiding this is done via CSS.
			var addButton = new OO.ui.ButtonWidget( {
				framed: false,
				icon: 'parameter',
				label: ve.msg( 'visualeditor-dialog-transclusion-add-param' )
			} )
				.connect( this, { click: 'addPlaceholderParameter' } );
			$( '<div>' )
				.addClass( 've-ui-mwTemplatePage-more' )
				.append( addButton.$element )
				.appendTo( this.$element );
		}
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTemplatePage, OO.ui.PageLayout );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWTemplatePage.prototype.setupOutlineItem = function () {
	this.outlineItem
		.setIcon( 'puzzle' )
		.setMovable( true )
		.setRemovable( true )
		.setLabel( this.spec.getLabel() );
};

/**
 * @private
 */
ve.ui.MWTemplatePage.prototype.onRemoveButtonClick = function () {
	this.template.remove();
};

ve.ui.MWTemplatePage.prototype.addPlaceholderParameter = function () {
	this.template.addParameter( new ve.dm.MWParameterModel( this.template ) );
};
