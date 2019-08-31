// Captcha "errors" usually aren't errors. We simply don't know about them ahead of time,
// so we save once, then (if required) we get an error with a captcha back and try again after
// the user solved the captcha.
// TODO: ConfirmEdit API is horrible, there is no reliable way to know whether it is a "math",
// "question" or "fancy" type of captcha. They all expose differently named properties in the
// API for different things in the UI. At this point we only support the SimpleCaptcha and
// FancyCaptcha which we very intuitively detect by the presence of a "url" property.
mw.libs.ve.targetLoader.addPlugin( function () {

	ve.init.mw.CaptchaSaveErrorHandler = function () {};

	OO.inheritClass( ve.init.mw.CaptchaSaveErrorHandler, ve.init.mw.SaveErrorHandler );

	ve.init.mw.CaptchaSaveErrorHandler.static.name = 'confirmEditCaptchas';

	ve.init.mw.CaptchaSaveErrorHandler.static.matchFunction = function ( data ) {
		var captchaData = ve.getProp( data, 'visualeditoredit', 'edit', 'captcha' );

		return !!( captchaData && (
			captchaData.url ||
			captchaData.type === 'simple' ||
			captchaData.type === 'math' ||
			captchaData.type === 'question'
		) );
	};

	ve.init.mw.CaptchaSaveErrorHandler.static.process = function ( data, target ) {
		var $captchaImg, msg, question,
			captchaInput, $captchaDiv, $captchaParagraph,
			captchaData = ve.getProp( data, 'visualeditoredit', 'edit', 'captcha' );

		captchaInput = new OO.ui.TextInputWidget( { classes: [ 've-ui-saveDialog-captchaInput' ] } );

		function onCaptchaLoad() {
			target.saveDialog.updateSize();
			captchaInput.focus();
			captchaInput.scrollElementIntoView();
		}

		// Save when pressing 'Enter' in captcha field as it is single line.
		captchaInput.on( 'enter', function () {
			target.saveDialog.executeAction( 'save' );
		} );

		// Register extra fields
		target.saveFields.wpCaptchaId = function () {
			return captchaData.id;
		};
		target.saveFields.wpCaptchaWord = function () {
			return captchaInput.getValue();
		};

		target.saveDialog.once( 'save', function () {
			// Unregister extra fields on save attempt
			delete target.saveFields.wpCaptchaId;
			delete target.saveFields.wpCaptchaWord;
		} );

		$captchaParagraph = $( '<p>' ).append(
			$( '<strong>' ).text( mw.msg( 'captcha-label' ) ),
			document.createTextNode( mw.msg( 'colon-separator' ) )
		);
		$captchaDiv = $( '<div>' ).append( $captchaParagraph );

		if ( captchaData.url ) {
			// FancyCaptcha
			// Based on FancyCaptcha::getFormInformation() (https://git.io/v6mml) and
			// ext.confirmEdit.fancyCaptcha.js in the ConfirmEdit extension.
			mw.loader.load( 'ext.confirmEdit.fancyCaptcha' );
			$captchaDiv.addClass( 'fancycaptcha-captcha-container' );
			$captchaParagraph.append( mw.message( 'fancycaptcha-edit' ).parseDom() );
			$captchaImg = $( '<img>' )
				.attr( 'src', captchaData.url )
				.addClass( 'fancycaptcha-image' )
				.on( 'load', onCaptchaLoad );
			$captchaDiv.append(
				$captchaImg,
				' ',
				$( '<a>' ).addClass( 'fancycaptcha-reload' ).text( mw.msg( 'fancycaptcha-reload-text' ) )
			);
		} else {
			if ( captchaData.type === 'simple' || captchaData.type === 'math' ) {
				// SimpleCaptcha and MathCaptcha
				msg = 'captcha-edit';
			} else if ( captchaData.type === 'question' ) {
				// QuestyCaptcha
				msg = 'questycaptcha-edit';
			}

			if ( msg ) {
				switch ( captchaData.mime ) {
					case 'text/html':
						question = $.parseHTML( captchaData.question );
						// TODO: Search for images and wait for them to load
						setTimeout( onCaptchaLoad );
						break;
					case 'text/plain':
						question = document.createTextNode( captchaData.question );
						setTimeout( onCaptchaLoad );
						break;
				}
				$captchaParagraph.append( mw.message( msg ).parseDom(), '<br>', question );
			}
		}

		ve.targetLinksToNewWindow( $captchaParagraph[ 0 ] );
		$captchaDiv.append( captchaInput.$element );

		// ProcessDialog's error system isn't great for this yet.
		target.saveDialog.clearMessage( 'api-save-error' );
		target.saveDialog.showMessage( 'api-save-error', $captchaDiv );
		target.saveDialog.popPending();

		// Emit event for tracking. TODO: This is a bad design
		target.emit( 'saveErrorCaptcha' );
	};

	ve.init.mw.saveErrorHandlerFactory.register( ve.init.mw.CaptchaSaveErrorHandler );

} );
