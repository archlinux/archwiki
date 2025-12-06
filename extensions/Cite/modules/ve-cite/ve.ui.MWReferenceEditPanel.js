'use strict';

/*!
 * VisualEditor UserInterface MWReferenceEditPanel class.
 *
 * @copyright 2024 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

const MWReferenceGroupInputWidget = require( './ve.ui.MWReferenceGroupInputWidget.js' );

/**
 * Creates a ve.ui.MWReferenceEditPanel object.
 *
 * @constructor
 * @extends OO.ui.PanelLayout
 * @param {Object} [config={}]
 * @param {jQuery} [config.$overlay] Layer to render options dropdown outside of the parent dialog
 */
ve.ui.MWReferenceEditPanel = function VeUiMWReferenceEditPanel( config ) {
	config = config || {};

	// Parent constructor
	ve.ui.MWReferenceEditPanel.super.call( this, { scrollable: true } );

	// Initialization
	this.$element.addClass( 've-ui-mwReferenceEditPanel' );

	// Properties
	/**
	 * @member {ve.dm.MWDocumentReferences|null}
	 */
	this.docRefs = null;
	/**
	 * @member {ve.dm.MWReferenceModel|null}
	 */
	this.referenceModel = null;
	/**
	 * @member {string|null}
	 */
	this.originalGroup = null;

	// Create content editor
	this.referenceTarget = ve.init.target.createTargetWidget(
		{
			includeCommands: null,
			excludeCommands: this.constructor.static.getExcludeCommands(),
			importRules: this.constructor.static.getImportRules(),
			inDialog: 'reference',
			placeholder: ve.msg( 'cite-ve-dialog-reference-placeholder' )
		}
	);
	this.contentFieldset = new OO.ui.FieldsetLayout();
	this.contentFieldset.$element.append(
		this.referenceTarget.$element
	);

	// Create group edit
	this.optionsFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'cite-ve-dialog-reference-options-section' ),
		icon: 'settings'
	} );
	this.referenceGroupInput = new MWReferenceGroupInputWidget( {
		$overlay: config.$overlay,
		emptyGroupName: ve.msg( 'cite-ve-dialog-reference-options-group-placeholder' )
	} );
	this.referenceGroupField = new OO.ui.FieldLayout( this.referenceGroupInput, {
		align: 'top',
		label: ve.msg( 'cite-ve-dialog-reference-options-group-label' )
	} );
	this.optionsFieldset.addItems( [ this.referenceGroupField ] );

	this.referenceListPreview = new OO.ui.Layout( {
		classes: [ 've-ui-mwReferenceDialog-referencePreview' ]
	} );

	this.referenceListFieldset = new OO.ui.FieldsetLayout( {
		classes: [ 've-ui-mwReferenceDialog-referencePreview-fieldset' ],
		items: [ this.referenceListPreview ]
	} );

	this.reuseWarning = new OO.ui.MessageWidget( {
		icon: 'alert',
		inline: true,
		classes: [ 've-ui-mwReferenceDialog-reuseWarning' ]
	} );

	this.helpLink = new OO.ui.LabelWidget( {
		classes: [
			// Needed for the external link icon
			'mw-parser-output',
			've-ui-mwReferenceDialog-helpLink'
		],
		label: $( '<a>' )
			.addClass( 'external' )
			.attr( {
				href: ve.msg( 'cite-ve-dialog-subreference-help-dialog-link-ve' ),
				target: '_blank'
			} )
			.text( ve.msg( 'cite-ve-dialog-subreference-help-dialog-link-label' ) )
			.on( 'click', () => {
				// Phabricator T403720
				ve.track( 'activity.subReference', { action: 'subref-edit-help-click' } );
			} )
	} );

	this.referenceTarget.connect( this, { change: 'onInputChange' } );
	this.referenceGroupInput.connect( this, { change: 'onInputChange' } );

	this.previewPanel = new OO.ui.Layout( { classes: [ 've-ui-mwReference-details-preview-panel' ] } );
	this.previewPanel.$element.append(
		this.referenceListFieldset.$element
	);
	this.editPanel = new OO.ui.Layout();
	this.editPanel.$element.append(
		this.reuseWarning.$element,
		this.contentFieldset.$element,
		this.optionsFieldset.$element,
		this.helpLink.$element
	);

	// Append to panel element
	this.$element.append(
		this.previewPanel.$element,
		this.editPanel.$element
	);
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferenceEditPanel, OO.ui.PanelLayout );

/* Events */
/**
 * A `change` event is emitted whenever the content or value of field is changed.
 *
 * @event ve.ui.MWReferenceEditPanel#change
 * @param {Object} change
 * @param {boolean} [change.isModified] If changes to the original content or values have been made
 * @param {boolean} [change.hasContent] If there's non empty content set
 */

/* Static Properties */
ve.ui.MWReferenceEditPanel.static.excludeCommands = [
	// No formatting
	'paragraph',
	'heading1',
	'heading2',
	'heading3',
	'heading4',
	'heading5',
	'heading6',
	'preformatted',
	'blockquote',
	// No tables
	'insertTable',
	'deleteTable',
	'mergeCells',
	'tableCaption',
	'tableCellHeader',
	'tableCellData',
	// No structure
	'bullet',
	'bulletWrapOnce',
	'number',
	'numberWrapOnce',
	// References
	'reference',
	'reference/existing',
	'citoid',
	'referencesList'
];

/**
 * Get the list of disallowed commands for the surface widget to edit the content. This includes
 * all Cite related commands to disencourage nesting of references.
 *
 * @see ve.dm.ElementLinearData#sanitize
 * @return {string[]} List of commands to exclude
 */
ve.ui.MWReferenceEditPanel.static.getExcludeCommands = function () {
	// Naming scheme for commands from MediaWiki:cite-tool-definition.json is "cite-…"
	const citeCommands = ve.init.target.getSurface().commandRegistry.getNames()
		.filter( ( name ) => name.includes( 'cite-' ) );

	return ve.ui.MWReferenceEditPanel.static.excludeCommands.concat( citeCommands );
};

/**
 * Get the import rules for the surface widget to edit the content
 *
 * @see ve.dm.ElementLinearData#sanitize
 * @return {Object} Import rules
 */
ve.ui.MWReferenceEditPanel.static.getImportRules = function () {
	const rules = ve.copy( ve.init.target.constructor.static.importRules );
	return ve.extendObject(
		rules,
		{
			all: {
				blacklist: ve.extendObject(
					{
						// Nested references are impossible
						mwReference: true,
						mwReferencesList: true,
						// Lists and tables are actually possible in wikitext with a leading
						// line break but we prevent creating these with the UI
						list: true,
						listItem: true,
						definitionList: true,
						definitionListItem: true,
						table: true,
						tableCaption: true,
						tableSection: true,
						tableRow: true,
						tableCell: true,
						mwTable: true,
						mwTransclusionTableCell: true
					},
					ve.getProp( rules, 'all', 'blacklist' )
				),
				// Headings are not possible in wikitext without HTML
				conversions: ve.extendObject(
					{
						mwHeading: 'paragraph'
					},
					ve.getProp( rules, 'all', 'conversions' )
				)
			}
		}
	);
};

/**
 * @param {ve.dm.MWDocumentReferences} docRefs
 */
ve.ui.MWReferenceEditPanel.prototype.setDocumentReferences = function ( docRefs ) {
	this.docRefs = docRefs;
	this.referenceGroupInput.populateMenu( docRefs.getAllGroupNames() );
};

/**
 * @param {ve.dm.MWReferenceModel} ref
 */
ve.ui.MWReferenceEditPanel.prototype.setReferenceForEditing = function ( ref ) {
	this.referenceModel = ref;
	const isInsertingSubRef = ref.isSubRef() && !this.documentHasContent();

	this.referenceListFieldset.setLabel( ve.msg( isInsertingSubRef ?
		'cite-ve-dialog-reference-editing-add-details' :
		'cite-ve-dialog-reference-editing-edit-details'
	) );

	this.setFormFieldsFromRef( ref );
	this.updateReuseWarningFromRef( ref );
	this.updatePreviewFromRef( ref );
	this.helpLink.toggle( ref.isSubRef() );
};

/**
 * @return {ve.dm.MWReferenceModel|null} Updated reference
 */
ve.ui.MWReferenceEditPanel.prototype.getReferenceFromEditing = function () {
	if ( this.referenceModel ) {
		this.referenceModel.setGroup( this.referenceGroupInput.getValue() );
	}

	return this.referenceModel;
};

/**
 * @private
 * @param {ve.dm.MWReferenceModel} ref
 */
ve.ui.MWReferenceEditPanel.prototype.setFormFieldsFromRef = function ( ref ) {
	this.referenceTarget.setDocument( ref.getDocument() );

	if ( ref.isSubRef() ) {
		this.referenceTarget.getSurface().setPlaceholder(
			ve.msg( 'cite-ve-dialog-reference-editing-add-details-placeholder' )
		);
	}
	this.optionsFieldset.toggle( !ref.isSubRef() );

	this.originalGroup = ref.getGroup();

	// Set the group input while it's disabled, so this doesn't pop up the group-picker menu
	this.referenceGroupInput.setDisabled( true );
	this.referenceGroupInput.setValue( this.originalGroup );
	this.referenceGroupInput.setDisabled( false );
};

/**
 * @private
 * @param {ve.dm.MWReferenceModel} ref
 */
ve.ui.MWReferenceEditPanel.prototype.updateReuseWarningFromRef = function ( ref ) {
	// Note: listGroup is only available after a (possibly new) ref has been registered via
	// ve.dm.MWReferenceModel.insertInternalItem
	const totalUsageCount = this.docRefs.getGroupRefs( ref.getGroup() )
		.getTotalUsageCount( ref.getListKey() );
	this.reuseWarning
		// Don't show the reuse warning when it's a sub-ref, these currently split on edit
		.toggle( totalUsageCount > 1 && !ref.mainRefKey )
		.setLabel( ve.msg( 'cite-ve-dialog-reference-editing-reused-long', totalUsageCount ) );
};

/**
 * @private
 * @param {ve.dm.MWReferenceModel} ref
 */
ve.ui.MWReferenceEditPanel.prototype.updatePreviewFromRef = function ( ref ) {
	if ( ref.isSubRef() ) {
		// Note: listGroup is only available after a (possibly new) ref has been registered via
		// ve.dm.MWReferenceModel.insertInternalItem
		const mainRefNode = this.docRefs.getGroupRefs( ref.getGroup() )
			.getInternalModelNode( ref.mainRefKey );
		this.referenceListPreview.$element.empty()
			.append( mainRefNode ?
				$( '<div>' )
					.append( new ve.ui.MWPreviewElement( mainRefNode, { useView: true } ).$element ) :
				$( '<div>' )
					.addClass( 've-ui-mwReferenceContextItem-muted' )
					.text( ve.msg( 'cite-ve-dialog-reference-missing-parent-ref' ) )
			)
			.append( $( '<div>' )
				.addClass( 've-ui-mwReference-details-preview-item' )
				.append(
					$( '<span>' )
						.addClass( 've-ui-mwReference-icon-newline' )
						// Extra <span> needed because the RTL CSS uses scaleX() as well
						.append( $( '<span>' ) ),
					$( '<span>' )
						.addClass( 've-ui-mwReferenceContextItem-muted' )
						.text( ve.msg( 'cite-ve-dialog-reference-editing-details-placeholder' ) )
				)
			);
	}
	this.previewPanel.toggle( ref.isSubRef() );
};

/**
 * Handle reference change events
 *
 * @private
 * @fires ve.ui.MWReferenceEditPanel#change
 */
ve.ui.MWReferenceEditPanel.prototype.onInputChange = function () {
	this.emit( 'change', {
		isModified: this.isModified(),
		hasContent: this.documentHasContent()
	} );
};

/**
 * Determine whether the reference document we're editing has any content.
 *
 * @private
 * @return {boolean} Document has content
 */
ve.ui.MWReferenceEditPanel.prototype.documentHasContent = function () {
	// TODO: Check for other types of empty, e.g. only whitespace?
	return this.referenceModel && this.referenceModel.getDocument().data.hasContent();
};

/**
 * Determine whether any changes have been made (and haven't been undone).
 *
 * @private
 * @return {boolean} Changes have been made
 */
ve.ui.MWReferenceEditPanel.prototype.isModified = function () {
	return this.documentHasContent() && ( this.referenceTarget.hasBeenModified() ||
			this.referenceGroupInput.getValue() !== this.originalGroup );
};

/**
 * Focus the edit panel
 */
ve.ui.MWReferenceEditPanel.prototype.focus = function () {
	this.referenceTarget.focus();
};

/**
 * @param {boolean} [readOnly=false]
 */
ve.ui.MWReferenceEditPanel.prototype.setReadOnly = function ( readOnly ) {
	this.referenceTarget.setReadOnly( !!readOnly );
	this.referenceGroupInput.setReadOnly( !!readOnly );
};

ve.ui.MWReferenceEditPanel.prototype.clear = function () {
	this.referenceTarget.clear();
	this.referenceModel = null;
};

module.exports = ve.ui.MWReferenceEditPanel;
