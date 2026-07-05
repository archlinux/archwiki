const maxCheckMetrics = 1000; // TODO this should live elsewhere

/**
 * Performance tracking handler for Edit Check.
 *
 * @class
 *
 * @constructor
 * @param {mw.editcheck.Controller} controller Edit Check controller instance
 */
mw.editcheck.EditCheckPerformance = function EditCheckPerformance( controller ) {
	this.controller = controller;
	this.timings = {
		typingLag: {
			minMs: null,
			maxMs: null,
			rawLags: []
		},
		allChecks: []
	};
	this.pendingChecks = new WeakMap();
	this.checksSeen = {};
	// Randomly select 1% of sessions in which to instrument action generation
	this.trackChecks = Math.random() < 0.01;
	this.checkMetricsCount = 0;

	this.controller.target.connect( this, {
		surfaceReady: 'onSurfaceReady'
	} );

	mw.editcheck.editCheckFactory.connect( this, {
		beforeActionsGenerated: 'onBeforeActionsGenerated',
		afterActionsGeneratedSync: 'onAfterActionsGeneratedSync',
		afterActionsGeneratedAsync: 'onAfterActionsGeneratedAsync'
	} );
};

/**
 * Send metrics through statsv service
 *
 * @param {string} topic Event name
 * @param {number} duration in ms
 * @param {Object} labels Optional labels to be sent with metric
 */
mw.editcheck.EditCheckPerformance.prototype.trackTiming = function ( topic, duration, labels ) {
	topic = 'stats.mediawiki_' + topic + '_seconds';
	if ( labels ) {
		mw.track( topic, duration, labels );
	} else {
		mw.track( topic, duration );
	}
};

mw.editcheck.EditCheckPerformance.prototype.onSurfaceReady = function () {
	const surface = this.controller.target.getSurface();
	this.timings.sessionStart = ve.now();
	surface.getView().eventSequencer.on( {
		input: this.onInput.bind( this )
	} );
};

/**
 * Handler for when a check's listener is invoked
 *
 * Tracks the start time and if this is this the first time we've observed this check/listener pair.
 *
 * @param {string} checkName
 * @param {string} listener
 * @param {boolean} includeSuggestions
 * @param {Object} run token to use to correlate lifecycle events
 * (in case the same check listener is invoked concurrently)
 */
mw.editcheck.EditCheckPerformance.prototype.onBeforeActionsGenerated = function ( checkName, listener, includeSuggestions, run ) {
	const key = listener + ':' + checkName + ':' + ( includeSuggestions ? '1' : '0' );
	const firstRun = !this.checksSeen[ key ];
	if ( !this.pendingChecks.has( run ) ) {
		this.pendingChecks.set( run, new Map() );
	}
	this.pendingChecks.get( run ).set( checkName, { start: ve.now(), firstRun, listener } );
	this.checksSeen[ key ] = true;
};

/**
 * Listener for when a check listener's sync work completes
 *
 * @param {string} checkName
 * @param {Object} run token to use to correlate lifecycle events
 */
mw.editcheck.EditCheckPerformance.prototype.onAfterActionsGeneratedSync = function ( checkName, run ) {
	const bycheck = this.pendingChecks.get( run );
	if ( bycheck ) {
		const entry = this.pendingChecks.get( run ).get( checkName );
		if ( entry ) {
			entry.syncMs = ve.now() - entry.start;
		}
	}
};

/**
 * Listener for when a check listener's async work completes
 * Records total duration for the check's async and sync action generations
 *
 * @param {string} checkName
 * @param {Object} run token to use to correlate lifecycle events
 */
mw.editcheck.EditCheckPerformance.prototype.onAfterActionsGeneratedAsync = function ( checkName, run ) {
	const bycheck = this.pendingChecks.get( run );
	if ( bycheck ) {
		const entry = this.pendingChecks.get( run ).get( checkName );
		if ( entry ) {
			this.recordActionStats( ve.now() - entry.start, entry.syncMs, checkName, entry.start, entry.firstRun, entry.listener );
			this.pendingChecks.get( run ).delete( checkName );
		}
	}
};

/**
 * Store metrics for one instance of actions being generated for a check
 * and send them off (up to a limit) if this session has been selected for sampling
 *
 * @param {number} asyncMs
 * @param {number} syncMs
 * @param {string} checkName
 * @param {number} start
 * @param {boolean} firstRun
 * @param {string} listener
 */
mw.editcheck.EditCheckPerformance.prototype.recordActionStats = function ( asyncMs, syncMs, checkName, start, firstRun, listener ) {
	if ( this.timings.allChecks.length < mw.editcheck.sessionPerfConfig.checksMax ) {
		const snapshot = {
			start: start - this.timings.sessionStart,
			checkName,
			syncMs,
			asyncMs,
			firstRun,
			listener
		};
		this.timings.allChecks.push( snapshot );

		if ( this.trackChecks && this.checkMetricsCount < maxCheckMetrics ) {
			const labels = {
				kind: checkName,
				platform: ( OO.ui.isMobile() ? 'mobile' : 'desktop' ),
				firstRun: firstRun ? '1' : '0',
				listener
			};
			this.trackTiming( 'editcheck_actiongeneration_async', asyncMs, labels );
			this.trackTiming( 'editcheck_actiongeneration_sync', syncMs, labels );
			this.checkMetricsCount += 2;
		}
	}
};

/**
 * Collect a typing lag sample (time from now until next paint)
 */
mw.editcheck.EditCheckPerformance.prototype.onInput = function () {
	const maxSamples = mw.editcheck.sessionPerfConfig.typingMaxSamples || 5000;
	const t0 = ve.now();
	requestAnimationFrame( () => {
		const store = this.timings.typingLag;
		if ( store.rawLags.length >= maxSamples ) {
			return;
		}

		const lag = ve.now() - t0;
		store.rawLags.push( lag );
		store.minMs = store.minMs === null ? lag : Math.min( store.minMs, lag );
		store.maxMs = store.maxMs === null ? lag : Math.max( store.maxMs, lag );
	} );
};

/**
 * Compute aggregate typing lag stats from the raw samples and send them off as metrics
 */
mw.editcheck.EditCheckPerformance.prototype.recordTypingLagSummary = function () {
	// get percentile from sorted array
	const percentile = ( arr, p ) => {
		if ( !arr.length ) {
			return 0;
		}
		const index = Math.min(
			arr.length - 1,
			Math.max( 0, Math.ceil( ( p / 100 ) * arr.length ) - 1 )
		);
		return arr[ index ];
	};

	const store = this.timings.typingLag;
	if ( !store.rawLags.length ) {
		return;
	}
	const sorted = store.rawLags.slice().sort( ( a, b ) => a - b );

	const label = { platform: ( OO.ui.isMobile() ? 'mobile' : 'desktop' ) };
	const count = store.rawLags.length;
	const avg = count ? store.rawLags.reduce( ( sum, x ) => sum + x, 0 ) / count : null;

	this.trackTiming( 'editcheck_typinglag_avg', avg, label );
	this.trackTiming( 'editcheck_typinglag_p95', percentile( sorted, 95 ), label );
	this.trackTiming( 'editcheck_typinglag_p50', percentile( sorted, 50 ), label );
	this.trackTiming( 'editcheck_typinglag_max', store.maxMs, label );
};
