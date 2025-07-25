mw.loader.using( 'ext.visualEditor.targetLoader' ).then( () => {
	mw.libs.ve.targetLoader.addPlugin( () => {

		ve.init.mw.NoCaptchaReCaptchaSaveErrorHandler = function () {};

		OO.inheritClass( ve.init.mw.NoCaptchaReCaptchaSaveErrorHandler, ve.init.mw.SaveErrorHandler );

		ve.init.mw.NoCaptchaReCaptchaSaveErrorHandler.static.name = 'confirmEditNoCaptchaReCaptcha';

		ve.init.mw.NoCaptchaReCaptchaSaveErrorHandler.static.getReadyPromise = function () {
			if ( !this.readyPromise ) {
				const deferred = $.Deferred();
				const config = mw.config.get( 'wgConfirmEditConfig' );
				const scriptURL = new URL( config.reCaptchaScriptURL, location.href );
				const onLoadFn = 'onRecaptchaLoadCallback' + Date.now();
				scriptURL.searchParams.set( 'onload', onLoadFn );
				scriptURL.searchParams.set( 'render', 'explicit' );

				this.readyPromise = deferred.promise();
				window[ onLoadFn ] = deferred.resolve;
				mw.loader.load( scriptURL.toString() );
			}

			return this.readyPromise;
		};

		ve.init.mw.NoCaptchaReCaptchaSaveErrorHandler.static.matchFunction = function ( data ) {
			const captchaData = ve.getProp( data, 'visualeditoredit', 'edit', 'captcha' );

			return !!( captchaData && captchaData.type === 'recaptchanocaptcha' );
		};

		ve.init.mw.NoCaptchaReCaptchaSaveErrorHandler.static.process = function ( data, target ) {
			const self = this,
				config = mw.config.get( 'wgConfirmEditConfig' ),
				siteKey = config.reCaptchaSiteKey,
				$container = $( '<div>' );

			// Register extra fields
			target.saveFields.wpCaptchaWord = function () {
				// eslint-disable-next-line no-jquery/no-global-selector
				return $( '#g-recaptcha-response' ).val();
			};

			this.getReadyPromise()
				.then( () => {
					if ( self.widgetId ) {
						window.grecaptcha.reset( self.widgetId );
					} else {
						target.saveDialog.showMessage( 'api-save-error', $container, { wrap: false } );
						self.widgetId = window.grecaptcha.render( $container[ 0 ], {
							sitekey: siteKey,
							callback: function () {
								target.saveDialog.executeAction( 'save' );
							},
							'expired-callback': function () {},
							'error-callback': function () {}
						} );

						target.saveDialog.updateSize();
					}

					target.emit( 'saveErrorCaptcha' );
				} );
		};

		ve.init.mw.saveErrorHandlerFactory.register( ve.init.mw.NoCaptchaReCaptchaSaveErrorHandler );

	} );
} );
