mw.editcheck.BaseEditCheck = function MWBaseEditCheck( config ) {
	this.config = config;
};

OO.initClass( mw.editcheck.BaseEditCheck );

mw.editcheck.BaseEditCheck.static.onlyCoveredNodes = false;

mw.editcheck.BaseEditCheck.static.choices = [
	{
		action: 'accept',
		label: ve.msg( 'editcheck-dialog-action-yes' ),
		icon: 'check'
	},
	{
		action: 'reject',
		label: ve.msg( 'editcheck-dialog-action-no' ),
		icon: 'close'
	}
];

mw.editcheck.BaseEditCheck.static.description = ve.msg( 'editcheck-dialog-addref-description' );

/**
 * @param {ve.dm.Surface} surfaceModel
 * @return {mw.editcheck.EditCheckAction[]}
 */
mw.editcheck.BaseEditCheck.prototype.onBeforeSave = null;

/**
 * @param {ve.dm.Surface} surfaceModel
 * @return {mw.editcheck.EditCheckAction[]}
 */
mw.editcheck.BaseEditCheck.prototype.onDocumentChange = null;

/**
 * @param {string} choice `action` key from static.choices
 * @param {mw.editcheck.EditCheckAction} action
 * @param {ve.ui.EditCheckContextItem} contextItem
 */
mw.editcheck.BaseEditCheck.prototype.act = null;

/**
 * @param {mw.editcheck.EditCheckAction} action
 * @return {Object[]}
 */
mw.editcheck.BaseEditCheck.prototype.getChoices = function () {
	return this.constructor.static.choices;
};

/**
 * @param {mw.editcheck.EditCheckAction} action
 * @return {string}
 */
mw.editcheck.BaseEditCheck.prototype.getDescription = function () {
	return this.constructor.static.description;
};

/**
 * Find out whether the check should be applied
 *
 * This is a general check for its applicability to the viewer / page, rather
 * than a specific check based on the current edit. It's used to filter out
 * checks before any maybe-expensive content analysis happens.
 *
 * @return {boolean} Whether the check should be shown
 */
mw.editcheck.BaseEditCheck.prototype.canBeShown = function () {
	// all checks are only in the main namespace for now
	if ( mw.config.get( 'wgNamespaceNumber' ) !== mw.config.get( 'wgNamespaceIds' )[ '' ] ) {
		return false;
	}
	// some checks are configured to only be for logged in / out users
	if ( mw.editcheck.ecenable ) {
		return true;
	}
	// account status:
	// loggedin, loggedout, or any-other-value meaning 'both'
	// we'll count temporary users as "logged out" by using isNamed here
	if ( this.config.account === 'loggedout' && mw.user.isNamed() ) {
		return false;
	}
	if ( this.config.account === 'loggedin' && !mw.user.isNamed() ) {
		return false;
	}
	// some checks are only shown for newer users
	if ( this.config.maximumEditcount && mw.config.get( 'wgUserEditCount', 0 ) > this.config.maximumEditcount ) {
		return false;
	}
	return true;
};

/**
 * Get content ranges where at least the minimum about of text has been changed
 *
 * @param {ve.dm.Document} documentModel
 * @return {ve.Range[]}
 */
mw.editcheck.BaseEditCheck.prototype.getModifiedContentRanges = function ( documentModel ) {
	return mw.editcheck.getModifiedRanges( documentModel, this.constructor.static.onlyCoveredNodes )
		.filter(
			( range ) => range.getLength() >= this.config.minimumCharacters &&
				this.isRangeInValidSection( range, documentModel )
		);
};

/**
 * Check if a modified range is a section we don't ignore (config.ignoreSections)
 *
 * @param {ve.Range} range
 * @param {ve.dm.Document} documentModel
 * @return {boolean}
 */
mw.editcheck.BaseEditCheck.prototype.isRangeInValidSection = function ( range, documentModel ) {
	const ignoreSections = this.config.ignoreSections || [];
	if ( ignoreSections.length === 0 && !this.config.ignoreLeadSection ) {
		// Nothing is forbidden, so everything is permitted
		return true;
	}
	const isHeading = ( nodeType ) => nodeType === 'mwHeading';
	// Note: we set a limit of 1 here because otherwise this will turn around
	// to keep looking when it hits the document boundary:
	const heading = documentModel.getNearestNodeMatching( isHeading, range.start, -1, 1 );
	if ( !heading ) {
		// There's no preceding heading, so work out if we count as being in a
		// lead section. It's only a lead section if there's more headings
		// later in the document, otherwise it's just a stub article.
		return !(
			this.config.ignoreLeadSection &&
			!!documentModel.getNearestNodeMatching( isHeading, range.start, 1 )
		);
	}
	if ( ignoreSections.length === 0 ) {
		// There's nothing left to deny
		return true;
	}
	const compare = new Intl.Collator( documentModel.getLang(), { sensitivity: 'accent' } ).compare;
	const headingText = documentModel.data.getText( false, heading.getRange() );
	// If the heading text matches any of ignoreSections, return false.
	return !ignoreSections.some( ( section ) => compare( headingText, section ) === 0 );
};
