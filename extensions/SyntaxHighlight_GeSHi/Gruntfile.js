/*!
 * Grunt file
 *
 * @package SyntaxHighlight_GeSHi
 */

/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-jscs' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		jshint: {
			options: {
				jshintrc: true
			},
			all: [
				'*.js',
				'modules/**/*.js'
			]
		},
		jsonlint: {
			all: [
				'*.json',
				'i18n/*.json',
				'modules/**/*.json'
			]
		},
		jscs: {
			all: '<%= jshint.all %>'
		},
		stylelint: {
			all: [
				'**/*.css',
				'!**/*.generated.css',
				'!vendor/**',
				'!node_modules/**'
			]
		},
		banana: {
			options: {
				disallowDuplicateTranslations: false
			},
			all: 'i18n/'
		},
		watch: {
			files: [
				'.{stylelintrc,jscsrc,jshintignore,jshintrc}',
				'<%= jshint.all %>',
				'<%= stylelint.all %>'
			],
			tasks: 'test'
		}
	} );

	grunt.registerTask( 'test', [ 'jshint', 'jsonlint', 'jscs', 'stylelint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
