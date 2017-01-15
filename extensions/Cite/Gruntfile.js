/*!
 * Grunt file
 *
 * @package Cite
 */

/*jshint node:true */
module.exports = function ( grunt ) {
	'use strict';
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-jscs' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-stylelint' );
	grunt.initConfig( {
		jshint: {
			options: {
				jshintrc: true
			},
			all: [
				'**/*.js',
				'{.jsduck,build}/**/*.js',
				'modules/**/*.js',
				'!node_modules/**'
			]
		},
		banana: {
			core: [ 'i18n/' ],
			ve: [ 'modules/ve-cite/i18n/' ]
		},
		jscs: {
			fix: {
				options: {
					fix: true
				},
				src: '<%= jshint.all %>'
			},
			main: {
				src: '<%= jshint.all %>'
			}
		},
		stylelint: {
			core: {
				src: [
					'**/*.css',
					'!modules/ve-cite/**',
					'!node_modules/**'
				]
			},
			've-cite': {
				options: {
					configFile: 'modules/ve-cite/.stylelintrc'
				},
				src: [
					'modules/ve-cite/**/*.css'
				]
			}
		},
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'jshint', 'jscs:main', 'stylelint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
