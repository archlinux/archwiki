/* eslint-disable no-jquery/no-global-selector */
$( document ).on( 'click', '.fancycaptcha-reload', function () {
	const $this = $( this ),
		$root = $this.closest( '.fancycaptcha-captcha-container' ),
		$captchaImage = $root.find( '.fancycaptcha-image' );

	$this.addClass( 'fancycaptcha-reload-loading' );

	// AJAX request to get captcha index key
	new mw.Api().post( { action: 'fancycaptchareload' } ).then( ( data ) => {
		const captchaIndex = data.fancycaptchareload.index;
		let imgSrc;
		if ( typeof captchaIndex === 'string' ) {
			// replace index key with a new one for captcha image
			imgSrc = $captchaImage.attr( 'src' ).replace( /(wpCaptchaId=)\w+/, '$1' + captchaIndex );
			$captchaImage.attr( 'src', imgSrc );

			// replace index key with a new one for hidden tag
			$( '#mw-input-captchaId' ).val( captchaIndex );
			$( '#mw-input-captchaWord' ).val( '' ).trigger( 'focus' );

			// and make it accessible for other tools, e.g. VisualEditor
			$captchaImage.data( 'captchaId', captchaIndex );
		}
	} )
		.always( () => {
			$this.removeClass( 'fancycaptcha-reload-loading' );
		} );

	return false;
} );
