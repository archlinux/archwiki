'use strict';

/*!
 * @copyright 2025 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Help dialog shown for sub-referencing features.
 *
 * @constructor
 * @extends OO.ui.ProcessDialog
 * @param {Object} [config]
 */
ve.ui.MWSubReferenceHelpDialog = function VeUiMWSubReferenceHelpDialog( config ) {
	// Parent constructor
	ve.ui.MWSubReferenceHelpDialog.super.call( this, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWSubReferenceHelpDialog, OO.ui.ProcessDialog );

/* Static Properties */

ve.ui.MWSubReferenceHelpDialog.static.name = 'subrefHelp';

ve.ui.MWSubReferenceHelpDialog.static.title =
	OO.ui.deferMsg( 'cite-ve-dialog-subreference-help-dialog-title' );

ve.ui.MWSubReferenceHelpDialog.static.actions = [
	{
		action: 'close',
		flags: [ 'safe', 'close' ]
	},
	{
		action: 'dismiss',
		flags: [ 'progressive', 'primary' ],
		label: OO.ui.deferMsg( 'visualeditor-educationpopup-dismiss' )
	}
];

/* Methods */

/**
 * @override
 */
ve.ui.MWSubReferenceHelpDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWSubReferenceHelpDialog.super.prototype.initialize.call( this );

	// Initialization
	this.$element.addClass( 've-ui-mwSubReferenceHelpDialog' );
	this.$body.append(
		$( '<h3>' )
			.text( ve.msg( 'cite-ve-dialog-subreference-help-dialog-head' ) ),
		$( '<div>' )
			.append( mw.message( 'cite-ve-dialog-subreference-help-dialog-content' ).parseDom() ),
		$( '<p>' )
			// Needed for the external link icon
			.addClass( 'mw-parser-output' )
			// Not worth much more effort, this is temporary anyway
			.attr( 'style', 'margin-top: 2em;' )
			.append( $( '<a>' )
				.addClass( 'external' )
				.text( ve.msg( 'cite-ve-dialog-subreference-help-dialog-link-label' ) )
				.attr( {
					href: ve.msg( 'cite-ve-dialog-subreference-help-dialog-link' ),
					target: '_blank'
				} )

			)
			.on( 'click', () => {
				// Phabricator T403720
				ve.track( 'activity.subReference', { action: 'subref-tooltip-help-click' } );
			} )
	);
};

/**
 * @override
 */
ve.ui.MWSubReferenceHelpDialog.prototype.getActionProcess = function ( action ) {
	// Phabricator T403720
	if ( action === 'close' ) {
		ve.track( 'activity.subReference', { action: 'subref-tooltip-abort' } );
	}
	if ( action === 'dismiss' ) {
		ve.track( 'activity.subReference', { action: 'subref-tooltip-confim' } );
	}

	return new OO.ui.Process( () => {
		this.close( action );
	} );
};

module.exports = ve.ui.MWSubReferenceHelpDialog;
