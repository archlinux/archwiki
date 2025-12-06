mw.editcheck.ToneCheck = function MWToneCheck() {
	// Parent constructor
	mw.editcheck.ToneCheck.super.apply( this, arguments );

	this.showThankToast = () => {
		mw.notify( ve.msg( 'editcheck-tone-thank' ), { type: 'success' } );
	};
};

OO.inheritClass( mw.editcheck.ToneCheck, mw.editcheck.AsyncTextCheck );

/* Static properties */

mw.editcheck.ToneCheck.static.name = 'tone';

mw.editcheck.ToneCheck.static.allowedContentLanguages = [ 'en', 'es', 'fr', 'ja', 'pt' ];

mw.editcheck.ToneCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.BaseEditCheck.static.defaultConfig, {
	predictionThreshold: 0.8
} );

mw.editcheck.AsyncTextCheck.static.queue = [];

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
					subqueue.forEach( ( item ) => {
						item.deferred.reject();
					} );
				}
			} );
	}
	this.queue = [];
};

mw.editcheck.ToneCheck.static.doCheckRequestsDebounced = ve.debounce( mw.editcheck.ToneCheck.static.doCheckRequests, 1 );

/* Instance methods */

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
	return new mw.editcheck.EditCheckAction( {
		fragments: [ fragment ],
		title: ve.msg( 'editcheck-tone-title' ),
		// eslint-disable-next-line no-jquery/no-append-html
		message: $( '<span>' ).append( ve.htmlMsg( 'editcheck-tone-description', ve.msg( 'editcheck-tone-descriptionlink' ) ) )
			.find( 'a' ).attr( 'target', '_blank' ).on( 'click', () => {
				ve.track( 'activity.editCheck-' + this.getName(), { action: 'click-learn-more' } );
			} ).end(),
		// eslint-disable-next-line no-jquery/no-append-html
		footer: $( '<span>' ).append( ve.htmlMsg( 'editcheck-tone-footer', ve.msg( 'editcheck-tone-footerlink' ) ) )
			.find( 'a' ).attr( 'target', '_blank' ).on( 'click', () => {
				ve.track( 'activity.editCheck-' + this.getName(), { action: 'click-model-card' } );
			} ).end(),
		check: this,
		choices: [
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
		]
	} );
};

mw.editcheck.ToneCheck.prototype.act = function ( choice, action, surface ) {
	action.off( 'discard', this.showThankToast );
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
			this.showThankToast();
			return ve.createDeferred().resolve( { action: choice, reason: reason } ).promise();
		} );
	} else if ( choice === 'edit' && surface ) {
		action.gutterQuickAction = 'recheck';
		action.setStale( true );
		action.setMode( 'revising' );
		// Once revising has started the user will either make enough of an
		// edit that this action is discarded, or will `act` again and this
		// event-handler will be removed above:
		action.once( 'discard', this.showThankToast );
		action.once( 'stale', () => {
			// Clean up the mode after we're done; any other act or anything
			// that can trigger an update should un-stale the action.
			action.setMode( '' );
			action.gutterQuickAction = null;
		} );
		// If in pre-save mode, close the check dialog
		const closePromise = this.controller.inBeforeSave ? this.controller.closeDialog() : ve.createDeferred().resolve().promise();
		return closePromise.then( () => {
			const fragment = action.fragments[ action.fragments.length - 1 ].collapseToEnd();
			// prevent triggering branch node change listeners and thus clearing staleness immediately:
			this.controller.updateCurrentBranchNodeFromSelection( fragment.getSelection() );
			fragment.select();
			// select won't have refocused the article if it didn't change:
			surface.getView().focus();
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
			action.gutterQuickAction = null;
			action.setStale( false );
			action.untag( 'pending' );

			progress.$element.remove();
			if ( !result ) {
				this.showThankToast();
				this.controller.removeAction( 'onBranchNodeChange', action, false );
			}
		} );
	}
};

mw.editcheck.editCheckFactory.register( mw.editcheck.ToneCheck );
