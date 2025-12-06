const Vue = require( 'vue' ),
	NukeNamespaceLookup = require( './components/NukeNamespaceLookup.vue' ),
	NukeTargetLookup = require( './components/NukeTargetLookup.vue' );

// This targets the `.cdx-field` which contains the namespace lookup box.
const namespacesField = document.getElementById( 'nuke-namespace' );
const namespacesTextfield = namespacesField.getElementsByTagName( 'textarea' )[ 0 ];
Vue.createMwApp( NukeNamespaceLookup, { namespaces: namespacesTextfield.value } )
	.mount( namespacesField );

const targetField = document.getElementById( 'nuke-target' );
const targetInputField = targetField.getElementsByTagName( 'input' )[ 0 ];
Vue.createMwApp( NukeTargetLookup, { target: targetInputField.value } )
	.mount( targetField );
