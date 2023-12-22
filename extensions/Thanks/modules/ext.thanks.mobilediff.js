( function () {
	// To allow users to cancel a thanks in the event of an accident, the action is delayed.
	var THANKS_DELAY = 2000,
		mobile = mw.mobileFrontend.require( 'mobile.startup' ),
		IconButton = mobile.IconButton,
		msgOptions = {
			// tag ensures that only one message in workflow is shown at any time
			tag: 'thanks'
		};
	/**
	 * Attempt to execute a thank operation for a given edit
	 *
	 * @param {string} name The username of the user who made the edit
	 * @param {string} revision The revision the user created
	 * @param {string} recipientGender The gender of the user who made the edit
	 * @return {jQuery.Promise} The thank operation's status.
	 */
	function thankUser( name, revision, recipientGender ) {
		return ( new mw.Api() ).postWithToken( 'csrf', {
			action: 'thank',
			rev: revision,
			source: 'mobilediff'
		} ).then( function () {
			mw.notify( mw.msg( 'thanks-button-action-completed', name, recipientGender, mw.user ),
				msgOptions );
		}, function ( errorCode ) {
			var msg;
			switch ( errorCode ) {
				case 'invalidrevision':
					msg = mw.msg( 'thanks-error-invalidrevision' );
					break;
				case 'ratelimited':
					msg = mw.msg( 'thanks-error-ratelimited', recipientGender );
					break;
				default:
					msg = mw.msg( 'thanks-error-undefined', errorCode );
			}
			mw.notify( msg, msgOptions );
		} );
	}

	/**
	 * Disables the thank button marking the user as thanked
	 *
	 * @param {jQuery} $button used for thanking
	 * @param {string} gender The gender of the user who made the edit
	 * @return {jQuery} $button now disabled
	 */
	function disableThanks( $button, gender ) {
		return $button
			.addClass( 'thanked' )
			.prop( 'disabled', true )
			.text( mw.msg( 'thanks-button-thanked', mw.user, gender ) );
	}

	/**
	 * Create a thank button for a given edit
	 *
	 * @param {string} name The username of the user who made the edit
	 * @param {string} rev The revision the user created
	 * @param {string} gender The gender of the user who made the edit
	 * @return {jQuery|null} The HTML of the button.
	 */
	function createThankLink( name, rev, gender ) {
		var timeout,
			button = new IconButton( {
				weight: 'primary',
				action: 'progressive',
				size: 'medium',
				additionalClassNames: 'mw-mf-action-button',
				icon: 'userTalk',
				isIconOnly: false,
				glyphPrefix: 'thanks',
				label: mw.msg( 'thanks-button-thank', mw.user, gender )
			} ),
			$button = button.$el;

		// Don't make thank button for self
		if ( name === mw.config.get( 'wgUserName' ) ) {
			return null;
		}
		// See if user has already been thanked for this edit
		if ( mw.config.get( 'wgThanksAlreadySent' ) ) {
			return disableThanks( $button, gender );
		}

		function cancelThanks( $btn ) {
			// Hide the notification
			$( '.mw-notification' ).hide();
			// Clear the queued thanks!
			clearTimeout( timeout );
			timeout = null;
			$btn.prop( 'disabled', false );
		}

		function queueThanks( $btn ) {
			var $msg = $( '<div>' ).addClass( 'mw-thanks-notification' )
				.text( mw.msg( 'thanks-button-action-queued', name, gender ) )
				.append( $( '<a>' ).text( mw.msg( 'thanks-button-action-cancel' ) )
					.on( 'click', function () {
						cancelThanks( $btn );
					} )
				);
			mw.notify( $msg, msgOptions );
			timeout = setTimeout( function () {
				timeout = null;
				thankUser( name, rev, gender ).then( function () {
					disableThanks( $btn, gender );
				} );
			}, THANKS_DELAY );
		}

		return $button.on( 'click', function () {
			var $this = $( this );
			$this.prop( 'disabled', true );
			// eslint-disable-next-line no-jquery/no-class-state
			if ( !$this.hasClass( 'thanked' ) && !timeout ) {
				queueThanks( $this );
			}
		} );
	}

	/**
	 * Initialise a thank button in the given container.
	 *
	 * @param {jQuery} $user existing element with data attributes associated describing a user.
	 * @param {jQuery} $container to render button in
	 */
	function init( $user, $container ) {
		var username = $user.data( 'user-name' ),
			rev = $user.data( 'revision-id' ),
			gender = $user.data( 'user-gender' ),
			$thankBtn;

		$thankBtn = createThankLink( username, rev, gender );
		if ( $thankBtn ) {
			$thankBtn.prependTo( $container );
		}

	}

	$( function () {
		init( $( '.mw-mf-user' ), $( '#mw-mf-userinfo' ) );
	} );

	// Expose for testing purposes
	mw.thanks = $.extend( {}, mw.thanks || {}, {
		_mobileDiffInit: init
	} );
}() );
