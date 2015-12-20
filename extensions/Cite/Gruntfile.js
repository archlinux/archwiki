/*!
 * Grunt file
 *
 * @package Cite
 */

/*jshint node:true */
module.exports = function ( grunt ) {
	'use strict';
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.initConfig( {
		banana: {
			all: ['i18n/']
		},
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'jsonlint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
