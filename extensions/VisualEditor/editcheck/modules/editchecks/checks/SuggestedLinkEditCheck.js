/*
 * SuggestedLinkEditCheck
 *
 * Offers to add links suggested by the suggested link service.
 *
 * @class
 * @extends mw.editcheck.LinkEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.SuggestedLinkEditCheck = function () {
	mw.editcheck.SuggestedLinkEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.SuggestedLinkEditCheck, mw.editcheck.LinkEditCheck );

/* Static properties */

mw.editcheck.SuggestedLinkEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.BaseEditCheck.static.defaultConfig, {
	showAsCheck: false, // This would never make sense to enable
	showAsSuggestion: false,
	predictionThreshold: 0.6
} );

mw.editcheck.SuggestedLinkEditCheck.static.name = 'suggestedLink';
mw.editcheck.SuggestedLinkEditCheck.static.title = 'Suggested link';
mw.editcheck.SuggestedLinkEditCheck.static.description = 'Do you want to link to this article?';

mw.editcheck.SuggestedLinkEditCheck.static.choices = [
	{
		action: 'accept',
		label: OO.ui.deferMsg( 'editcheck-dialog-action-yes' ),
		icon: 'check'
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'editcheck-dialog-action-no' ),
		icon: 'close'
	}
];

mw.editcheck.SuggestedLinkEditCheck.static.linkClasses = [ ve.dm.MWInternalLinkAnnotation ];

mw.editcheck.SuggestedLinkEditCheck.static.cachedPromises = new Map();

/* Static methods */

mw.editcheck.SuggestedLinkEditCheck.static.fetchSuggestions = function ( surfaceModel ) {
	if ( !this.cachedPromises.has( surfaceModel ) ) {
		const deferred = ve.createDeferred();
		// TODO: build this URL in some more robust manner, maybe by
		// persuading the model-owner to use a present-in-mw.config identifier
		const parts = window.location.hostname.split( '.' );
		if ( ve.getProp( parts, 1 ) !== 'wikipedia' ) {
			return deferred.resolve( [] ).promise();
		}
		mw.editcheck.fetchTimeout( `https://api.wikimedia.org/service/linkrecommendation/v1/linkrecommendations/${ parts[ 1 ] }/${ parts[ 0 ] }/${ mw.config.get( 'wgRelevantPageName' ) }` )
			.then( ( response ) => response.json() )
			.then( ( results ) => {
				if ( !ve.getProp( results, 'links' ) ) {
					deferred.reject( results );
					return;
				}
				const linkData = [];
				const documentModel = surfaceModel.getDocument();
				// This could be optimized by squashing it into a single finder
				results.links.forEach( ( result ) => {
					const ranges = documentModel.findText( result.context_before + result.link_text + result.context_after, { caseSensitiveString: true } );
					const range = ranges[ result.match_index ];
					if ( !range ) {
						return;
					}
					result.title = mw.Title.newFromText( result.link_target );
					result.fragment = surfaceModel.getLinearFragment( new ve.Range( range.start + result.context_before.length, range.end - result.context_after.length ) );
					linkData.push( result );
				} );
				deferred.resolve( linkData );
				return linkData;
			}, ( reason ) => {
				deferred.reject( reason );
			} );
		this.cachedPromises.set( surfaceModel, deferred.promise() );
	}
	return this.cachedPromises.get( surfaceModel );
};

/* Methods */

mw.editcheck.SuggestedLinkEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const documentModel = surfaceModel.getDocument();
	const modified = this.getModifiedRanges( documentModel );
	return this.constructor.static.fetchSuggestions( surfaceModel ).then( ( linkData ) => linkData.map( ( link ) => {
		const range = link.fragment.getSelection().getRange();
		if (
			link.score >= this.config.predictionThreshold &&
			!range.isCollapsed() && // deleted fragment
			link.fragment.getText() === link.link_text && // modified text might no longer apply
			!this.isDismissedRange( range ) &&
			!this.getLinkFromFragment( link.fragment ) &&
			modified.some( ( modifiedRange ) => modifiedRange.touchesRange( range ) )
		) {
			// TODO: this can be replaced with a normal message that takes params; this is just helpful for link formatting in experimental:
			const msgkey = `editcheck-${ this.getName() }-${ link.match_index }-description`;
			ve.init.platform.addMessages( { [ msgkey ]: 'Do you want to link <code>$1</code> to [[$2]]?' } );
			return this.buildActionFromLinkRange( range, surfaceModel, {
				message: ve.deferJQueryMsg( msgkey, link.link_text, link.link_target )
			} );
		}
		return null;
	} ) );
};

mw.editcheck.SuggestedLinkEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'accept' ) {
		return this.constructor.static.fetchSuggestions( surface.getModel() ).then( ( linkData ) => {
			const fragment = action.fragments[ 0 ];
			const link = linkData.find( ( ln ) => ln.fragment.getSelection().equals( fragment.getSelection() ) );
			if ( !link ) {
				return;
			}
			fragment.annotateContent( 'clear', ve.dm.MWInternalLinkAnnotation.static.name );
			fragment.annotateContent( 'set', ve.dm.MWInternalLinkAnnotation.static.newFromTitle( link.title ) );
			action.select( surface, true );
		} );
	}
	// Parent method
	return mw.editcheck.SuggestedLinkEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.SuggestedLinkEditCheck );
