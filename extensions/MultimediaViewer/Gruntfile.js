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
				reportUnusedDisableDirectives: true,
				extensions: [ '.js', '.json' ],
				cache: true
			},
			all: [
				'**/*.{js,json}',
				'!{vendor,node_modules,docs}/**'
			]
		},
		stylelint: {
			options: {
				syntax: 'less'
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

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'svgmin', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
