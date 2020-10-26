/* eslint-env node */

module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );
	grunt.loadNpmTasks( 'grunt-svgmin' );

	grunt.initConfig( {
		banana: conf.MessagesDirs,
		eslint: {
			options: {
				cache: true,
				fix: grunt.option( 'fix' )
			},
			all: [
				'**/*.{js,json}',
				'!{vendor,node_modules,docs}/**'
			]
		},
		stylelint: {
			options: {
				fix: grunt.option( 'fix' )
			},
			src: 'resources/mmv/**/*.{css,less}'
		},
		// Image Optimization
		svgmin: {
			options: {
				js2svg: {
					indent: '\t',
					pretty: true
				},
				multipass: true,
				plugins: [ {
					cleanupIDs: false
				}, {
					removeDesc: false
				}, {
					removeRasterImages: true
				}, {
					removeTitle: false
				}, {
					removeViewBox: false
				}, {
					removeXMLProcInst: false
				}, {
					sortAttrs: true
				} ]
			},
			all: {
				files: [ {
					expand: true,
					cwd: 'resources',
					src: [
						'**/*.svg'
					],
					dest: 'resources/',
					ext: '.svg'
				} ]
			}
		}
	} );

	grunt.registerTask( 'lint', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'minify', 'svgmin' );

	grunt.registerTask( 'test', 'lint' );
	grunt.registerTask( 'build', 'minify' );
	grunt.registerTask( 'default', [ 'test', 'build' ] );
};
