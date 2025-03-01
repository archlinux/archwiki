mw.editcheck.TextMatchEditCheck = function MWTextMatchEditCheck( /* config */ ) {
	// Parent constructor
	mw.editcheck.TextMatchEditCheck.super.apply( this, arguments );
};

OO.inheritClass( mw.editcheck.TextMatchEditCheck, mw.editcheck.BaseEditCheck );

mw.editcheck.TextMatchEditCheck.static.name = 'textMatch';

mw.editcheck.TextMatchEditCheck.static.replacers = [
	// TODO: Load text replacement rules from community config
	{
		query: 'unfortunately',
		message: new OO.ui.HtmlSnippet( 'Use of adverbs such as "unfortunately" should usually be avoided so as to maintain an impartial tone. <a href="#">Read more</a>.' )
	}
];

mw.editcheck.TextMatchEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	const actions = [];
	this.constructor.static.replacers.forEach( ( replacer ) => {
		surfaceModel.getDocument().findText( replacer.query ).forEach( ( range ) => {
			const fragment = surfaceModel.getFragment( new ve.dm.LinearSelection( range ) );
			actions.push(
				new mw.editcheck.EditCheckAction( {
					highlight: fragment,
					selection: fragment,
					message: replacer.message,
					check: this
				} )
			);
		} );
	} );
	return actions;
};

mw.editcheck.editCheckFactory.register( mw.editcheck.TextMatchEditCheck );
