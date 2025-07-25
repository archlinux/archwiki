'use strict';

$( () => {
	const Vue = require( 'vue' );
	const App = require( './components/App.vue' );

	Vue.createMwApp( App ).mount( '#ext-checkuser-tempaccountsonboarding-app' );
} );
