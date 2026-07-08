( function () {
	const VALID_UA = 'Mozilla/5.0 (Linux; Android 5.1.1; Nexus 6 Build/LYZ28E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Mobile Safari/537.36';
	const VALID_SUPPORTED_NAMESPACES = [ 0 ];
	const spinner = () => ( {
		$el: $( '<span>' )
	} );
	const Deferred = $.Deferred;
	const downloadAction = require( 'skins.minerva.scripts/downloadPageAction.js' );
	const onClick = downloadAction.test.onClick;
	const isAvailable = downloadAction.test.isAvailable;

	class MockConfig {
		constructor( values ) {
			this.values = Object.assign( {
				wgArticleId: 300,
				wgIsMainPage: false,
				wgNamespaceNumber: 0,
				wgMinervaDownloadNamespaces: VALID_SUPPORTED_NAMESPACES
			}, values );
		}

		get( key ) {
			return this.values[ key ];
		}
	}

	QUnit.module( 'Minerva DownloadIcon', {
		beforeEach: function () {
			this.getOnClickHandler = function ( onLoadAllImages ) {
				const portletLink = document.createElement( 'li' );

				return function () {
					onClick( portletLink, spinner(), onLoadAllImages );
				};
			};
		}
	} );

	QUnit.test( '#getOnClickHandler (print after image download)', function ( assert ) {
		const
			d = Deferred(),
			handler = this.getOnClickHandler( () => d.resolve() ),
			spy = this.sandbox.stub( window, 'print' );

		handler();
		d.then( () => {
			assert.strictEqual( spy.callCount, 1, 'Print occurred.' );
		} );

		return d;
	} );

	QUnit.test( '#getOnClickHandler (print via timeout)', function ( assert ) {
		const
			d = Deferred(),
			handler = this.getOnClickHandler( () => d.resolve() ),
			spy = this.sandbox.stub( window, 'print' );

		window.setTimeout( () => {
			d.resolve();
		}, 3400 );

		handler();
		d.then( () => {
			assert.strictEqual( spy.callCount, 1,
				'Print was called once despite loadImages resolving after MAX_PRINT_TIMEOUT' );
		} );

		return d;
	} );

	QUnit.test( '#getOnClickHandler (multiple clicks)', function ( assert ) {
		const d = Deferred(),
			handler = this.getOnClickHandler( () => d.resolve() ),
			spy = this.sandbox.stub( window, 'print' );

		window.setTimeout( () => {
			d.resolve();
		}, 3400 );

		handler();
		handler();
		d.then( () => {
			assert.strictEqual( spy.callCount, 1,
				'Print was called once despite multiple clicks' );
		} );

		return d;
	} );

	QUnit.module( 'Minerva DownloadIcon isAvailable()', {
		beforeEach: function () {
			this.config = new MockConfig( {} );
			this.isAvailable = function ( ua ) {
				return isAvailable( this.config, ua );
			};
			this.notChromeIsAvailable = function ( ua ) {
				return isAvailable( this.config, ua );
			};
		}
	} );

	QUnit.test( 'isAvailable() handles properly correct namespace', function ( assert ) {
		assert.true( this.isAvailable( VALID_UA ) );
	} );

	QUnit.test( 'isAvailable() handles properly not supported namespace', ( assert ) => {
		const config = new MockConfig( {
			wgMinervaDownloadNamespaces: [ 9999 ]
		} );
		assert.false( isAvailable( config, VALID_UA ) );
	} );

	QUnit.test( 'isAvailable() handles missing pages', ( assert ) => {
		const config = new MockConfig( {
			wgArticleId: 0
		} );
		assert.false( isAvailable( config, VALID_UA ) );
	} );

	QUnit.test( 'isAvailable() handles properly main page', ( assert ) => {
		const config = new MockConfig( {
			wgArticleId: 200,
			wgIsMainPage: true
		} );
		assert.false( isAvailable( config, VALID_UA ) );
	} );

	QUnit.test( 'isAvailable() returns false for iOS', function ( assert ) {
		assert.false( this.isAvailable( 'ipad' ) );
	} );

	QUnit.test( 'isAvailable() handles properly browsers', function ( assert ) {
		// IPhone 6 Safari
		assert.false( this.isAvailable( 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_0_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13A405 Safari/601.1' ) );
		// Nokia Lumia 930 Windows Phone 8.1
		assert.false( this.isAvailable( 'Mozilla/5.0 (Mobile; Windows Phone 8.1; Android 4.0; ARM; Trident/7.0; Touch; rv:11.0; IEMobile/11.0; Microsoft; Virtual) like iPhone OS 7_0_3 Mac OS X AppleWebKit/537 (KHTML, like Gecko) Mobile Safari/537' ) );
		// Firefox @ Ubuntu
		assert.true( this.isAvailable( 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:56.0) Gecko/20100101 Firefox/56.0' ) );
	} );

	QUnit.test( 'isAvailable() handles properly non-chrome browsers', function ( assert ) {
		// IPhone 6 Safari
		assert.false( this.notChromeIsAvailable( 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_0_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13A405 Safari/601.1' ) );
		// Nokia Lumia 930 Windows Phone 8.1
		assert.false( this.notChromeIsAvailable( 'Mozilla/5.0 (Mobile; Windows Phone 8.1; Android 4.0; ARM; Trident/7.0; Touch; rv:11.0; IEMobile/11.0; Microsoft; Virtual) like iPhone OS 7_0_3 Mac OS X AppleWebKit/537 (KHTML, like Gecko) Mobile Safari/537' ) );
		// Firefox @ Ubuntu
		assert.true( this.notChromeIsAvailable( 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:56.0) Gecko/20100101 Firefox/56.0' ) );
	} );

	QUnit.test( 'isAvailable() handles properly supported browsers', function ( assert ) {
		// Samsung Galaxy S7, Android 6, Chrome 44
		assert.true( this.isAvailable( 'Mozilla/5.0 (Linux; Android 6.0.1; SAMSUNG SM-G930F Build/MMB29K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/4.0 Chrome/44.0.2403.133 Mobile Safari/537.36' ) );
		// Samsung Galaxy A5, Android 7, Samsung Browser 5.2
		assert.true( this.isAvailable( 'Mozilla/5.0 (Linux; Android 7.0; SAMSUNG SM-A510F Build/NRD90M) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/5.2 Chrome/51.0.2704.106 Mobile Safari/537.36' ) );
		// Galaxy J2, Android 5, Chrome 65
		assert.true( this.isAvailable( 'Mozilla/5.0 (Linux; Android 5.1.1; SM-J200G Build/LMY47X) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3320.0 Mobile Safari/537.36' ) );
		// Desktop, Chrome 63
		assert.true( this.isAvailable( 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.84 Safari/537.36' ) );
		// Desktop, Ubuntu, Chromium 61
		assert.true( this.isAvailable( 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/61.0.3163.100 Chrome/61.0.3163.100 Safari/537.36' ) );
		// Galaxy S8, Android 8, Samsung Browser 6.2
		assert.true( this.isAvailable( 'Mozilla/5.0 (Linux; Android 7.0; SAMSUNG SM-G950U1 Build/NRD90M) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/6.2 Chrome/56.0.2924.87 Mobile Safari/537.36' ) );
	} );

}() );
