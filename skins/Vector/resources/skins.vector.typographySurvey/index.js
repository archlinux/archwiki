if ( mw.config.get( 'wgNamespaceNumber' ) === 0 ) {
	mw.loader.using( [ 'vue' ] ).then( function () {
		const Vue = require( 'vue' ),
			App = require( './TypographySurvey.vue' ),
			mountEl = document.createElement( 'div' );

		mountEl.id = 'vector-typography-survey';
		document.body.appendChild( mountEl );
		// @ts-ignore
		Vue.createMwApp( App ).mount( mountEl );
	} );
}
