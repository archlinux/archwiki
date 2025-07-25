mw.editcheck.TextMatchEditCheck = function MWTextMatchEditCheck( /* config */ ) {
	// Parent constructor
	mw.editcheck.TextMatchEditCheck.super.apply( this, arguments );

	this.replacers = [
		...this.constructor.static.replacers,
		...( this.config.replacers || [] )
	];
};

OO.inheritClass( mw.editcheck.TextMatchEditCheck, mw.editcheck.BaseEditCheck );

mw.editcheck.TextMatchEditCheck.static.name = 'textMatch';

mw.editcheck.TextMatchEditCheck.static.choices = [
	{
		action: 'delete',
		label: ve.msg( 'visualeditor-contextitemwidget-label-remove' )
	},
	{
		action: 'dismiss',
		label: ve.msg( 'ooui-dialog-process-dismiss' )
	}
];

mw.editcheck.TextMatchEditCheck.static.replacers = [
	{
		query: 'unfortunately',
		title: 'Adverb usage',
		message: new OO.ui.HtmlSnippet( 'Use of adverbs such as "unfortunately" should usually be avoided so as to maintain an impartial tone. <a href="#">Read more</a>.' )
	}
];

mw.editcheck.TextMatchEditCheck.prototype.handleListener = function ( surfaceModel, listener ) {
	const actions = [];
	const modified = this.getModifiedContentRanges( surfaceModel.getDocument() );
	this.replacers.forEach( ( replacer ) => {
		if ( replacer.listener && replacer.listener !== listener ) {
			return;
		}
		surfaceModel.getDocument().findText( replacer.query )
			.filter( ( range ) => !this.isDismissedRange( range ) )
			.filter( ( range ) => modified.some( ( modRange ) => range.touchesRange( modRange ) ) )
			.filter( ( range ) => this.isRangeInValidSection( range, surfaceModel.documentModel ) )
			.forEach( ( range ) => {
				let fragment = surfaceModel.getLinearFragment( range );
				switch ( replacer.expand ) {
					case 'sentence':
						// TODO: implement once unicodejs support is added
						break;
					case 'paragraph':
						fragment = fragment.expandLinearSelection( 'closest', ve.dm.ContentBranchNode )
							// …but that covered the entire CBN, we only want the contents
							.adjustLinearSelection( 1, -1 );
						break;
					case 'word':
					case 'siblings':
					case 'parent':
						fragment = fragment.expandLinearSelection( replacer.expand );
						break;
				}
				actions.push(
					new mw.editcheck.EditCheckAction( {
						fragments: [ fragment ],
						title: replacer.title,
						message: replacer.message,
						check: this
					} )
				);
			} );
	} );
	return actions;
};

mw.editcheck.TextMatchEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	return this.handleListener( surfaceModel, 'onDocumentChange' );
};

// For now it doesn't make sense to run a TextMatchEditCheck in review mode
// as there isn't a way to edit the text.
mw.editcheck.TextMatchEditCheck.prototype.onBeforeSave = null;

mw.editcheck.TextMatchEditCheck.prototype.act = function ( choice, action /* , surface */ ) {
	switch ( choice ) {
		case 'dismiss':
			this.dismiss( action );
			break;
		case 'delete':
			action.fragments[ 0 ].removeContent();
			break;
	}
	return ve.createDeferred().resolve( {} );
};

mw.editcheck.editCheckFactory.register( mw.editcheck.TextMatchEditCheck );
