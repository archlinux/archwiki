const { createMwApp } = require( 'vue' );
const OATHManage = require( './OATHManage.vue' );

const vueContainer = document.querySelector( '.mw-special-OATHManage-vue-container' );
if ( vueContainer ) {
	createMwApp( OATHManage ).mount( vueContainer );
}
