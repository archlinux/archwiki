'use strict';

const { nextTick } = require( 'vue' );
const useWatchList = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/composables/useWatchList.js' );

// Store stubs for use in arrow functions
let watchStub, unwatchStub, notifyStub;

QUnit.module( 'ext.checkUser.userInfoCard.useWatchList', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.sandbox.stub( mw, 'msg' ).callsFake( ( key, ...args ) => {
			let returnValue = '(' + key;
			if ( args.length !== 0 ) {
				returnValue += ': ' + args.join( ', ' );
			}
			return returnValue + ')';
		} );
		this.sandbox.stub( mw, 'message' ).callsFake( ( key, ...args ) => {
			let returnValue = '(' + key;
			if ( args.length !== 0 ) {
				returnValue += ': ' + args.join( ', ' );
			}
			return returnValue + ')';
		} );

		// Assign stubs to the module-level variables
		notifyStub = this.sandbox.stub( mw, 'notify' );
		watchStub = this.sandbox.stub().resolves();
		unwatchStub = this.sandbox.stub().resolves();

		// Store references in this context for backward compatibility
		this.notifyStub = notifyStub;
		this.watchStub = watchStub;
		this.unwatchStub = unwatchStub;

		this.sandbox.stub( mw, 'Api' ).returns( {
			watch: watchStub,
			unwatch: unwatchStub
		} );
	}
} ) );

QUnit.test( 'initializes with correct state', ( assert ) => {
	const watchList = useWatchList( 'TestUser' );
	assert.strictEqual(
		typeof watchList.toggleWatchList,
		'function',
		'Returns toggleWatchList function'
	);
	assert.notStrictEqual(
		watchList.watchListLabel,
		undefined,
		'Returns watchListLabel computed property'
	);
} );

QUnit.test( 'toggleWatchList unwatches when currently watched', ( assert ) => {
	const done = assert.async();

	const watchList = useWatchList( 'TestUser', 'male', true );
	assert.strictEqual(
		watchList.watchListLabel.value,
		'(checkuser-userinfocard-menu-remove-from-watchlist: male)'
	);

	watchList.toggleWatchList();

	assert.strictEqual( unwatchStub.callCount, 1, 'Calls unwatch method' );
	assert.strictEqual(
		unwatchStub.firstCall.args[ 0 ],
		'User:TestUser',
		'Unwatches correct page'
	);

	// Wait for the promise to resolve
	nextTick( () => {
		assert.strictEqual(
			watchList.watchListLabel.value,
			'(checkuser-userinfocard-menu-add-to-watchlist: male)'
		);
		assert.strictEqual( notifyStub.callCount, 1, 'Shows notification' );
		done();
	} );
} );

QUnit.test( 'toggleWatchList watches when currently unwatched', ( assert ) => {
	const done = assert.async();

	const watchList = useWatchList( 'TestUser', 'female', false );
	assert.strictEqual(
		watchList.watchListLabel.value,
		'(checkuser-userinfocard-menu-add-to-watchlist: female)'
	);

	watchList.toggleWatchList();

	assert.strictEqual( watchStub.callCount, 1, 'Calls watch method' );
	assert.strictEqual( watchStub.firstCall.args[ 0 ], 'User:TestUser', 'Watches correct page' );

	nextTick( () => {
		assert.strictEqual(
			watchList.watchListLabel.value,
			'(checkuser-userinfocard-menu-remove-from-watchlist: female)'
		);
		assert.strictEqual( notifyStub.callCount, 1, 'Shows notification' );
		done();
	} );
} );

QUnit.test( 'toggleWatchList handles unwatch error', ( assert ) => {
	const done = assert.async();

	// Make the unwatch call fail
	unwatchStub.rejects( new Error( 'Failed to unwatch' ) );

	const watchList = useWatchList( 'TestUser', 'unknown', true );
	watchList.toggleWatchList();

	nextTick( () => {
		// A second nextTicket is required due to the forced reject
		// Alternately, we could use setTimeout (0 interval) instead of double-nextTick
		nextTick( () => {
			assert.strictEqual( notifyStub.callCount, 1, 'Shows error notification' );
			assert.deepEqual(
				notifyStub.firstCall.args[ 1 ],
				{ type: 'error' },
				'Notification has error type'
			);
			done();
		} );
	} );
} );

QUnit.test( 'toggleWatchList handles watch error', ( assert ) => {
	const done = assert.async();

	// Make the watch call fail
	watchStub.rejects( new Error( 'Failed to watch' ) );

	const watchList = useWatchList( 'TestUser', 'unknown', false );
	watchList.toggleWatchList();

	nextTick( () => {
		nextTick( () => {
			assert.strictEqual( notifyStub.callCount, 1, 'Shows error notification' );
			assert.deepEqual(
				notifyStub.firstCall.args[ 1 ],
				{ type: 'error' },
				'Notification has error type'
			);
			done();
		} );
	} );
} );

QUnit.test( 'watchListLabel returns correct label based on watch state', ( assert ) => {
	// Test with unwatched state
	let watchList = useWatchList( 'TestUser', 'unknown', false );
	assert.strictEqual(
		watchList.watchListLabel.value,
		'(checkuser-userinfocard-menu-add-to-watchlist: unknown)',
		'Returns correct key when unwatched'
	);

	// Test with watched state
	watchList = useWatchList( 'TestUser', 'unknown', true );
	assert.strictEqual(
		watchList.watchListLabel.value,
		'(checkuser-userinfocard-menu-remove-from-watchlist: unknown)',
		'Returns correct key when watched'
	);
} );
