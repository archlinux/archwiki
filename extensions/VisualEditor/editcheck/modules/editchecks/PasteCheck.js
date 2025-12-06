mw.editcheck.PasteCheck = function MWPasteCheck() {
	// Parent constructor
	mw.editcheck.PasteCheck.super.apply( this, arguments );
};

OO.inheritClass( mw.editcheck.PasteCheck, mw.editcheck.BaseEditCheck );

mw.editcheck.PasteCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.BaseEditCheck.static.defaultConfig, {
	enabled: false,
	minimumCharacters: 50
} );

mw.editcheck.PasteCheck.static.title = ve.msg( 'editcheck-copyvio-title' );

mw.editcheck.PasteCheck.static.description = ve.msg( 'editcheck-copyvio-description' );

mw.editcheck.PasteCheck.static.prompt = ve.msg( 'editcheck-copyvio-prompt' );

mw.editcheck.PasteCheck.static.name = 'paste';

mw.editcheck.PasteCheck.static.choices = [
	{
		action: 'keep',
		label: ve.msg( 'editcheck-copyvio-action-keep' )
	},
	{
		action: 'remove',
		label: ve.msg( 'editcheck-copyvio-action-remove' )
	}
];

mw.editcheck.PasteCheck.static.takesFocus = true;

mw.editcheck.PasteCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const pastesById = {};
	surfaceModel.documentModel.documentNode.getAnnotationRanges().forEach( ( annRange ) => {
		const annotation = annRange.annotation;
		if ( annotation instanceof ve.dm.ImportedDataAnnotation && !annotation.getAttribute( 'source' ) ) {
			const id = annotation.getAttribute( 'eventId' );
			if ( this.isDismissedId( id ) ) {
				return;
			}
			if ( annRange.range.getLength() < this.config.minimumCharacters ) {
				return;
			}
			pastesById[ id ] = pastesById[ id ] || [];
			pastesById[ id ].push( annRange.range );
		}
	} );
	return Object.keys( pastesById ).map( ( id ) => {
		const fragments = pastesById[ id ].map( ( range ) => surfaceModel.getLinearFragment( range ) );
		return new mw.editcheck.EditCheckAction( {
			fragments: fragments,
			// eslint-disable-next-line no-jquery/no-append-html
			message: $( '<span>' ).append( ve.htmlMsg( 'editcheck-copyvio-description', ve.msg( 'editcheck-copyvio-descriptionlink' ) ) )
				.find( 'a' ).attr( 'target', '_blank' ).on( 'click', () => {
					ve.track( 'activity.editCheck-' + this.getName(), { action: 'click-learn-more' } );
				} ).end(),
			id: id,
			check: this
		} );
	} );
};

mw.editcheck.PasteCheck.prototype.act = function ( choice, action, surface ) {
	switch ( choice ) {
		case 'keep':
			return action.widget.showFeedback( {
				description: ve.msg( 'editcheck-copyvio-keep-description' ),
				choices: [ 'wrote', 'permission', 'other' ].map(
					( key ) => ( {
						data: key,
						// Messages that can be used here:
						// * editcheck-copyvio-keep-wrote
						// * editcheck-copyvio-keep-permission
						// * editcheck-copyvio-keep-other
						label: ve.msg( 'editcheck-copyvio-keep-' + key )
					} ) )
			} ).then( ( reason ) => {
				this.dismiss( action );
				mw.notify( ve.msg( 'editcheck-copyvio-keep-notify' ), { type: 'success' } );
				return ve.createDeferred().resolve( { action: choice, reason: reason } ).promise();
			} );
		case 'remove':
			action.fragments.forEach( ( fragment ) => {
				fragment.removeContent();
			} );
			// Auto-scrolling causes selection and focus changes...
			setTimeout( () => {
				action.fragments[ action.fragments.length - 1 ].select();
				surface.getView().focus();
			}, 500 );

			mw.notify( ve.msg( 'editcheck-copyvio-remove-notify' ), { type: 'success' } );
			break;
	}
};

mw.editcheck.editCheckFactory.register( mw.editcheck.PasteCheck );
