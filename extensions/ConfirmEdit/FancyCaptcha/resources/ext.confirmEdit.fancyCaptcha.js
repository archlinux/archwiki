( function ( $, mw ) {
	var api = new mw.Api();
	$( document ).on( 'click', '.fancycaptcha-reload', function () {
		var $this = $( this ), $captchaImage;

		$this.addClass( 'fancycaptcha-reload-loading' );

		$captchaImage = $( '.fancycaptcha-image' );

		// AJAX request to get captcha index key
		api.post( {
			action: 'fancycaptchareload',
			format: 'xml'
		}, {
			dataType: 'xml'
		} )
		.done( function ( xmldata ) {
			var imgSrc, captchaIndex;
			captchaIndex = $( xmldata ).find( 'fancycaptchareload' ).attr( 'index' );
			if ( typeof captchaIndex === 'string' ) {
				// replace index key with a new one for captcha image
				imgSrc = $captchaImage.attr( 'src' )
				.replace( /(wpCaptchaId=)\w+/, '$1' + captchaIndex );
				$captchaImage.attr( 'src', imgSrc );

				// replace index key with a new one for hidden tag
				$( '#wpCaptchaId' ).val( captchaIndex );
				$( '#wpCaptchaWord' ).val( '' ).focus();
			}
		} )
		.always( function () {
			$this.removeClass( 'fancycaptcha-reload-loading' );
		} );

		return false;
	} );
} )( jQuery, mediaWiki );
