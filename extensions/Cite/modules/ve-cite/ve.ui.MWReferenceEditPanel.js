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
	 * @private
	 * @member {ve.dm.MWDocumentReferences|null}
	 */
	this.docRefs = null;
	/**
	 * @private
	 * @member {ve.dm.InternalList|null}
	 */
	this.internalList = null;
	/**
	 * @private
	 * @member {ve.dm.MWReferenceModel|null}
	 */
	this.referenceModel = null;
	/**
	 * @private
	 * @member {boolean}
	 */
	this.subRefMode = false;
	/**
	 * @private
	 * @member {string|null}
	 */
	this.originalGroup = null;
	/**
	 * @private
	 * @member {number}
	 */
	this.mainReuseCount = 0;

	/**
	 * @private
	 * @member {number}
	 */
	this.totalReuseCount = 0;

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

	this.changeAllCheckbox = new OO.ui.CheckboxInputWidget();
	this.changeAllCheckboxFieldset = new OO.ui.FieldLayout( this.changeAllCheckbox, {
		align: 'inline'
	} );
	this.changeAllCheckboxFieldset.toggle( false );

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
		this.changeAllCheckboxFieldset.$element,
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
 * all Cite related commands to discourage nesting of references.
 *
 * @see ve.dm.ElementLinearData#sanitize
 * @return {string[]} List of commands to exclude
 */
ve.ui.MWReferenceEditPanel.static.getExcludeCommands = function () {
	// Naming scheme for commands from MediaWiki:Cite-tool-definition.json is "cite-…"
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
 * @param {ve.dm.InternalList} internalList
 */
ve.ui.MWReferenceEditPanel.prototype.setInternalList = function ( internalList ) {
	this.internalList = internalList;
	this.docRefs = ve.dm.MWDocumentReferences.static.refsForDoc( internalList.getDocument() );
	this.referenceGroupInput.populateMenu( this.docRefs.getListGroupNames() );
};

/**
 * @param {ve.dm.MWReferenceModel} ref
 */
ve.ui.MWReferenceEditPanel.prototype.setReferenceForEditing = function ( ref ) {
	this.referenceModel = ref;
	this.subRefMode = ref.isSubRef();
	this.isInsertingSubRef = this.subRefMode && !this.documentHasContent();
	this.referenceListFieldset.setLabel( ve.msg( this.isInsertingSubRef ?
		'cite-ve-dialog-reference-editing-add-details' :
		'cite-ve-dialog-reference-editing-edit-details'
	) );
	// Note: listGroup is only available after a (possibly new) ref has been registered via
	// ve.dm.MWReferenceModel.insertInternalItem
	const groupRefs = this.docRefs.getGroupRefs( ref.getGroup() );

	this.totalReuseCount = groupRefs.getTotalUsageCount( ref.getListIndex() );
	if ( this.subRefMode ) {
		const allMainRefs = groupRefs.getRefUsages( ref.getMainListIndex() );
		const mainRefReuses = allMainRefs.filter(
			( node ) => !node.findParent( ve.dm.MWReferencesListNode )
		);
		this.mainReuseCount = mainRefReuses.length;
	} else {
		this.mainReuseCount = 0;
	}

	this.populateFormFields();
	this.updateReuseWarning();
	this.updatePreview();
	this.updateChangeAllCheckbox();
	this.helpLink.toggle( this.subRefMode );
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
 * @return {ve.dm.MWReferenceModel|null} edit all checkbox
 */
ve.ui.MWReferenceEditPanel.prototype.getChangeAllCheckboxState = function () {
	return this.changeAllCheckbox.selected;
};

/**
 * @private
 */
ve.ui.MWReferenceEditPanel.prototype.populateFormFields = function () {
	this.referenceTarget.setDocument(
		this.referenceModel.getDocument()
	);

	if ( this.subRefMode ) {
		this.referenceTarget.getSurface().setPlaceholder(
			ve.msg( 'cite-ve-dialog-reference-editing-add-details-placeholder' )
		);
	}
	this.optionsFieldset.toggle( !this.subRefMode );

	this.originalGroup = this.referenceModel.getGroup();

	// Set the group input while it's disabled, so this doesn't pop up the group-picker menu
	this.referenceGroupInput.setDisabled( true );
	this.referenceGroupInput.setValue( this.originalGroup );
	this.referenceGroupInput.setDisabled( false );
};

/**
 * @private
 */
ve.ui.MWReferenceEditPanel.prototype.updateReuseWarning = function () {
	this.reuseWarning
		// Don't show the reuse warning when it's a sub-ref, these currently split on edit
		.toggle( this.totalReuseCount > 1 && !this.subRefMode )
		.setLabel( ve.msg( 'cite-ve-dialog-reference-editing-reused-long', this.totalReuseCount ) );
};

/**
 * @private
 */
ve.ui.MWReferenceEditPanel.prototype.updatePreview = function () {
	if ( this.subRefMode ) {
		// Note: listGroup is only available after a (possibly new) ref has been registered via
		// ve.dm.MWReferenceModel.insertInternalItem
		const mainInternalItem = this.internalList.getItemNode(
			this.referenceModel.getMainListIndex()
		);
		this.referenceListPreview.$element.empty()
			.append( mainInternalItem && mainInternalItem.getLength() ?
				$( '<div>' )
					.append( new ve.ui.MWPreviewElement( mainInternalItem, { useView: true } ).$element ) :
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
	this.previewPanel.toggle( this.subRefMode );
};

ve.ui.MWReferenceEditPanel.prototype.updateChangeAllCheckbox = function () {
	// reset checkbox visibility
	this.changeAllCheckboxFieldset.toggle( false );

	if ( this.mainReuseCount > 1 && this.isInsertingSubRef ) {
		this.changeAllCheckboxFieldset.setLabel(
			ve.msg( 'cite-ve-dialog-reference-convert-all-checkbox-label', this.mainReuseCount )
		);
		this.changeAllCheckbox.setSelected( false );
		this.changeAllCheckboxFieldset.toggle( true );
	}

	if ( this.totalReuseCount > 1 && this.subRefMode && !this.isInsertingSubRef ) {
		this.changeAllCheckboxFieldset.setLabel(
			ve.msg( 'cite-ve-dialog-subreference-change-all-checkbox-label', this.totalReuseCount )
		);
		this.changeAllCheckbox.setSelected( true );
		this.changeAllCheckboxFieldset.toggle( true );
	}
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
