( function () {
	'use strict';
	const extensionAssetsPath = mw.config.get( 'wgExtensionAssetsPath' );
	window.MathJax = {
		loader: {
			// see https://docs.mathjax.org/en/latest/input/mathml.html
			load: [ '[mml]/mml3' ],
			// see https://docs.mathjax.org/en/latest/options/startup/loader.html
			paths: {
				mathjax: extensionAssetsPath + '/Math/modules/mathjax/es5'
			}
		},
		// helper function for https://phabricator.wikimedia.org/T375932
		/* eslint-disable no-return-assign */
		remapChars( v1, v2, base, map, font ) {
			const c1 = v1.chars;
			const c2 = v2.chars;
			for ( let i = 0; i < 26; i++ ) {
				const data1 = c1[ map[ i ] || base + i ] || [];
				const data2 = c2[ 0x41 + i ];
				if ( data1.length === 0 ) {
					c1[ base + i ] = data1;
				}
				[ 0, 1, 2 ].forEach( ( j ) => data1[ j ] = data2[ j ] );
				data1[ 3 ] = Object.assign( {}, data2[ 3 ], { f: font, c: String.fromCharCode( 0x41 + i ) } );
			}
		},

		startup: {
			ready() {
				window.MathJax.startup.defaultReady();
				// See https://phabricator.wikimedia.org/T375932 and the suggested fix from
				// https://github.com/mathjax/MathJax/issues/3292#issuecomment-2383760829
				// Makes rendering of \matcal look similar to the browsers MathML rendering
				// and the old image rendering.
				// Note that \mathsrc (which is unsupported by texvc) would map to the
				// same unicode chars and thus should not be activated.
				const variant = window.MathJax.startup.document.outputJax.font.variant;
				const map = { 1: 0x212C, 4: 0x2130, 5: 0x2131, 7: 0x210B, 8: 0x2110, 11: 0x2112, 12: 0x2133, 17: 0x211B };
				window.MathJax.config.remapChars( variant.normal, variant[ '-tex-calligraphic' ], 0x1D49C, map, 'C' );
				window.MathJax.config.remapChars( variant.normal, variant[ '-tex-bold-calligraphic' ], 0x1D4D0, {}, 'CB' );
				// See https://phabricator.wikimedia.org/T375241 and the suggested startup function from
				// https://github.com/mathjax/MathJax/issues/3030#issuecomment-1490520850
				// This workaround will be included in the MathJax 4 release and no longer be
				// required when we upgrade to MathJax 4.
				const { Mml3 } = window.MathJax._.input.mathml.mml3.mml3;
				const mml3 = new Mml3( window.MathJax.startup.document );
				const adaptor = window.MathJax.startup.document.adaptor;
				const processor = new XSLTProcessor();
				const parsed = adaptor.parse( Mml3.XSLT, 'text/xml' );
				processor.importStylesheet( parsed );
				mml3.transform = ( node ) => {
					const div = adaptor.node( 'div', {}, [ adaptor.clone( node ) ] );
					const dom = adaptor.parse( adaptor.serializeXML( div ), 'text/xml' );
					const mml = processor.transformToDocument( dom );
					return ( mml ? adaptor.tags( mml, 'math' )[ 0 ] : node );
				};
				// inputJax[0] did not work as per https://github.com/mathjax/MathJax/issues/3287#issuecomment-2363843017
				const MML = window.MathJax.startup.document.inputJax[ 1 ];
				MML.mmlFilters.items.pop(); // remove old filter
				MML.mmlFilters.add( mml3.mmlFilter.bind( mml3 ) );
			}
		}
	};
}() );
