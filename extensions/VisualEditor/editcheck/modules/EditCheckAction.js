mw.editcheck.EditCheckAction = function MWEditCheckAction( config ) {
	this.check = config.check;
	this.highlight = config.highlight;
	this.selection = config.selection;
	this.message = config.message;
};

OO.initClass( mw.editcheck.EditCheckAction );

mw.editcheck.EditCheckAction.prototype.getChoices = function () {
	return this.check.getChoices( this );
};

mw.editcheck.EditCheckAction.prototype.getDescription = function () {
	return this.check.getDescription( this );
};
