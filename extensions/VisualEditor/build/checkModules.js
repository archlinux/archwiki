/**
 * Checks that all files referenced in lib/ve/build/modules.json are either:
 *   - referenced by a ResourceModule's veModules in extension.json (and thus loaded dynamically), or
 *   - explicitly listed in scripts/styles in extension.json, or
 *   - ignored via the ignore list in this script.
 *
 * Warns about unused "ignored" entries and errors on missing files.
 */

'use strict';

const path = require( 'path' );

const extensionJson = require( '../extension.json' );
const modulesJson = require( '../lib/ve/build/modules.json' );

/**
 * List of file/directory patterns to ignore.
 *
 * @type {string[]}
 */
const ignored = [
	'node_modules/',
	'lib/',
	'dist/',
	'src/ve.track.js', // Re-implemented in MW
	'tests/ve.qunit.local.js',
	// Standalone / Demos
	'demos/',
	'src/init/sa/',
	'tests/init/ve.init.sa.Platform.test.js',
	'src/ui/dialogs/ve.ui.DiffDialog.js',
	// Rebaser
	'rebaser/',
	// TODO: Put these is a folder
	'tests/ve.FakePeer.test.js',
	'tests/dm/ve.dm.TransactionSquasher.test.js',
	'tests/dm/ve.dm.RebaseServer.test.js',
	'tests/dm/ve.dm.FakeMongo.js',
	'tests/dm/ve.dm.FakeSocket.js',
	'tests/dm/ve.dm.DocumentStore.test.js',
	'tests/dm/ve.dm.TransportServer.test.js'
];

/**
 * Ignored entries which haven't been used yet.
 *
 * @type {Set<string>}
 */
const unusedIgnores = new Set( ignored );

/**
 * Add file or files to a Set, prefixing with basePath.
 *
 * @param {string|string[]|Object[]} files
 * @param {Set<string>} set
 * @param {string} [basePath='']
 */
function addFilesToSet( files, set, basePath = '' ) {
	if ( Array.isArray( files ) ) {
		files.forEach( ( file ) => set.add( path.join( basePath, file.file || file ) ) );
	} else if ( typeof files === 'string' ) {
		set.add( path.join( basePath, files ) );
	}
}

/**
 * Check if the filePath matches any 'ignored' entry.
 *
 * If matched, the entry is removed from unusedIgnores.
 *
 * @param {string} filePath
 * @return {boolean}
 */
function isIgnored( filePath ) {
	return ignored.some( ( ignorePath ) => {
		const fullIgnorePath = path.join( 'lib/ve', ignorePath );
		const match = filePath === fullIgnorePath || filePath.startsWith( fullIgnorePath );
		if ( match ) {
			unusedIgnores.delete( ignorePath );
		}
		return match;
	} );
}

/**
 * Add all scripts and styles files from a modules object to a Set.
 *
 * @param {Object} modules
 * @param {Set<string>} set
 * @param {string} [basePath]
 */
function addModulesToSet( modules, set, basePath = '' ) {
	Object.values( modules ).forEach( ( module ) => {
		[
			'scripts',
			'debugScripts',
			'styles'
		].forEach( ( property ) => {
			if ( module[ property ] ) {
				addFilesToSet( module[ property ], set, basePath );
			}
		} );
		if ( module.skinStyles ) {
			Object.values( module.skinStyles ).forEach( ( files ) => {
				addFilesToSet( files, set, basePath );
			} );
		}
	} );
}

const extensionFiles = new Set();
addModulesToSet( extensionJson.ResourceModules, extensionFiles );
addModulesToSet( { QUnitTestModule: extensionJson.QUnitTestModule }, extensionFiles );

// Modules listed in veModules of ResourceModules are loaded automatically.
Object.values( extensionJson.ResourceModules ).forEach( ( module ) => {
	if ( module.veModules ) {
		module.veModules.forEach( ( veModule ) => {
			addModulesToSet( { [ veModule ]: modulesJson[ veModule ] || {} }, extensionFiles, 'lib/ve' );
		} );
	}
} );

const modulesFiles = new Set();
addModulesToSet( modulesJson, modulesFiles, 'lib/ve' );
const missingFiles = Array.from( modulesFiles ).filter( ( file ) => !extensionFiles.has( file ) && !isIgnored( file ) );

if ( unusedIgnores.size ) {
	console.warn(
		'Unused ignore path(s) in checkModules.js:\n\n' +
		Array.from( unusedIgnores ).map( ( ignore ) => `* ${ ignore }\n` ).join( '' )
	);
}

if ( missingFiles.length ) {
	console.error(
		`${ missingFiles.length } file(s) from lib/ve/modules.json are missing from extension.json:\n\n` +
		missingFiles.map( ( file ) => `* ${ file }\n` ).join( '' ) +
		'\nIf any of these files are not required, add them to the ignore list in build/checkModules.js.'
	);
	// eslint-disable-next-line n/no-process-exit
	process.exit( 1 );
} else {
	console.log( 'No missing files.' );
}
