/**
 * A list of all MediaWiki core pageObjects.
 * To simplify imports in world.js.
 */

import Page from 'wdio-mediawiki/Page';
import UserLoginPage from 'wdio-mediawiki/LoginPage';

export { Page };

export default {
	// Page is a constructor, all other pageObjects are instances.
	Page,
	UserLoginPage
};
