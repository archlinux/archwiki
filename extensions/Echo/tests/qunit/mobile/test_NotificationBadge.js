QUnit.module( 'ext.echo.mobile - NotificationBadge', function () {
	var NotificationBadge = require( 'ext.echo.mobile' ).NotificationBadge;

	QUnit.test( '.setCount()', function ( assert ) {
		var initialExpectationsMet,
			badge = new NotificationBadge( {
				hasNotifications: true,
				hasUnseenNotifications: true,
				notificationCountRaw: 5
			} );
		initialExpectationsMet = badge.$el.find( '.mw-ui-icon' ).length === 0;

		badge.setCount( 0 );
		assert.true( initialExpectationsMet, 'No icon.' );
		badge.setCount( 105 );
		assert.strictEqual( badge.options.notificationCountRaw, 100, 'Number is capped to 100.' );
	} );

	QUnit.test( '.setCount() Eastern Arabic numerals', function ( assert ) {
		var badge;

		this.sandbox.stub( mw.language, 'convertNumber' )
			.withArgs( 2 ).returns( '۲' )
			.withArgs( 5 ).returns( '۵' );
		this.sandbox.stub( mw, 'message' )
			.withArgs( 'echo-badge-count', '۵' ).returns( { text: function () { return '۵'; } } )
			.withArgs( 'echo-badge-count', '۲' ).returns( { text: function () { return '۲'; } } );

		badge = new NotificationBadge( {
			el: $( '<div><a title="n" href="/" class="notification-unseen"><div class="circle" ><span data-notification-count="2">۲</span></div></a></div>' )
		} );
		assert.strictEqual( badge.options.notificationCountRaw, 2,
			'Number is parsed from Eastern Arabic numerals' );
		assert.strictEqual( badge.options.notificationCountString, '۲',
			'Number will be rendered in Eastern Arabic numerals' );
		badge.setCount( 5 );
		assert.strictEqual( badge.options.notificationCountString, '۵',
			'Number will be rendered in Eastern Arabic numerals' );
	} );

	QUnit.test( '.render() [hasUnseenNotifications]', function ( assert ) {
		var badge = new NotificationBadge( {
			notificationCountRaw: 0,
			hasNotifications: false,
			hasUnseenNotifications: false
		} );
		assert.strictEqual( badge.$el.find( '.mw-ui-icon' ).length, 1, 'A bell icon is visible' );
	} );

	QUnit.test( '.markAsSeen()', function ( assert ) {
		var badge = new NotificationBadge( {
			notificationCountRaw: 2,
			hasNotifications: true,
			hasUnseenNotifications: true
		} );
		// Badge resets counter to zero
		badge.setCount( 0 );
		assert.strictEqual( badge.$el.find( '.mw-ui-icon' ).length, 0, 'The bell icon is not visible' );
		badge.markAsSeen();
		assert.strictEqual( badge.$el.find( '.notification-unseen' ).length, 0,
			'Unseen class disappears after markAsSeen called.' );
	} );
} );
