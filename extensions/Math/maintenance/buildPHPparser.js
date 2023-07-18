#!/usr/bin/env node
/**
 * This script is used for generating Parser.php from parser.pegjs.
 * This is used for creating new versions of the parser expression grammar
 * in TexVC.
 *
 * Make sure to have the node dev dependencies from package.json installed.
 * Run it with: "$node maintenance/buildPHPparser.js <optional params>"
 *
 * Parameters can be defined over CLI parameters or by changing the
 * DEFAULT_PATH_XYZ constants in this file.
 *
 * @author Johannes Stegmüller
 */

'use strict';

const { program } = require( 'commander' );
const peggy = require( 'peggy' );
const phpeggy = require( 'phpeggy' );
const fs = require( 'fs' );
const DEFAULT_PATH_INPUT = './src/TexVC/parser.pegjs';
const DEFAULT_PATH_OUTPUT = './src/TexVC/Parser.php';
const PHP_INSERTION_LINE = 9; // indicates where the 'use_xyz' statements are inserted

program
	.name( 'buildPHPparser' )
	.option( '-i, --input <string>',
		'path of input parser.pegjs file (*.pegjs)', DEFAULT_PATH_INPUT )
	.option( '-o, --output <string>',
		'path of generated output file (*.php)', DEFAULT_PATH_OUTPUT )
	.option( '-d, --debug',
		'debug logging activated', false )
	.description( 'Generates Parser.php as output from parser.pegjs as input. ' +
		'This is used for for updating the parser expression grammar in TexVC ' +
		'which is located in src/TexVC' )
	.version( '0.1.0' );

program.parse();

const options = program.opts();
console.log( 'Running buildPHPparser.js with this configuration: \n' +
	'input path:\t' + options.input + '\n' +
	'output path:\t' + options.output );

const parserPeg = fs.readFileSync( options.input, 'utf-8' );
let parser = peggy.generate( parserPeg, {
	plugins: [ phpeggy ],
	cache: true,
	phpeggy: {
		parserNamespace: 'MediaWiki\\Extension\\Math\\TexVC'
	}
} );

const useStatements =
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Box;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Big;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\ChemFun2u;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\ChemWord;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Curly;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Declh;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Dollar;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\DQ;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\FQ;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Fun1;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Fun1nb;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Fun2;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Fun2nb;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Fun2sq;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Infix;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Literal;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Lr;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Matrix;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\Mhchem;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\UQ;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\Nodes\\TexArray;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\TexUtil;\n' +
	'use MediaWiki\\Extension\\Math\\TexVC\\ParserUtil;';

function addUseStatements( p, lineStart = PHP_INSERTION_LINE ) {
	// Adding the specified use statements
	const splitParser = p.split( '\n' );
	splitParser.splice( lineStart, 0, useStatements );
	return splitParser.join( '\n' );
}

parser = addUseStatements( parser );

/**
 * Fixing phpeggy to denote regular expressions which
 * are \x12 to \x{0012} so php can interpret them.
 * can be removed when phpeggy is fixed, see:
 * https://phabricator.wikimedia.org/T320964
 */
const regexp = /\\x(\d\d)/g;
if ( options.debug ) {
	const matches = parser.match( regexp );
	for ( const match of matches ) {
		console.log( `Found ${match}.` );
	}
}
const newParse = parser.replace( regexp, '\\x{00$1}' );

fs.writeFileSync( options.output, newParse );
console.log( 'Generated output file at: ' + options.output );
