/**
 * Edit check to detect references that can be converted to use a citation template
 *
 * @class
 * @extends mw.editcheck.BaseEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {string} [config.strict='url-only'] How strict to be in determining whether a reference is convertible:
 * - 'url-only': the reference must entirely consist of only a single URL
 * - 'covered': the reference must be entirely covered by a single link annotation
 * - 'any': anything that the context item triggers for counts
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.ConvertReferenceEditCheck = function MWConvertReferenceEditCheck() {
	// Parent constructor
	mw.editcheck.ConvertReferenceEditCheck.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.ConvertReferenceEditCheck, mw.editcheck.BaseEditCheck );

/* Static properties */

mw.editcheck.ConvertReferenceEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.ConvertReferenceEditCheck.super.static.defaultConfig, {
	showAsCheck: false,
	strict: 'url-only'
} );

mw.editcheck.ConvertReferenceEditCheck.static.title = OO.ui.deferMsg( 'editcheck-convertreference-title' );

mw.editcheck.ConvertReferenceEditCheck.static.description = ve.deferJQueryMsg( 'editcheck-convertreference-description' );

mw.editcheck.ConvertReferenceEditCheck.static.name = 'convertReference';

mw.editcheck.ConvertReferenceEditCheck.static.choices = [
	{
		action: 'convert',
		label: OO.ui.deferMsg( 'editcheck-action-convert-reference' )
	},
	{
		action: 'dismiss',
		label: OO.ui.deferMsg( 'ooui-dialog-process-dismiss' )
	}
];

/* Methods */

mw.editcheck.ConvertReferenceEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const seenIndexes = {};
	const documentModel = surfaceModel.getDocument();
	return this.getAddedNodes( documentModel, 'mwReference' ).map( ( node ) => {
		const index = node.getIndexNumber();
		if ( seenIndexes[ index ] ) {
			return null;
		}
		seenIndexes[ index ] = true;
		const referenceNode = node.getInternalItem();
		const href = ve.ui.CitoidReferenceContextItem.static.getConvertibleHref( referenceNode );
		if ( href ) {
			switch ( this.config.strict ) {
				case 'url-only': {
					// Restrict this further than the context item does: require that the contents
					// of the reference be a single URL.
					// i.e. <ref>https://example.com</ref>
					const referenceRange = referenceNode.getRange();
					const protocols = ve.init.platform.getUnanchoredExternalLinkUrlProtocolsRegExp().source;
					const urlPattern = new RegExp( protocols + '\\S+', 'ig' );
					const text = documentModel.data.getText( false, referenceRange );
					if ( urlPattern.test( text ) ) {
						return node.getOuterRange();
					}
					// Also catch <ref>[https:///example.com]</ref>
					// This turns into `{ref}{paragraph}{mwnumberedexternallink}{/}{/}{/}`
					if ( referenceRange.getLength() === 4 ) {
						const contentNode = ve.getProp( referenceNode, 'children', 0, 'children', 0 );
						if ( contentNode instanceof ve.dm.MWNumberedExternalLinkNode ) {
							return node.getOuterRange();
						}
					}
					break;
				}
				case 'covered': {
					// Restrict this further than the context item does: require that the link annotation
					// completely covers the contents of the referenceNode. This should mean that either
					// it's a linked URL, or it's just the title of the external page that has been linked,
					// and so we won't potentially be throwing away data like pages, publication names, etc.
					const annotations = referenceNode.getAnnotationRanges();
					if ( annotations.length === 1 && ( annotations[ 0 ].range.getLength() + 2 === referenceNode.getRange().getLength() ) ) {
						return node.getOuterRange();
					}
					break;
				}
				default:
					// The context item was enough
					return node.getOuterRange();
			}
		}
		return null;
	} ).filter( ( range ) => range && !this.isDismissedRange( range ) ).map( ( range ) => (
		new mw.editcheck.EditCheckAction( {
			fragments: [ surfaceModel.getLinearFragment( range ) ],
			check: this
		} )
	) );
};

mw.editcheck.ConvertReferenceEditCheck.prototype.act = function ( choice, action, surface ) {
	if ( choice === 'convert' ) {
		action.fragments[ 0 ].select();
		const node = action.fragments[ 0 ].getSelectedNode();
		const href = ve.ui.CitoidReferenceContextItem.static.getConvertibleHref( node.getInternalItem() );
		const citoidAction = ve.ui.actionFactory.create( 'citoid', surface );
		citoidAction.open( { replace: true, lookup: href } );
		return;
	}

	// Parent method
	return mw.editcheck.ConvertReferenceEditCheck.super.prototype.act.apply( this, arguments );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.ConvertReferenceEditCheck );
