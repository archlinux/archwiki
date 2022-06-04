/*!
 * VisualEditor MWWikitextPasteContextItem class.
 *
 * @copyright 2011-2019 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item shown after a rich text paste.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWWikitextPasteContextItem = function VeUiMWWikitextPasteContextItem() {
	// Parent constructor
	ve.ui.MWWikitextPasteContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwWikitextPasteContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWWikitextPasteContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWWikitextPasteContextItem.static.name = 'wikitextPaste';

ve.ui.MWWikitextPasteContextItem.static.icon = 'wikiText';

ve.ui.MWWikitextPasteContextItem.static.label = OO.ui.deferMsg( 'visualeditor-wikitextconvert-title' );

ve.ui.MWWikitextPasteContextItem.static.editable = false;

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWWikitextPasteContextItem.prototype.renderBody = function () {
	var fragment = this.model.fragment,
		doc = this.model.doc,
		contextRange = this.model.contextRange;

	var convertButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'visualeditor-wikitextconvert-convert' ),
		flags: [ 'progressive' ]
	} ).on( 'click', function () {
		fragment.insertDocument( doc, contextRange ).getPending().then( function () {
			fragment.collapseToEnd().select();
		} );
		// TODO: Show something if the promise (conversion) fails?
	} );

	this.$body.append(
		$( '<p>' ).text( ve.msg( 'visualeditor-wikitextconvert-message' ) )
	);

	if ( this.$foot ) {
		this.$foot.prepend( convertButton.$element );
	} else {
		this.$body.append( convertButton.$element );
	}
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWWikitextPasteContextItem );
