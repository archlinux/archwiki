/**
 * @external MenuDefinition
 * @external UserLinksDefinition
 */
import msgs from '../i18n/en.json';
import mustache from 'mustache';
import { menuTemplate } from './Menu.stories.data';
import userLinksTemplateLegacy from '!!raw-loader!../includes/templates/legacy/UserLinks.mustache';
import userLinksTemplate from '!!raw-loader!../includes/templates/UserLinks.mustache';
import userLinksMoreTemplate from '!!raw-loader!../includes/templates/UserLinks__more.mustache';
import userLinksLogoutTemplate from '!!raw-loader!../includes/templates/UserLinks__logout.mustache';
import userLinksLoginTemplate from '!!raw-loader!../includes/templates/UserLinks__login.mustache';
import { helperClassName, helperMakeMenuData } from './utils';

/**
 * Legacy User Links
 */

const ECHO_ITEMS = `<li id="pt-notifications-alert"><a href="/wiki/Special:Notifications" class="mw-echo-notifications-badge mw-echo-notification-badge-nojs oo-ui-icon-bell mw-echo-notifications-badge-all-read" data-counter-num="0" data-counter-text="0" title="Your alerts">Alerts (0)</a></li><li id="pt-notifications-notice"><a href="/wiki/Special:Notifications" class="mw-echo-notifications-badge mw-echo-notification-badge-nojs oo-ui-icon-tray" data-counter-num="3" data-counter-text="3" title="Your notices">Notices (3)</a></li>`;
const USERNAME_ITEM = `<li id="pt-userpage"><a href="/wiki/User:WikiUser" dir="auto" title="Your user page [⌃⌥.]" accesskey=".">WikiUser</a></li>`;
const REST_ITEMS = `<li id="pt-mytalk"><a href="/wiki/User_talk:WikiUser" title="Your talk page [⌃⌥n]" accesskey="n">Talk</a></li><li id="pt-sandbox"><a href="/wiki/User:WikiUser/sandbox" title="Your sandbox">Sandbox</a></li><li id="pt-preferences"><a href="/wiki/Special:Preferences" title="Your preferences">Preferences</a></li><li id="pt-betafeatures"><a href="/wiki/Special:Preferences#mw-prefsection-betafeatures" title="Beta features">Beta</a></li><li id="pt-watchlist"><a href="/wiki/Special:Watchlist" title="A list of pages you are monitoring for changes [⌃⌥l]" accesskey="l">Watchlist</a></li><li id="pt-mycontris"><a href="/wiki/Special:Contributions/WikiUser" title="A list of your contributions [⌃⌥y]" accesskey="y">Contributions</a></li>`;
const LOGOUT_ITEM = `<li id="pt-logout"><a href="/w/index.php?title=Special:UserLogout&amp;returnto=Main+Page&amp;returntoquery=useskin%3Dvector" title="Log out">Log out</a></li>`;
const ULS_LANGUAGE_SELECTOR = '<li class="uls-trigger active"><a href="#">English</a></li>';

/**
 * @type {MenuDefinition}
 */
const loggedOut = helperMakeMenuData(
	'personal',
	`<li id="pt-anonuserpage">Not logged in</li><li id="pt-anontalk"><a href="/wiki/Special:MyTalk" title="Discussion about edits from this IP address [⌃⌥n]" accesskey="n">Talk</a></li><li id="pt-anoncontribs"><a href="/wiki/Special:MyContributions" title="A list of edits made from this IP address [⌃⌥y]" accesskey="y">Contributions</a></li><li id="pt-createaccount"><a href="/w/index.php?title=Special:CreateAccount&amp;returnto=Main+Page" title="You are encouraged to create an account and log in; however, it is not mandatory">Create account</a></li><li id="pt-login"><a href="/w/index.php?title=Special:UserLogin&amp;returnto=Main+Page" title="You're encouraged to log in; however, it's not mandatory. [⌃⌥o]" accesskey="o">Log in</a></li>`,
	helperClassName( 'vector-user-menu-legacy' )
);

/**
 * @type {MenuDefinition}
 */
const loggedInWithEcho = helperMakeMenuData(
	'personal',
	`${USERNAME_ITEM}${ECHO_ITEMS}${REST_ITEMS}${LOGOUT_ITEM}`,
	helperClassName( 'vector-user-menu-legacy' )
);

/**
 * @type {MenuDefinition}
 */
const loggedInWithULS = helperMakeMenuData(
	'personal',
	`${ULS_LANGUAGE_SELECTOR}${USERNAME_ITEM}${ECHO_ITEMS}${REST_ITEMS}${LOGOUT_ITEM}`,
	helperClassName( 'vector-user-menu-legacy' )
);

/**
 * @type {Object.<string, MenuDefinition>}
 */
const PERSONAL_MENU_TEMPLATE_DATA = {
	loggedOut,
	loggedInWithEcho,
	loggedInWithULS
};

/**
 * Modern User Links
 */

const LOGGED_IN_ITEMS = `
	<li id="pt-userpage" class="user-links-collapsible-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-userAvatar" href="/wiki/User:Test" dir="auto" title="Your user page [ctrl-option-.]" accesskey="."><span>Test</span></a></li>
	<li id="pt-mytalk"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-userTalk" href="/wiki/User_talk:WikiUser" title="Your talk page [⌃⌥n]" accesskey="n"><span>Talk</span></a></li>
	<li id="pt-sandbox"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-markup" href="/wiki/User:WikiUser/sandbox" title="Your sandbox"><span>Sandbox</span></a></li>
	<li id="pt-preferences"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-settings" href="/wiki/Special:Preferences" title="Your preferences"><span>Preferences</span></a></li>
	<li id="pt-watchlist"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-unStar" href="/wiki/Special:Watchlist" title="A list of pages you are monitoring for changes [⌃⌥l]" accesskey="l"><span>Watchlist</span></a></li>
	<li id="pt-mycontris"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-userContributions" href="/wiki/Special:Contributions/WikiUser" title="A list of your contributions [⌃⌥y]" accesskey="y"><span>Contributions</span></a></li>
`;
const LOGGED_OUT_ITEMS = `
<li id="pt-anontalk"><a href="/wiki/Special:MyTalk" title="Discussion about edits from this IP address [ctrl-option-n]" accesskey="n"><span>Talk</span></a></li>
	<li id="pt-anoncontribs"><a href="/wiki/Special:MyContributions" title="A list of edits made from this IP address [ctrl-option-y]" accesskey="y"><span>Contributions</span></a></li>
`;

const additionalUserMoreData = {
	class: 'vector-menu vector-user-menu-more',
	'heading-class': 'vector-menu-heading',
	'is-dropdown': false
};

const userMoreHtmlItems = ( isAnon = true ) => mustache.render( userLinksMoreTemplate, {
	'is-anon': isAnon,
	'is-create-account-allowed': isAnon,
	'html-create-account': `<a href="/w/index.php?title=Special:CreateAccount&amp;returnto=Main+Page" class="mw-ui-button mw-ui-quiet" title="You are encouraged to create an account and log in; however, it is not mandatory">Create account</a>`,
	'data-user-page': helperMakeMenuData( 'user-page', `
		<li id="pt-userpage-2" class="user-links-collapsible-item">
			<a href="/wiki/User:WikiUser" dir="auto" title="Your user page [⌃⌥.]" accesskey="." class="mw-ui-button mw-ui-quiet"><span>WikiUser</span></a>
		</li>
	` ),
	'data-notifications': helperMakeMenuData( 'notifications', ECHO_ITEMS )
} );

const loggedInData = {
	class: 'vector-user-menu vector-menu-dropdown vector-user-menu-logged-in',
	'is-dropdown': true,
	'heading-class': 'mw-ui-button mw-ui-quiet mw-ui-icon mw-ui-icon-element mw-ui-icon-wikimedia-userAvatar',
	'is-anon': false,
	'html-after-portal': mustache.render( userLinksLogoutTemplate, {
		htmlLogout: `<a class="vector-menu-content-item vector-menu-content-item-logout mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-logOut" data-mw="interface" href="/w/index.php?title=Special:UserLogout&amp;returnto=Main+Page"><span>Log out</span></a>`
	} )
};

const loggedOutData = {
	class: 'vector-user-menu vector-menu-dropdown vector-user-menu-logged-out',
	'is-dropdown': true,
	'heading-class': 'mw-ui-button mw-ui-quiet mw-ui-icon mw-ui-icon-element mw-ui-icon-wikimedia-ellipsis',
	'is-anon': true,
	'html-before-portal': mustache.render( userLinksLoginTemplate, {
		htmlCreateAccount: `<a href="/w/index.php?title=Special:CreateAccount&amp;returnto=Special%3AUserLogout" icon="userAvatar" class="user-links-collapsible-item vector-menu-content-item mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-userAvatar" title="You are encouraged to create an account and log in; however, it is not mandatory"><span>Create account</span></a>`,
		htmlLogin: `<a class="vector-menu-content-item vector-menu-content-item-login mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-logIn" href="/w/index.php?title=Special:UserLogin&amp;returnto=Main+Page" title="You are encouraged to log in; however, it is not mandatory [ctrl-option-o]" accesskey="o"><span>Log in</span></a>`,
		msgLearnMore: msgs[ 'vector-anon-user-menu-pages' ],
		htmlLearnMoreLink: `<a href="/wiki/Help:Introduction"><span>${msgs[ 'vector-anon-user-menu-pages-learn' ]}</span></a>:`
	} )
};

/**
 * @type {UserLinksDefinition}
 */
const USER_LINKS_LOGGED_IN_TEMPLATE_DATA = {
	'data-user-more': helperMakeMenuData( 'personal-more', userMoreHtmlItems( false ), additionalUserMoreData ),
	'data-user-menu': helperMakeMenuData( 'new-personal', LOGGED_IN_ITEMS, loggedInData )
};

/**
 * @type {UserLinksDefinition}
 */
const USER_LINKS_LOGGED_OUT_TEMPLATE_DATA = {
	'data-user-more': helperMakeMenuData( 'personal-more', userMoreHtmlItems( true ), additionalUserMoreData ),
	'data-user-menu': helperMakeMenuData( 'new-personal', LOGGED_OUT_ITEMS, loggedOutData )
};

export {
	PERSONAL_MENU_TEMPLATE_DATA,
	USER_LINKS_LOGGED_IN_TEMPLATE_DATA,
	USER_LINKS_LOGGED_OUT_TEMPLATE_DATA,
	menuTemplate,
	userLinksTemplateLegacy,
	userLinksTemplate
};
