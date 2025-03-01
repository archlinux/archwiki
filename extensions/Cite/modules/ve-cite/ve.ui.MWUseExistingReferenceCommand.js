'use strict';

/*!
 * VisualEditor UserInterface MediaWiki UseExistingReferenceCommand class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Reuse existing reference command.
 *
 * @constructor
 * @extends ve.ui.Command
 */
ve.ui.MWUseExistingReferenceCommand = function VeUiMWUseExistingReferenceCommand() {
	// Parent constructor
	ve.ui.MWUseExistingReferenceCommand.super.call(
		this, 'reference/existing', 'window', 'open',
		{ args: [ 'reference', { reuseReference: true } ], supportedSelections: [ 'linear' ] }
	);
};

/* Inheritance */

OO.inheritClass( ve.ui.MWUseExistingReferenceCommand, ve.ui.Command );

/* Methods */

/**
 * @override
 */
ve.ui.MWUseExistingReferenceCommand.prototype.isExecutable = function ( fragment ) {
	return ve.ui.MWUseExistingReferenceCommand.super.prototype.isExecutable.apply( this, arguments ) &&
		ve.dm.MWDocumentReferences.static.refsForDoc( fragment.getDocument() ).hasRefs();
};

/* Registration */

ve.ui.commandRegistry.register( new ve.ui.MWUseExistingReferenceCommand() );
