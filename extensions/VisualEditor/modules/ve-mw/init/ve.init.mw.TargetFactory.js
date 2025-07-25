/*!
 * VisualEditor MediaWiki Initialization TargetFactory class.
 *
 * @copyright See AUTHORS.txt
 */

/**
 * Target factory.
 *
 * @class
 * @extends OO.Factory
 * @constructor
 */
ve.init.mw.TargetFactory = function VeInitMwTargetFactory() {
	// Parent constructor
	ve.init.mw.TargetFactory.super.call( this );
};

/* Inheritance */

OO.inheritClass( ve.init.mw.TargetFactory, OO.Factory );

/* Methods */

/**
 * @inheritdoc
 */
ve.init.mw.TargetFactory.prototype.register = function ( constructor ) {
	// Validate arguments
	if ( !( constructor.prototype instanceof ve.init.mw.Target ) ) {
		throw new Error( 'Targets must be subclasses of ve.init.mw.Target' );
	}

	// Parent method
	ve.init.mw.TargetFactory.super.prototype.register.apply( this, arguments );
};

/**
 * @inheritdoc
 */
ve.init.mw.TargetFactory.prototype.create = function () {
	// Parent method
	const target = ve.init.mw.TargetFactory.super.prototype.create.apply( this, arguments );

	/*
	 * This hook is designed to replace all the previous post-init
	 * article editor hooks:
	 *
	 * mw.hook( 've.newTarget' ).add( ( target ) => {
	 *     if ( target.constructor.static.name !== 'article' ) {
	 *         return;
	 *     }
	 *
	 *     // ve.activationComplete
	 *     target.on( 'surfaceReady', () => {
	 *         console.log( 'surface ready' );
	 *     } );
	 *
	 *     // ve.toolbarSaveButton.stateChanged
	 *     target.on( 'toolbarSaveButtonStateChanged', () => {
	 *         console.log( 'toolbar save button changed', target.toolbarSaveButton, target.isSaveable() );
	 *     } );
	 *
	 *     // ve.saveDialog.stateChanged
	 *     target.on( 'saveWorkflowChangePanel', () => {
	 *         console.log( 'save dialog change panel', ve.init.target.saveDialog );
	 *     } );
	 *
	 *     // ve.deactivationComplete
	 *     target.on( 'teardown', () => {
	 *         console.log( 'teardown', ve.init.target.edited );
	 *     } );
	 * } );
	 */
	mw.hook( 've.newTarget' ).fire( target );

	return target;
};

/* Initialization */
ve.init.mw.targetFactory = new ve.init.mw.TargetFactory();
