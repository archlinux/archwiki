/**
 * Edit check to detect issues with the tone of an edit, e.g. promotional or non-neutral language.
 *
 * @class
 * @extends mw.editcheck.BaseEditCheck
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller
 * @param {Object} [config]
 * @param {number} [config.predictionThreshold=0.8] Threshold for the prediction value, between 0.5 and 1
 * @param {boolean} [includeSuggestions=false]
 */
mw.editcheck.ToneCheck = function MWToneCheck() {
	// Parent constructor
	mw.editcheck.ToneCheck.super.apply( this, arguments );

	// Bind with no arguments so it can be used as an event listener
	this.showSuccessHandler = this.showSuccess.bind( this );
};

/* Inheritance */

OO.inheritClass( mw.editcheck.ToneCheck, mw.editcheck.AsyncTextCheck );

/* Static properties */

mw.editcheck.ToneCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.ToneCheck.super.static.defaultConfig, {
	showAsCheck: false,
	predictionThreshold: 0.8,
	ignoreQuotedContent: true
} );

mw.editcheck.ToneCheck.static.name = 'tone';

mw.editcheck.ToneCheck.static.allowedContentLanguages = [ 'en', 'es', 'fr', 'ja', 'pt' ];

mw.editcheck.ToneCheck.static.title = OO.ui.deferMsg( 'editcheck-tone-title' );

mw.editcheck.ToneCheck.static.description = ve.deferJQueryMsg( 'editcheck-tone-description' );

mw.editcheck.ToneCheck.static.footer = ve.deferJQueryMsg( 'editcheck-tone-footer' );

mw.editcheck.ToneCheck.static.success = OO.ui.deferMsg( 'editcheck-tone-thank' );

mw.editcheck.ToneCheck.static.choices = [
	{
		action: 'edit',
		label: ve.msg( 'editcheck-dialog-action-revise' ),
		modes: [ '' ]
	},
	{
		action: 'recheck',
		label: ve.msg( 'editcheck-dialog-action-recheck' ),
		flags: [ 'primary', 'progressive' ],
		icon: 'check',
		modes: [ 'revising' ]
	},
	{
		action: 'dismiss',
		label: ve.msg( 'editcheck-dialog-action-decline' ),
		modes: [ '', 'revising' ]
	}
];

mw.editcheck.ToneCheck.static.queue = [];

/* Static methods */

/**
 * Perform an asynchronous check
 *
 * @param {string} text The plaintext to check
 * @return {Promise|any}
 */
mw.editcheck.ToneCheck.static.checkAsync = function ( text ) {
	/* Don't send requests for short strings */
	if ( text.trim().length <= 0 ) {
		return false;
	}

	const deferred = ve.createDeferred();

	// we don't need to think about deduplication because the memoization has
	// handled that for us
	this.queue.push( { text, deferred } );

	this.doCheckRequestsDebounced();

	return deferred.promise();
};

/**
 * Make the actual API request, batching together all pending checks
 */
mw.editcheck.ToneCheck.static.doCheckRequests = function () {
	const title = mw.Title.newFromText( mw.config.get( 'wgRelevantPageName' ) );
	const titleText = title ? title.getMainText() : '';

	// API will only accept at most 100 instances
	const batchSize = 100;
	while ( this.queue.length ) {
		const subqueue = this.queue.splice( 0, batchSize );
		mw.editcheck.fetchTimeout( 'https://api.wikimedia.org/service/lw/inference/v1/models/edit-check:predict', {
			method: 'POST',
			headers: {
				'Content-Type': 'text/html'
			},
			body: JSON.stringify( { instances: subqueue.map( ( item ) => (
				/* eslint-disable camelcase */
				{
					modified_text: item.text,
					page_title: titleText,
					original_text: '',
					check_type: 'tone',
					lang: mw.config.get( 'wgContentLanguage' )
				}
				/* eslint-enable camelcase */
			) ) } )
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data && data.predictions && subqueue.length === data.predictions.length ) {
					subqueue.forEach( ( item, index ) => {
						const prediction = data.predictions[ index ];
						item.deferred.resolve( prediction );
					} );
				} else {
					throw new Error( 'Invalid response from edit-check:predict' );
				}
			} )
			.catch( ( error ) => {
				subqueue.forEach( ( item ) => {
					item.deferred.reject( error );
				} );
			} );
	}
	this.queue = [];
};

mw.editcheck.ToneCheck.static.doCheckRequestsDebounced = ve.debounce( mw.editcheck.ToneCheck.static.doCheckRequests, 1 );

/* Methods */

/**
 * @inheritdoc
 */
mw.editcheck.ToneCheck.prototype.canBeShown = function ( ...args ) {
	if ( !this.constructor.static.allowedContentLanguages.includes( mw.config.get( 'wgContentLanguage' ) ) ) {
		return false;
	}

	return mw.editcheck.ToneCheck.super.prototype.canBeShown.call( this, ...args );
};

mw.editcheck.ToneCheck.prototype.afterMemoized = function ( data ) {
	return !!( data.prediction && data.probability >= this.config.predictionThreshold );
};

mw.editcheck.ToneCheck.prototype.newAction = function ( fragment, outcome ) {
	if ( !outcome ) {
		return null;
	}

	// TODO: variant message/labels when in back-from-presave state
	const action = new mw.editcheck.EditCheckAction( {
		fragments: [ fragment ],
		check: this
	} );

	action.on( 'stale', ( stale ) => {
		action.setMode( stale ? 'revising' : '' );
		action.gutterQuickAction = stale ? 'recheck' : null;
	} );

	return action;
};

mw.editcheck.ToneCheck.prototype.act = function ( choice, action, surface ) {
	action.off( 'discard', this.showSuccessHandler );
	// The 'interacted' tag was previously used for not showing the user
	// the tone check again in pre-save if they had already interacted with it.
	// Per T409991 this is no longer the behavior we want. Keeping the tag for future use.
	this.tag( 'interacted', action );
	if ( choice === 'dismiss' ) {
		return action.widget.showFeedback( {
			choices: [
				{
					data: 'appropriate',
					label: ve.msg( 'editcheck-tone-reject-appropriate' )
				},
				{
					data: 'uncertain',
					label: ve.msg( 'editcheck-tone-reject-uncertain' )
				},
				{
					data: 'other',
					label: ve.msg( 'editcheck-tone-reject-other' )
				}
			]
		} ).then( ( reason ) => {
			this.dismiss( action );
			this.showSuccess();
			return ve.createDeferred().resolve( { action: choice, reason } ).promise();
		} );
	} else if ( choice === 'edit' && surface ) {
		// Once revising has started the user will either make enough of an
		// edit that this action is discarded, or will `act` again and this
		// event-handler will be removed above:
		// If in pre-save mode, close the check dialog
		const closePromise = this.controller.inBeforeSave ? this.controller.closeDialog() : ve.createDeferred().resolve().promise();
		return closePromise.then( () => {
			const fragment = action.fragments[ action.fragments.length - 1 ].collapseToEnd();
			// prevent triggering branch node change listeners and thus clearing staleness immediately:
			this.controller.updateCurrentBranchNodeFromSelection( fragment.getSelection() );
			// If we transitioned from the pre-save mode to mid-edit we need
			// to switch to the new action-object. If we didn't, this will just
			// find `action` again.
			const newAction = this.controller.getActions().find( ( cAct ) => cAct.equals( action ) );
			if ( newAction ) {
				newAction.updateStale( true );
				newAction.once( 'discard', newAction.check.showSuccessHandler );
				// If we transitioned, this will result in us waiting until
				// the sidebar is open:
				this.controller.refresh( true ).then( () => {
					this.controller.ensureActionIsShown( newAction );
					// select won't have refocused the article if it didn't change:
					surface.getView().focus();
				} );
			} else {
				// This is unlikely, but if configuration means there's *not*
				// an equivalent action in the mid-edit, go ahead and focus the fragment
				fragment.select();
				// select won't have refocused the article if it didn't change:
				surface.getView().focus();
			}
		} );
	} else if ( choice === 'recheck' ) {
		const recheckDeferred = ve.createDeferred();

		const progress = new OO.ui.ProgressBarWidget( {
			progress: false,
			inline: true
		} );
		action.widget.$body.prepend( progress.$element );

		this.checkText( action.fragments[ action.fragments.length - 1 ].getText() )
			.then( ( result ) => {
				recheckDeferred.resolve( result );
			} );

		const minimumTimeDeferred = ve.createDeferred();
		setTimeout( () => {
			minimumTimeDeferred.resolve();
		}, 500 );

		setTimeout( () => {
			/* Silently fail if it takes too long */
			recheckDeferred.resolve();
		}, 3000 );

		action.tag( 'pending' );

		// Caller requires a Deferred as it then calls '.always()'
		// eslint-disable-next-line no-jquery/no-when
		return $.when( recheckDeferred, minimumTimeDeferred ).then( ( result ) => {
			action.updateStale( false );
			action.untag( 'pending' );

			progress.$element.remove();
			if ( !result ) {
				this.onSuccess( action );
			}
		} );
	}
};

mw.editcheck.ToneCheck.prototype.onSuccess = function ( action ) {
	this.showSuccess();
	this.controller.removeAction( 'onBranchNodeChange', action, false );
};

/* Registration */

mw.editcheck.editCheckFactory.register( mw.editcheck.ToneCheck );
