/**
 * Edit check to detect missing required template parameters
 *
 * @class
 * @extends mw.editcheck.BaseEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.RequiredTemplateParamEditCheck = function MWRequiredTemplateParamEditCheck() {
	// Parent constructor
	mw.editcheck.RequiredTemplateParamEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.RequiredTemplateParamEditCheck, mw.editcheck.BaseEditCheck );

/* Static properties */

mw.editcheck.RequiredTemplateParamEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.RequiredTemplateParamEditCheck.super.static.defaultConfig, {
	showAsCheck: false,
	showAsSuggestion: false
} );

mw.editcheck.RequiredTemplateParamEditCheck.static.title = 'Template has missing parameters';

mw.editcheck.RequiredTemplateParamEditCheck.static.name = 'requiredTemplateParam';

mw.editcheck.RequiredTemplateParamEditCheck.static.description = 'The template is missing some required parameters.';

// HACK: Use plain string above so Special:EditChecks can parse.
const description = mw.editcheck.RequiredTemplateParamEditCheck.static.description;
mw.editcheck.RequiredTemplateParamEditCheck.static.description = () => $( $.parseHTML( description ) );

mw.editcheck.RequiredTemplateParamEditCheck.static.choices = [
	{
		action: 'edit',
		label: 'Edit'
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

/* Methods */

mw.editcheck.RequiredTemplateParamEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const doc = surfaceModel.getDocument();
	const modified = this.getModifiedRanges( doc );

	return doc.getNodesByType( ve.dm.MWTransclusionNode, true ).map( ( node ) => {
		const range = node.getOuterRange();
		if ( !this.isDismissedRange( range ) && modified.some( ( modifiedRange ) => modifiedRange.touchesRange( range ) ) ) {
			// Check every template that makes up the transclusion for a missing require param
			const missingParams = node.getPartsList().map( ( part ) => {
				const title = part.templatePage ? mw.Title.newFromText( part.templatePage ) : null;
				if ( !title ) {
					return ve.createDeferred().resolve( false ).promise();
				}
				return ve.init.platform.templateDataCache.get( title.getPrefixedText() ).then(
					// Check if any of the params in the template spec are required
					// and missing from the transclusion.
					// spec.params can be unset for missing templates
					( spec ) => !!spec.params && Object.entries( spec.params ).some(
						( [ name, value ] ) => value.required && !(
							part.params && part.params[ name ] && part.params[ name ].wt.trim()
						)
					)
				);
			} );
			// If any of the templates are missing required params, flag the transclusion
			return Promise.all( missingParams ).then( ( hasMissingParams ) => {
				if ( hasMissingParams.some( Boolean ) ) {
					return new mw.editcheck.EditCheckAction( {
						fragments: [ surfaceModel.getLinearFragment( range ) ],
						check: this
					} );
				}
				return null;
			} );
		}
		return null;
	} );
};

mw.editcheck.RequiredTemplateParamEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'edit' ) {
		action.fragments[ 0 ].select();
		surface.executeCommand( 'transclusion' );
		return;
	}
	// Parent method
	return mw.editcheck.RequiredTemplateParamEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.RequiredTemplateParamEditCheck );
