mw.loader.using( 'ext.visualEditor.targetLoader' ).then( () => {
	mw.libs.ve.targetLoader.addPlugin( () => {

		ve.init.mw.TitleBlacklistSaveErrorHandler = function () {};

		OO.inheritClass( ve.init.mw.TitleBlacklistSaveErrorHandler, ve.init.mw.SaveErrorHandler );

		ve.init.mw.TitleBlacklistSaveErrorHandler.static.name = 'titleBlacklist';

		ve.init.mw.TitleBlacklistSaveErrorHandler.static.matchFunction = function ( data ) {
			return data.errors && data.errors.some( ( err ) => err.code === 'titleblacklist-forbidden' );
		};

		ve.init.mw.TitleBlacklistSaveErrorHandler.static.process = function ( data, target ) {
			// Handle errors from Extension:TitleBlacklist
			target.showSaveError( target.extractErrorMessages( data ) );
			// Emit event for tracking. TODO: This is a bad design
			target.emit( 'saveErrorTitleBlacklist' );
		};

		ve.init.mw.saveErrorHandlerFactory.register( ve.init.mw.TitleBlacklistSaveErrorHandler );
	} );
} );
