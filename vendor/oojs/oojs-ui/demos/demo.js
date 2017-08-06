/* eslint-disable no-console */
/**
 * @class
 * @extends {OO.ui.Element}
 *
 * @constructor
 */
window.Demo = function Demo() {
	var demo = this;

	// Parent constructor
	Demo.parent.call( this );

	// Normalization
	this.normalizeHash();

	// Properties
	this.stylesheetLinks = this.getStylesheetLinks();
	this.mode = this.getCurrentMode();
	this.$menu = $( '<div>' );
	this.pageDropdown = new OO.ui.DropdownWidget( {
		menu: {
			items: [
				new OO.ui.MenuOptionWidget( { data: 'dialogs', label: 'Dialogs' } ),
				new OO.ui.MenuOptionWidget( { data: 'icons', label: 'Icons' } ),
				new OO.ui.MenuOptionWidget( { data: 'toolbars', label: 'Toolbars' } ),
				new OO.ui.MenuOptionWidget( { data: 'widgets', label: 'Widgets' } )
			]
		},
		classes: [ 'demo-pageDropdown' ]
	} );
	this.pageMenu = this.pageDropdown.getMenu();
	this.themeSelect = new OO.ui.ButtonSelectWidget();
	Object.keys( this.constructor.static.themes ).forEach( function ( theme ) {
		demo.themeSelect.addItems( [
			new OO.ui.ButtonOptionWidget( {
				data: theme,
				label: demo.constructor.static.themes[ theme ]
			} )
		] );
	} );
	this.directionSelect = new OO.ui.ButtonSelectWidget().addItems( [
		new OO.ui.ButtonOptionWidget( { data: 'ltr', label: 'LTR' } ),
		new OO.ui.ButtonOptionWidget( { data: 'rtl', label: 'RTL' } )
	] );
	this.jsPhpSelect = new OO.ui.ButtonGroupWidget().addItems( [
		new OO.ui.ButtonWidget( { label: 'JS' } ).setActive( true ),
		new OO.ui.ButtonWidget( {
			label: 'PHP',
			href: 'demos.php' +
				'?page=widgets' +
				'&theme=' + this.mode.theme +
				'&direction=' + this.mode.direction
		} )
	] );
	this.platformSelect = new OO.ui.ButtonSelectWidget().addItems( [
		new OO.ui.ButtonOptionWidget( { data: 'desktop', label: 'Desktop' } ),
		new OO.ui.ButtonOptionWidget( { data: 'mobile', label: 'Mobile' } )
	] );

	this.documentationLink = new OO.ui.ButtonWidget( {
		label: 'Docs',
		icon: 'journal',
		href: '../js/',
		flags: [ 'progressive' ]
	} );

	// Events
	this.pageMenu.on( 'choose', OO.ui.bind( this.onModeChange, this ) );
	this.themeSelect.on( 'choose', OO.ui.bind( this.onModeChange, this ) );
	this.directionSelect.on( 'choose', OO.ui.bind( this.onModeChange, this ) );
	this.platformSelect.on( 'choose', OO.ui.bind( this.onModeChange, this ) );

	// Initialization
	this.pageMenu.selectItemByData( this.mode.page );
	this.themeSelect.selectItemByData( this.mode.theme );
	this.directionSelect.selectItemByData( this.mode.direction );
	this.platformSelect.selectItemByData( this.mode.platform );
	this.$menu
		.addClass( 'demo-menu' )
		.append(
			this.pageDropdown.$element,
			this.themeSelect.$element,
			this.directionSelect.$element,
			this.jsPhpSelect.$element,
			this.platformSelect.$element,
			this.documentationLink.$element
		);
	this.$element
		.addClass( 'demo' )
		.append( this.$menu );
	$( 'html' ).attr( 'dir', this.mode.direction );
	$( 'head' ).append( this.stylesheetLinks );
	// eslint-disable-next-line new-cap
	OO.ui.theme = new OO.ui[ this.constructor.static.themes[ this.mode.theme ] + 'Theme' ]();
	OO.ui.isMobile = function () {
		return demo.mode.platform === 'mobile';
	};
};

/* Setup */

OO.inheritClass( Demo, OO.ui.Element );

/* Static Properties */

/**
 * Available pages.
 *
 * Populated by each of the page scripts in the `pages` directory.
 *
 * @static
 * @property {Object.<string,Function>} pages List of functions that render a page, keyed by
 *   symbolic page name
 */
Demo.static.pages = {};

/**
 * Available themes.
 *
 * Map of lowercase name to proper name. Lowercase names are used for linking to the
 * correct stylesheet file. Proper names are used to find the theme class.
 *
 * @static
 * @property {Object.<string,string>}
 */
Demo.static.themes = {
	mediawiki: 'MediaWiki', // Do not change this line or you'll break `grunt add-theme`
	apex: 'Apex'
};

/**
 * Additional suffixes for which each theme defines image modules.
 *
 * @static
 * @property {Object.<string,string[]>
 */
Demo.static.additionalThemeImagesSuffixes = {
	mediawiki: [
		'-icons-movement',
		'-icons-content',
		'-icons-alerts',
		'-icons-interactions',
		'-icons-moderation',
		'-icons-editing-core',
		'-icons-editing-styling',
		'-icons-editing-list',
		'-icons-editing-advanced',
		'-icons-media',
		'-icons-location',
		'-icons-user',
		'-icons-layout',
		'-icons-accessibility',
		'-icons-wikimedia'
	],
	apex: [
		'-icons-movement',
		'-icons-content',
		'-icons-alerts',
		'-icons-interactions',
		'-icons-moderation',
		'-icons-editing-core',
		'-icons-editing-styling',
		'-icons-editing-list',
		'-icons-editing-advanced',
		'-icons-media',
		'-icons-layout'
	]
};

/**
 * Available text directions.
 *
 * List of text direction descriptions, each containing a `fileSuffix` property used for linking to
 * the correct stylesheet file.
 *
 * @static
 * @property {Object.<string,Object>}
 */
Demo.static.directions = {
	ltr: { fileSuffix: '' },
	rtl: { fileSuffix: '.rtl' }
};

/**
 * Available platforms.
 *
 * @static
 * @property {string[]}
 */
Demo.static.platforms = [ 'desktop', 'mobile' ];

/**
 * Default page.
 *
 * @static
 * @property {string}
 */
Demo.static.defaultPage = 'widgets';

/**
 * Default page.
 *
 * Set by one of the page scripts in the `pages` directory.
 *
 * @static
 * @property {string}
 */
Demo.static.defaultTheme = 'mediawiki';

/**
 * Default page.
 *
 * Set by one of the page scripts in the `pages` directory.
 *
 * @static
 * @property {string}
 */
Demo.static.defaultDirection = 'ltr';

/**
 * Default platform.
 *
 * Set by one of the page scripts in the `pages` directory.
 *
 * @static
 * @property {string}
 */
Demo.static.defaultPlatform = 'desktop';

/* Methods */

/**
 * Load the demo page. Must be called after $element is attached.
 */
Demo.prototype.initialize = function () {
	var demo = this,
		promises = this.stylesheetLinks.map( function ( el ) {
			return $( el ).data( 'load-promise' );
		} );

	// Helper function to get high resolution profiling data, where available.
	function now() {
		return ( window.performance && performance.now ) ? performance.now() :
			Date.now ? Date.now() : new Date().getTime();
	}

	$.when.apply( $, promises )
		.done( function () {
			var start, end;
			start = now();
			demo.constructor.static.pages[ demo.mode.page ]( demo );
			end = now();
			window.console.log( 'Took ' + ( end - start ) + ' ms to build demo page.' );
		} )
		.fail( function () {
			demo.$element.append( $( '<p>' ).text( 'Demo styles failed to load.' ) );
		} );
};

/**
 * Handle mode change events.
 *
 * Will load a new page.
 */
Demo.prototype.onModeChange = function () {
	var page = this.pageMenu.getSelectedItem().getData(),
		theme = this.themeSelect.getSelectedItem().getData(),
		direction = this.directionSelect.getSelectedItem().getData(),
		platform = this.platformSelect.getSelectedItem().getData();

	location.hash = '#' + [ page, theme, direction, platform ].join( '-' );
};

/**
 * Get a list of mode factors.
 *
 * Factors are a mapping between symbolic names used in the URL hash and internal information used
 * to act on those symbolic names.
 *
 * Factor lists are in URL order: page, theme, direction, platform. Page contains the symbolic
 * page name, others contain file suffixes.
 *
 * @return {Object[]} List of mode factors, keyed by symbolic name
 */
Demo.prototype.getFactors = function () {
	var key,
		factors = [ {}, {}, {}, {} ];

	for ( key in this.constructor.static.pages ) {
		factors[ 0 ][ key ] = key;
	}
	for ( key in this.constructor.static.themes ) {
		factors[ 1 ][ key ] = '-' + key;
	}
	for ( key in this.constructor.static.directions ) {
		factors[ 2 ][ key ] = this.constructor.static.directions[ key ].fileSuffix;
	}
	this.constructor.static.platforms.forEach( function ( platform ) {
		factors[ 3 ][ platform ] = '';
	} );

	return factors;
};

/**
 * Get a list of default factors.
 *
 * Factor defaults are in URL order: page, theme, direction, platform. Each contains a symbolic
 * factor name which should be used as a fallback when the URL hash is missing or invalid.
 *
 * @return {Object[]} List of default factors
 */
Demo.prototype.getDefaultFactorValues = function () {
	return [
		this.constructor.static.defaultPage,
		this.constructor.static.defaultTheme,
		this.constructor.static.defaultDirection,
		this.constructor.static.defaultPlatform
	];
};

/**
 * Parse the current URL hash into factor values.
 *
 * @return {string[]} Factor values in URL order: page, theme, direction
 */
Demo.prototype.getCurrentFactorValues = function () {
	return location.hash.slice( 1 ).split( '-' );
};

/**
 * Get the current mode.
 *
 * Generated from parsed URL hash values.
 *
 * @return {Object} List of factor values keyed by factor name
 */
Demo.prototype.getCurrentMode = function () {
	var factorValues = this.getCurrentFactorValues();

	return {
		page: factorValues[ 0 ],
		theme: factorValues[ 1 ],
		direction: factorValues[ 2 ],
		platform: factorValues[ 3 ]
	};
};

/**
 * Get link elements for the current mode.
 *
 * @return {HTMLElement[]} List of link elements
 */
Demo.prototype.getStylesheetLinks = function () {
	var i, len, links, fragments,
		factors = this.getFactors(),
		theme = this.getCurrentFactorValues()[ 1 ],
		suffixes = this.constructor.static.additionalThemeImagesSuffixes[ theme ] || [],
		urls = [];

	// Translate modes to filename fragments
	fragments = this.getCurrentFactorValues().map( function ( val, index ) {
		return factors[ index ][ val ];
	} );

	// Theme styles
	urls.push( 'dist/oojs-ui' + fragments.slice( 1 ).join( '' ) + '.css' );
	for ( i = 0, len = suffixes.length; i < len; i++ ) {
		urls.push( 'dist/oojs-ui' + fragments[ 1 ] + suffixes[ i ] + fragments[ 2 ] + '.css' );
	}

	// Demo styles
	urls.push( 'styles/demo' + fragments[ 2 ] + '.css' );

	// Add link tags
	links = urls.map( function ( url ) {
		var
			link = document.createElement( 'link' ),
			$link = $( link ),
			deferred = $.Deferred();
		$link.data( 'load-promise', deferred.promise() );
		$link.on( {
			load: deferred.resolve,
			error: deferred.reject
		} );
		link.rel = 'stylesheet';
		link.href = url;
		return link;
	} );

	return links;
};

/**
 * Normalize the URL hash.
 */
Demo.prototype.normalizeHash = function () {
	var i, len, factorValues,
		modes = [],
		factors = this.getFactors(),
		defaults = this.getDefaultFactorValues();

	factorValues = this.getCurrentFactorValues();
	for ( i = 0, len = factors.length; i < len; i++ ) {
		modes[ i ] = factors[ i ][ factorValues[ i ] ] !== undefined ? factorValues[ i ] : defaults[ i ];
	}

	// Update hash
	location.hash = modes.join( '-' );
};

/**
 * Destroy demo.
 */
Demo.prototype.destroy = function () {
	$( 'body' ).removeClass( 'oo-ui-ltr oo-ui-rtl' );
	$( this.stylesheetLinks ).remove();
	this.$element.remove();
};

/**
 * Build a console for interacting with an element.
 *
 * @param {OO.ui.Layout} item
 * @param {string} layout Variable name for layout
 * @param {string} widget Variable name for layout's field widget
 * @return {jQuery} Console interface element
 */
Demo.prototype.buildConsole = function ( item, layout, widget ) {
	var $toggle, $log, $label, $input, $submit, $console, $form,
		console = window.console;

	function exec( str ) {
		var func, ret;
		if ( str.indexOf( 'return' ) !== 0 ) {
			str = 'return ' + str;
		}
		try {
			// eslint-disable-next-line no-new-func
			func = new Function( layout, widget, 'item', str );
			ret = { value: func( item, item.fieldWidget, item.fieldWidget ) };
		} catch ( error ) {
			ret = {
				value: undefined,
				error: error
			};
		}
		return ret;
	}

	function submit() {
		var val, result, logval;

		val = $input.val();
		$input.val( '' );
		$input[ 0 ].focus();
		result = exec( val );

		logval = String( result.value );
		if ( logval === '' ) {
			logval = '""';
		}

		$log.append(
			$( '<div>' )
				.addClass( 'demo-console-log-line demo-console-log-line-input' )
				.text( val ),
			$( '<div>' )
				.addClass( 'demo-console-log-line demo-console-log-line-return' )
				.text( logval || result.value )
		);

		if ( result.error ) {
			$log.append( $( '<div>' ).addClass( 'demo-console-log-line demo-console-log-line-error' ).text( result.error ) );
		}

		if ( console && console.log ) {
			console.log( '[demo]', result.value );
			if ( result.error ) {
				if ( console.error ) {
					console.error( '[demo]', String( result.error ), result.error );
				} else {
					console.log( '[demo] Error: ', result.error );
				}
			}
		}

		// Scrol to end
		$log.prop( 'scrollTop', $log.prop( 'scrollHeight' ) );
	}

	$toggle = $( '<span>' )
		.addClass( 'demo-console-toggle' )
		.attr( 'title', 'Toggle console' )
		.on( 'click', function ( e ) {
			e.preventDefault();
			$console.toggleClass( 'demo-console-collapsed demo-console-expanded' );
			if ( $input.is( ':visible' ) ) {
				$input[ 0 ].focus();
				if ( console && console.log ) {
					window[ layout ] = item;
					window[ widget ] = item.fieldWidget;
					console.log( '[demo]', 'Globals ' + layout + ', ' + widget + ' have been set' );
					console.log( '[demo]', item );
				}
			}
		} );

	$log = $( '<div>' )
		.addClass( 'demo-console-log' );

	$label = $( '<label>' )
		.addClass( 'demo-console-label' );

	$input = $( '<input>' )
		.addClass( 'demo-console-input' )
		.prop( 'placeholder', '... (predefined: ' + layout + ', ' + widget + ')' );

	$submit = $( '<div>' )
		.addClass( 'demo-console-submit' )
		.text( '↵' )
		.on( 'click', submit );

	$form = $( '<form>' ).on( 'submit', function ( e ) {
		e.preventDefault();
		submit();
	} );

	$console = $( '<div>' )
		.addClass( 'demo-console demo-console-collapsed' )
		.append(
			$toggle,
			$log,
			$form.append(
				$label.append(
					$input
				),
				$submit
			)
		);

	return $console;
};
