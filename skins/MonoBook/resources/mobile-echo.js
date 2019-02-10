( function () {
	$( function () {
		var mobileCutoffWidth = 550,
			notificationIcons = $( '#pt-notifications-alert, #pt-notifications-notice' ),
			echoHacked = false,
			echoHackActive = false,
			notifications = $( '#pt-notifications-alert a' ).data( 'counter-num' ) + $( '#pt-notifications-notice a' ).data( 'counter-num' ),
			notificationsString;

		// Move echo badges in/out of p-personal
		function monoBookMobileMoveEchoIcons() {
			if ( notificationIcons.length ) {
				if ( !echoHackActive && $( window ).width() <= mobileCutoffWidth ) {
					$( '#echo-hack-badges' ).append( notificationIcons );

					echoHackActive = true;
				} else if ( echoHackActive && $( window ).width() > mobileCutoffWidth ) {
					$( notificationIcons ).insertBefore( '#pt-mytalk' );

					echoHackActive = false;
				}
			}
		}

		function monoBookMobileEchoHack() {
			if ( notificationIcons.length ) {
				if ( !echoHacked && $( window ).width() <= mobileCutoffWidth ) {
					if ( notifications ) {
						notificationsString = mw.msg( 'monobook-notifications-link', notifications );
					} else {
						notificationsString = mw.msg( 'monobook-notifications-link-none' );
					}

					// add inline p-personal echo link
					mw.util.addPortletLink(
						'p-personal',
						mw.util.getUrl( 'Special:Notifications' ),
						notificationsString,
						'pt-notifications',
						$( '#pt-notifications-notice' ).attr( 'tooltip' ),
						null,
						'#pt-preferences'
					);

					$( '#p-personal-toggle' ).append( $( '<ul>' ).attr( 'id', 'echo-hack-badges' ) );

					echoHacked = true;
				}

				monoBookMobileMoveEchoIcons();
			}
		}

		$( window ).resize( monoBookMobileEchoHack );
		monoBookMobileEchoHack();
	} );
}() );
