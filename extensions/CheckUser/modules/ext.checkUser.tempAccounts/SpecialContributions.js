const ipReveal = require( './ipReveal.js' );

/**
 * Add IP reveal functionality to contributions pages that show contributions made by a single
 * temporary user. See ipReveal#enableIpRevealForContributionsPage for details.
 *
 * @param {string|*} documentRoot A Document or selector to use as the root of the
 *   search for elements
 * @param {string} pageTitle Declare what page this is being run on.
 *   This is for compatibility across Special:Contributions and Special:DeletedContributions,
 *   as they have different guaranteed existing elements.
 */
module.exports = function ( documentRoot, pageTitle ) {
	ipReveal.enableIpRevealForContributionsPage( documentRoot, pageTitle );
};
