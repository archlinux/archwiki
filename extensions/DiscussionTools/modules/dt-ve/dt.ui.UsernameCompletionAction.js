/*!
 * VisualEditor UserInterface MWUsernameCompletionAction class.
 *
 * @copyright 2011-2019 VisualEditor Team and others; see http://ve.mit-license.org
 */

var sequence,
	controller = require( 'ext.discussionTools.init' ).controller;

/**
 * MWUsernameCompletionAction action.
 *
 * Controls autocompletion of usernames
 *
 * @class
 * @extends ve.ui.CompletionAction
 * @constructor
 * @param {ve.ui.Surface} surface Surface to act on
 */
function MWUsernameCompletionAction( surface ) {
	var action = this,
		relevantUserName = mw.config.get( 'wgRelevantUserName' );

	// Parent constructor
	MWUsernameCompletionAction.super.call( this, surface );

	// Shared API object so previous requests can be aborted
	this.api = controller.getApi();
	this.searchedPrefixes = {};
	this.localUsers = [];
	this.ipUsers = [];
	this.surface.authors.forEach( function ( user ) {
		if ( mw.util.isIPAddress( user ) ) {
			action.ipUsers.push( user );
		} else if ( user !== mw.user.getName() ) {
			action.localUsers.push( user );
		}
	} );
	if (
		relevantUserName &&
		relevantUserName !== mw.user.getName() &&
		this.localUsers.indexOf( relevantUserName ) === -1
	) {
		this.localUsers.push( relevantUserName );
		this.localUsers.sort();
	}
	this.remoteUsers = [];
}

/* Inheritance */

OO.inheritClass( MWUsernameCompletionAction, ve.ui.CompletionAction );

/* Static Properties */

MWUsernameCompletionAction.static.name = 'mwUsernameCompletion';

MWUsernameCompletionAction.static.methods = OO.copy( MWUsernameCompletionAction.static.methods );
MWUsernameCompletionAction.static.methods.push( 'insertAndOpen' );

/* Methods */

MWUsernameCompletionAction.prototype.insertAndOpen = function () {
	var inserted = false,
		surfaceModel = this.surface.getModel(),
		fragment = surfaceModel.getFragment();

	// This is opening a window in a slightly weird way, so the normal logging
	// doesn't catch it. This assumes that the only way to get here is from
	// the tool. If we add other paths, we'd need to change the logging.
	ve.track(
		'activity.' + this.constructor.static.name,
		{ action: 'window-open-from-tool' }
	);

	// Run the sequence matching logic again to check
	// if we already have the sequence inserted at the
	// current offset.
	if ( fragment.getSelection().isCollapsed() ) {
		inserted = this.surface.getView().findMatchingSequences().some( function ( item ) {
			return item.sequence === sequence;
		} );
	}

	if ( !inserted ) {
		fragment.insertContent( '@' );
	}
	fragment.collapseToEnd().select();

	return this.open();
};

MWUsernameCompletionAction.prototype.getSuggestions = function ( input ) {
	var title = mw.Title.makeTitle( mw.config.get( 'wgNamespaceIds' ).user, input ),
		validatedInput = title ? input : '',
		action = this;

	this.api.abort(); // Abort all unfinished API requests
	var apiPromise;
	if ( input.length > 0 && !this.searchedPrefixes[ input ] ) {
		apiPromise = this.api.get( {
			action: 'query',
			list: 'allusers',
			auprefix: input,
			auprop: 'blockinfo',
			auwitheditsonly: 1,
			// Fetch twice as many results as we need so we can filter
			// blocked users and still probably have some suggestions left
			aulimit: this.constructor.static.defaultLimit * 2
		} ).then( function ( response ) {
			var suggestions = response.query.allusers.filter( function ( user ) {
				// API doesn't return IPs
				return action.localUsers.indexOf( user.name ) === -1 &&
					action.remoteUsers.indexOf( user.name ) === -1 &&
					// Exclude users with indefinite sitewide blocks:
					// The only place such users could reply is on their
					// own user talk page, and in that case the user
					// will be included in localUsers.
					!( user.blockexpiry === 'infinite' && !user.blockpartial );
			} ).map( function ( user ) {
				return user.name;
			} );

			action.remoteUsers.push.apply( action.remoteUsers, suggestions );
			action.remoteUsers.sort();

			action.searchedPrefixes[ input ] = true;
		} );
	} else {
		apiPromise = ve.createDeferred().resolve().promise();
	}

	return apiPromise.then( function () {
		// By concatenating on-thread authors and remote-fetched authors, both
		// sorted alphabetically, we'll get our suggestion popup sorted so all
		// on-thread matches come first.
		return action.filterSuggestionsForInput(
			action.localUsers
				// Show no remote users if no input provided
				.concat( input.length > 0 ? action.remoteUsers : [] ),
			// TODO: Consider showing IP users
			// * Change link to Special:Contributions/<ip> (localised)
			// * Let users know that mentioning an IP will not create a notification?
			// .concat( this.ipUsers )
			validatedInput
		);
	} );
};

MWUsernameCompletionAction.prototype.getHeaderLabel = function ( input, suggestions ) {
	if ( suggestions === undefined ) {
		var $query = $( '<span>' ).text( input );
		return mw.message( 'discussiontools-replywidget-mention-tool-header', $query ).parseDom();
	}
};

MWUsernameCompletionAction.prototype.insertCompletion = function ( word, range ) {
	var prefix = mw.msg( 'discussiontools-replywidget-mention-prefix' ),
		suffix = mw.msg( 'discussiontools-replywidget-mention-suffix' ),
		title = mw.Title.newFromText( word, mw.config.get( 'wgNamespaceIds' ).user );

	if ( this.surface.getMode() === 'source' ) {
		// TODO: this should be configurable per-wiki so that e.g. custom templates can be used
		word = prefix + '[[' + title.getPrefixedText() + '|' + word + ']]' + suffix;
		return MWUsernameCompletionAction.super.prototype.insertCompletion.call( this, word, range );
	}

	var fragment = this.surface.getModel().getLinearFragment( range, true );
	fragment.removeContent().insertContent( [
		{ type: 'mwPing', attributes: { user: word } },
		{ type: '/mwPing' }
	] );

	fragment.collapseToEnd();

	return fragment;
};

MWUsernameCompletionAction.prototype.shouldAbandon = function ( input ) {
	// TODO: need to consider whether pending loads from server are happening here
	return MWUsernameCompletionAction.super.prototype.shouldAbandon.apply( this, arguments ) && input.split( /\s+/ ).length > 2;
};

/* Registration */

ve.ui.actionFactory.register( MWUsernameCompletionAction );

var openCommand = new ve.ui.Command(
	'openMWUsernameCompletions', MWUsernameCompletionAction.static.name, 'open',
	{ supportedSelections: [ 'linear' ] }
);
var insertAndOpenCommand = new ve.ui.Command(
	'insertAndOpenMWUsernameCompletions', MWUsernameCompletionAction.static.name, 'insertAndOpen',
	{ supportedSelections: [ 'linear' ] }
);
sequence = new ve.ui.Sequence( 'autocompleteMWUsernames', 'openMWUsernameCompletions', '@', 0 );
ve.ui.commandRegistry.register( openCommand );
ve.ui.commandRegistry.register( insertAndOpenCommand );
ve.ui.wikitextCommandRegistry.register( openCommand );
ve.ui.wikitextCommandRegistry.register( insertAndOpenCommand );
ve.ui.sequenceRegistry.register( sequence );
ve.ui.wikitextSequenceRegistry.register( sequence );

module.exports = MWUsernameCompletionAction;
