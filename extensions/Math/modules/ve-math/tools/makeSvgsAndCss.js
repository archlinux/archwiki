#!/usr/bin/env node
'use strict';

const path = require( 'path' );
const fs = require( 'fs' ).promises;
const http = require( 'http' );

/**
 * Make an HTTP POST request and return the response body
 *
 * @param {Object} options Request options
 * @param {string} data Request body data
 * @return {Promise<string>} Promise resolving with the response body
 */
async function httpRequest( options, data ) {
	return new Promise( ( resolve, reject ) => {
		const req = http.request( options, ( res ) => {
			let body = '';
			res.setEncoding( 'utf8' );
			res.on( 'data', ( chunk ) => {
				body += chunk;
			} );
			res.on( 'end', () => resolve( body ) );
		} );
		req.on( 'error', reject );
		req.setTimeout( 10000, () => {
			req.destroy();
			reject( new Error( 'Request timed out' ) );
		} );
		req.write( data );
		req.end();
	} );
}

/**
 * Encode a string for use in a CSS url()
 *
 * @param {string} str String to encode
 * @return {string}
 */
function encodeURIComponentForCSS( str ) {
	return encodeURIComponent( str )
		.replace( /[!'*()]/g, ( chr ) => '%' + chr.charCodeAt( 0 ).toString( 16 ) );
}

/**
 * Make the className, replacing any non-alphanumerics with their character code
 *
 * The reverse of function would look like this, although we have no use for it yet:
 *
 *  return className.replace( /_([0-9]+)_/g, ( all, one ) => String.fromCharCode( +one ) } );
 *
 * @param {string} tex TeX input
 * @return {string} Class name
 */
function texToClass( tex ) {
	return tex.replace( /[^\w]/g, ( c ) => '_' + c.charCodeAt( 0 ) + '_' );
}

/**
 * Generate CSS file with SVG data URIs for LaTeX symbols
 *
 * @param {string} symbolsFile Path to JSON file with symbol definitions
 * @param {string} cssFile Path to CSS file to generate or update
 * @param {string} inputType 'tex' or 'chem'
 * @return {Promise<void>} Promise that resolves when done
 */
async function generateCSS( symbolsFile, cssFile, inputType ) {
	let cssRules = []; // Whole CSS rules
	const
		rerenderAll = process.argv.slice( 2 ).includes( '--all' ),
		unmodifiedClasses = {},
		cssClasses = {}, // Unique part of class name and whether baseline is shifted
		generatedRules = [],
		currentRule = [],
		symbolList = [], // Symbols whose CSS rules need to be added or adjusted
		cssPrefix = '.ve-ui-mwLatexSymbol-',
		mathoidMaxConnections = 20,
		// If symbol.alignBaseline is true, a background-position property will be added to the
		// CSS rule to shift the baseline of the SVG to be a certain proportion of the way up the
		// button.
		singleButtonHeight = 1.8, // Height of the single-height math dialog buttons in em
		baseline = 0.65; // Proportion of the way down the button the baseline should be

	let symbolsData;
	try {
		// eslint-disable-next-line security/detect-non-literal-fs-filename
		symbolsData = await fs.readFile( symbolsFile, 'utf8' );
	} catch ( e ) {
		console.error( 'Failed to read symbols file:', symbolsFile, e );
		return;
	}
	let cssData;
	try {
		// eslint-disable-next-line security/detect-non-literal-fs-filename
		cssData = await fs.readFile( cssFile, 'utf8' );
	} catch ( e ) {
		cssData = '';
	}

	/**
	 * Make a Mathoid request for a symbol and generate CSS
	 *
	 * @param {Object} symbol Symbol definition from JSON file
	 * @return {Promise<void>} Promise that resolves when done
	 */
	async function makeRequest( symbol ) {
		const
			tex = symbol.tex || symbol.insert,
			data = new URLSearchParams( {
				q: inputType === 'chem' ? '\\ce{' + tex + '}' : tex,
				type: inputType
			} ).toString(),
			// API call to mathoid
			options = {
				host: 'localhost',
				port: '10044',
				path: '/',
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'Content-Length': Buffer.byteLength( data )
				}
			};
		let body;
		try {
			body = await httpRequest( options, data );
		} catch ( err ) {
			console.error( tex + ' FAILED:', err );
			return;
		}

		const
			className = texToClass( tex ),
			bodyData = JSON.parse( body ),
			svg = bodyData.svg;

		if ( Object.prototype.hasOwnProperty.call( generatedRules, className ) ) {
			console.log( className + ' already generated' );
			return;
		}

		generatedRules[ className ] = true;

		if ( !svg ) {
			console.error( tex + ' FAILED:', body );
			return;
		}

		let cssRule = cssPrefix + className + ' {\n' +
			'\tbackground-image: url( data:image/svg+xml,' + encodeURIComponentForCSS( svg ) + ' );\n';

		if ( symbol.alignBaseline ) {
			// Convert buttonHeight from em to ex, because SVG height is given in ex. (This is an
			// approximation, since the em:ex ratio differs from font to font.)
			const buttonHeight = symbol.largeLayout ? singleButtonHeight * 4 : singleButtonHeight * 1.9931;
			// height and verticalAlign rely on the format of the SVG parameters
			// HACK: Adjust these by a factor of 0.8 to match VE's default font size of 0.8em
			const height = parseFloat( bodyData.mathoidStyle.match( /height:\s*([\d.]+)ex/ )[ 1 ] ) * 0.8;
			const verticalAlign = -parseFloat( bodyData.mathoidStyle.match( /vertical-align:\s*([-\d.]+)ex/ )[ 1 ] ) * 0.8;
			// CSS percentage positioning is based on the difference between the image and container sizes
			const heightDifference = buttonHeight - height;
			const offset = 100 * ( verticalAlign - height + ( baseline * buttonHeight ) ) / heightDifference;

			cssRule += '\tbackground-position: 50% ' + offset + '%;\n' +
				'}';
			cssRules.push( cssRule );
			console.log( tex + ' -> ' + className );
		} else {
			cssRule += '}';
			cssRules.push( cssRule );
			console.log( tex + ' -> ' + className );
		}
	}

	if ( cssData ) {
		let currentClassName;
		const cssLines = cssData.split( '\n' );
		for ( let i = 0; i < cssLines.length; i++ ) {
			if ( cssLines[ i ].startsWith( cssPrefix ) ) {
				currentClassName = cssLines[ i ].slice( cssPrefix.length, -2 );
				currentRule.push( cssLines[ i ] );
				cssClasses[ currentClassName ] = false; // Default to false
			} else if ( currentRule.length ) {
				currentRule.push( cssLines[ i ] );
				if ( cssLines[ i ].startsWith( '\tbackground-position' ) ) {
					cssClasses[ currentClassName ] = true;
				}
				if ( cssLines[ i ].startsWith( '}' ) ) {
					cssRules.push( currentRule.join( '\n' ) );
					currentRule.splice( 0, currentRule.length );
				}
			}
		}
	}

	const symbolObject = JSON.parse( symbolsData );
	for ( const group in symbolObject ) {
		const symbols = symbolObject[ group ];
		for ( let i = 0; i < symbols.length; i++ ) {
			const symbol = symbols[ i ];
			if ( symbol.duplicate || symbol.notWorking ) {
				continue;
			}
			const currentClassName = texToClass( symbol.tex || symbol.insert );
			const alignBaseline = !symbol.alignBaseline;
			// If symbol is not in the old CSS file, or its alignBaseline status has changed,
			// add it to symbolList. Check to make sure it hasn't already been added.
			if (
				rerenderAll ||
				cssClasses[ currentClassName ] === undefined ||
				( unmodifiedClasses[ currentClassName ] !== true &&
					cssClasses[ currentClassName ] === alignBaseline )
			) {
				symbolList.push( symbol );
			} else {
				// At the end of this loop, any CSS class names that aren't in unmodifiedClasses
				// will be deleted from cssRules. cssRules will then only contain rules that will
				// stay unmodified.
				unmodifiedClasses[ currentClassName ] = true;
			}
		}
	}

	console.log( '----' );
	console.log( 'Comparing ' + cssFile + ' and ' + symbolsFile );
	console.log( Object.keys( cssClasses ).length + ' images found in ' + cssFile );
	console.log( symbolList.length + ' symbols need rendering' );
	if ( !rerenderAll ) {
		console.log( Object.keys( unmodifiedClasses ).length + ' symbols already rendered' );
		console.log( 'To re-render all symbols, use --all' );
	}

	// Keep only classes that will stay the same. Remove classes that are being adjusted and
	// classes of symbols that have been deleted from the JSON.
	cssRules = cssRules.filter( ( rule ) => {
		const currentClassName = rule.split( '\n' )[ 0 ].slice( cssPrefix.length, -2 );
		if ( unmodifiedClasses[ currentClassName ] ) {
			return true;
		}
		console.log( 'Removing or adjusting: ' + currentClassName );
		return false;
	} );

	let active = 0;
	let idx = 0;
	let done = 0;
	return new Promise( ( resolve ) => {
		async function runQueue() {
			while ( active < mathoidMaxConnections && idx < symbolList.length ) {
				active++;
				// eslint-disable-next-line no-loop-func
				makeRequest( symbolList[ idx ] ).finally( () => {
					active--;
					done++;
					if ( done === symbolList.length ) {
						cssRules.sort();
						// eslint-disable-next-line security/detect-non-literal-fs-filename
						fs.writeFile( cssFile,
							'/*!\n' +
							' * This file is GENERATED by tools/makeSvgsAndCss.js\n' +
							' * DO NOT EDIT\n' +
							' */\n' +
							cssRules.join( '\n\n' ) +
							'\n'
						).then( resolve );
					} else {
						runQueue();
					}
				} );
				idx++;
			}
		}
		runQueue();
	} );
}

( async () => {
	await generateCSS(
		path.join( __dirname, '../mathSymbols.json' ),
		path.join( __dirname, '../ve.ui.MWMathSymbols.css' ),
		'tex'
	);
	await generateCSS(
		path.join( __dirname, '../chemSymbols.json' ),
		path.join( __dirname, '../ve.ui.MWChemSymbols.css' ),
		'chem'
	);
} )();
