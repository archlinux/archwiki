/**
 * Vue + Codex username policy popover.
 *
 * @module mediawiki.special.createaccount
 */
'use strict';

const Vue = require( 'vue' );
const UsernamePolicyPopover = require( './UsernamePolicyPopover.vue' );

/**
 * Mount the username policy popover (Codex bottom sheet) next to the “Choose carefully” trigger.
 *
 * @param {HTMLElement} trigger
 * @param {Object} [options]
 * @param {boolean} [options.openOnMount] Show the policy popover as soon as the component mounts.
 */
function mountUsernamePolicyPopover( trigger, options ) {
	options = options || {};
	const mountPoint = document.body.appendChild( document.createElement( 'div' ) );
	Vue.createMwApp( UsernamePolicyPopover, {
		triggerElement: trigger,
		openOnMount: !!options.openOnMount
	} ).mount( mountPoint );
}

module.exports = mountUsernamePolicyPopover;
