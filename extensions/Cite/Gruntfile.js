/*!
 * Grunt file
 *
 * @package Cite
 */

/* eslint-env node, es6 */

module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );
	grunt.initConfig( {
		eslint: {
			fix: {
				options: {
					fix: true
				},
				src: '<%= eslint.main %>'
			},
			main: [
				'**/*.js',
				'{.jsduck,build}/**/*.js',
				'modules/**/*.js',
				'!node_modules/**'
			]
		},
		banana: conf.MessagesDirs,
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

	grunt.registerTask( 'test', [ 'eslint:main', 'stylelint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
