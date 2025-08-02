/**
 * World
 *
 * World is a function that is bound as `this` to each step of a scenario.
 * It is reset for each scenario.
 * https://github.com/cucumber/cucumber-js/blob/master/docs/support_files/world.md
 *
 * We do not have a World function bound to scenarios with `setWorldConstructor`.
 * Instead, we have a simple object that encapsulates all dependencies,
 * and is exported so that it can be imported into each step definition file,
 * allowing us to use the dependencies across scenarios.
 */

'use strict';

const mwCorePages = require( '../support/pages/mw_core_pages' ),
	minervaPages = require( '../support/pages/minerva_pages' );

module.exports = {
	...mwCorePages,
	...minervaPages
};
