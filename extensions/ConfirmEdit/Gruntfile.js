/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
		jshint: {
			all: [
				'*.js'
			]
		},
		banana: {
			all: [
				'i18n/',
				'**/i18n/'
			]
		},
		jsonlint: {
			all: [
				'*.json',
				'**/*.json',
				'!node_modules/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'jshint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
