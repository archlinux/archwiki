( function () {
	const Vue = require( 'vue' );
	const App = require( './components/CommunityConfiguration.vue' );

	Vue.createMwApp( App )
		.mount( '#ext-cite-configuration-vue-root' );
}() );
