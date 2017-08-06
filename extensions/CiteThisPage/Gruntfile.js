/*!
 * Grunt file
 *
 * @package CiteThisPage
 */

/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		banana: {
			all: ["i18n/"]
		},
		jsonlint: {
			all: [
				'**/*.json',
				'.stylelintrc',
				'!node_modules/**'
			]
		},
		stylelint: {
			all: [
				'**/*.css',
				'!node_modules/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'jsonlint', 'banana', 'stylelint' ] );
	grunt.registerTask( 'default', 'test' );
};
