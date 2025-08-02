mw.editcheck.EditCheckFactory = function MWEditEditCheckFactory() {
	// Parent constructor
	mw.editcheck.EditCheckFactory.super.call( this, this.arguments );

	this.checksByListener = {
		onDocumentChange: [],
		onBeforeSave: []
	};
};

/* Inheritance */

OO.inheritClass( mw.editcheck.EditCheckFactory, OO.Factory );

/* Methods */

/**
 * @inheritdoc
 */
mw.editcheck.EditCheckFactory.prototype.register = function ( constructor, name ) {
	name = name || ( constructor.static && constructor.static.name );

	if ( typeof name !== 'string' || name === '' ) {
		throw new Error( 'Check names must be strings and must not be empty' );
	}
	if ( !( constructor.prototype instanceof mw.editcheck.BaseEditCheck ) ) {
		throw new Error( 'Checks must be subclasses of mw.editcheck.BaseEditCheck' );
	}
	if ( this.lookup( name ) === constructor ) {
		// Don't allow double registration as it would create duplicate
		// entries in various caches.
		return;
	}

	// Parent method
	mw.editcheck.EditCheckFactory.super.prototype.register.call( this, constructor, name );

	if ( constructor.prototype.onDocumentChange ) {
		this.checksByListener.onDocumentChange.push( name );
	}
	if ( constructor.prototype.onBeforeSave ) {
		this.checksByListener.onBeforeSave.push( name );
	}
};

/**
 * Get a list of registered command names.
 *
 * @param {string} listener Listener name, 'onDocumentChange', 'onBeforeSave'
 * @return {string[]}
 */
mw.editcheck.EditCheckFactory.prototype.getNamesByListener = function ( listener ) {
	if ( !this.checksByListener[ listener ] ) {
		throw new Error( `Unknown listener '${ listener }'` );
	}
	return this.checksByListener[ listener ];
};

/**
 * Create all checks actions for a given listener
 *
 * TODO: Rename to createAllActionsByListener
 *
 * @param {mw.editcheck.Controller} controller
 * @param {string} listener Listener name
 * @param {ve.dm.Surface} surfaceModel Surface model
 * @return {mw.editcheck.EditCheckActions[]} Actions, sorted by range
 */
mw.editcheck.EditCheckFactory.prototype.createAllByListener = function ( controller, listener, surfaceModel ) {
	let newChecks = [];
	this.getNamesByListener( listener ).forEach( ( checkName ) => {
		const check = this.create( checkName, controller, mw.editcheck.config[ checkName ] );
		if ( !check.canBeShown() ) {
			return;
		}
		const actions = check[ listener ]( surfaceModel );
		if ( actions.length > 0 ) {
			ve.batchPush( newChecks, actions );
		}
	} );
	newChecks.sort(
		( a, b ) => a.getHighlightSelections()[ 0 ].getCoveringRange().start - b.getHighlightSelections()[ 0 ].getCoveringRange().start
	);
	if ( mw.config.get( 'wgVisualEditorConfig' ).editCheckSingle && listener === 'onBeforeSave' ) {
		newChecks = newChecks.filter( ( action ) => action.getName() === 'addReference' );
		newChecks.splice( 1 );
	}
	return newChecks;
};

mw.editcheck.editCheckFactory = new mw.editcheck.EditCheckFactory();
