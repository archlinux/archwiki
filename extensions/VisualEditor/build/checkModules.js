'use strict';

const path = require( 'path' );

const extensionJson = require( '../extension.json' );
const modulesJson = require( '../lib/ve/build/modules.json' );

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

const unusedIgnores = new Set( ignored );

function addFilesToSet( files, set, basePath = '' ) {
	if ( Array.isArray( files ) ) {
		files.forEach( ( file ) => set.add( path.join( basePath, file.file || file ) ) );
	} else if ( typeof files === 'string' ) {
		set.add( path.join( basePath, files ) );
	}
}

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

function checkFiles() {
	const extensionFiles = new Set();
	addModulesToSet( extensionJson.ResourceModules, extensionFiles );
	addModulesToSet( { QUnitTestModule: extensionJson.QUnitTestModule }, extensionFiles );

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
}

checkFiles();
