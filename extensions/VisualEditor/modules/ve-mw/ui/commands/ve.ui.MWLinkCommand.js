/*!
 * @copyright See AUTHORS.txt
 */

/**
 * Command to open the link inspector, unless the currently selected link's target is uneditable.
 *
 * @class
 * @extends ve.ui.Command
 *
 * @constructor
 */
ve.ui.MWLinkCommand = function VeUiMwLinkCommand() {
	// Parent constructor
	ve.ui.MWLinkCommand.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWLinkCommand, ve.ui.Command );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWLinkCommand.prototype.isExecutable = function ( fragment ) {
	if ( !ve.ui.MWLinkCommand.super.prototype.isExecutable.apply( this, arguments ) ) {
		return false;
	}

	const anns = fragment.getAnnotations( true );
	for ( const ann of anns.getAnnotationsByName( 'link/mwInternal' ).get() ) {
		if ( ann.getAttribute( 'hasGeneratedHref' ) ) {
			return false;
		}
	}

	return true;
};

/* Registration */

// Override VE core link commands
ve.ui.commandRegistry.register(
	new ve.ui.MWLinkCommand(
		'link', 'window', 'open',
		{ args: [ 'link' ], supportedSelections: [ 'linear' ] }
	)
);
ve.ui.commandRegistry.register(
	new ve.ui.MWLinkCommand(
		'linkNoExpand', 'window', 'open',
		{ args: [ 'link', { noExpand: true } ], supportedSelections: [ 'linear' ] }
	)
);
