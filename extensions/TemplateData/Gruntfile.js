/*!
 * Grunt file
 *
 * @package TemplateData
 */

/* eslint-env node */
module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			options: {
				extensions: [ '.js', '.json' ],
				cache: true
			},
			all: [
				'**/*.{js,json}',
				'!{lib,vendor,node_modules,docs}/**'
			]
		},
		stylelint: {
			all: [
				'modules/*.css',
				'resources/*.css'
			]
		},
		banana: {
			options: {
				requireLowerCase: false
			},
			all: conf.MessagesDirs.TemplateData
		},
		watch: {
			files: [
				'.{stylelintrc,eslintrc}.json',
				'<%= eslint.all %>',
				'<%= stylelint.all %>'
			],
			tasks: 'test'
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
