'use strict';

const { iAmOnPage } = require( './common_steps' );

const username = browser.config.mwUser.replace( /_/g, ' ' );

const iVisitMyUserPage = async () => {
	await iAmOnPage( `User:${ username }` );
};

module.exports = { iVisitMyUserPage };
