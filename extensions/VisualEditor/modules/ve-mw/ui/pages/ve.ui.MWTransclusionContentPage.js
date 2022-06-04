/*!
 * VisualEditor user interface MWTransclusionContentPage class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki transclusion dialog content page.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {ve.dm.MWTransclusionContentModel} content
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @cfg {jQuery} [$overlay] Overlay to render dropdowns in
 * @cfg {boolean} [isReadOnly] Page is read-only
 */
ve.ui.MWTransclusionContentPage = function VeUiMWTransclusionContentPage( content, name, config ) {
	var veConfig = mw.config.get( 'wgVisualEditorConfig' );

	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWTransclusionContentPage.super.call( this, name, config );

	// Properties
	this.content = content;
	this.textInput = new ve.ui.MWLazyMultilineTextInputWidget( {
		autosize: true,
		classes: [ 've-ui-mwTransclusionDialog-input' ]
	} )
		.setValue( this.content.serialize() )
		.setReadOnly( config.isReadOnly )
		.connect( this, { change: 'onTextInputChange' } );
	this.valueFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( veConfig.transclusionDialogNewSidebar ?
			'visualeditor-dialog-transclusion-wikitext' :
			'visualeditor-dialog-transclusion-content'
		),
		icon: 'wikiText',
		$content: this.textInput.$element
	} );

	// Initialization
	this.$element
		.addClass( 've-ui-mwTransclusionContentPage' )
		.append( this.valueFieldset.$element );

	if ( !config.isReadOnly && !veConfig.transclusionDialogNewSidebar ) {
		var removeButton = new OO.ui.ButtonWidget( {
			framed: false,
			icon: 'trash',
			title: ve.msg( 'visualeditor-dialog-transclusion-remove-content' ),
			flags: [ 'destructive' ],
			classes: [ 've-ui-mwTransclusionDialog-removeButton' ]
		} )
			.connect( this, { click: 'onRemoveButtonClick' } );

		removeButton.$element.appendTo( this.$element );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionContentPage, OO.ui.PageLayout );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionContentPage.prototype.setupOutlineItem = function () {
	this.outlineItem
		.setIcon( 'wikiText' )
		.setMovable( true )
		.setRemovable( true )
		.setLabel( ve.msg( 'visualeditor-dialog-transclusion-content' ) );
};

/**
 * @private
 */
ve.ui.MWTransclusionContentPage.prototype.onTextInputChange = function () {
	this.content.setWikitext( this.textInput.getValue() );
};

/**
 * @private
 */
ve.ui.MWTransclusionContentPage.prototype.onRemoveButtonClick = function () {
	this.content.remove();
};
