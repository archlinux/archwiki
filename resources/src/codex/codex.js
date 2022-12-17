const codex = require( '../../lib/codex/codex.umd.js' );

// Codex is written for Vue 3, but we're running the compatibility build of Vue 3 (@vue/compat),
// which behaves like Vue 2 in certain cases. This causes issues, specfically with v-model use
// on components and with boolean attributes. Tell Vue to disable this compatibility behavior
// for Codex components by setting .compatConfig = { MODE: 3 } on all components. Do this
// recursively, so that internal components are included too.

function recursivelyMarkAsVue3( component ) {
	if ( component.compatConfig ) {
		return;
	}
	component.compatConfig = { MODE: 3 };
	for ( const childComponentName in component.components || {} ) {
		recursivelyMarkAsVue3( component.components[ childComponentName ] );
	}
}

for ( const key in codex ) {
	if ( typeof codex[ key ] !== 'function' ) {
		recursivelyMarkAsVue3( codex[ key ] );
	}
}

module.exports = codex;
