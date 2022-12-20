const mustache = require( 'mustache' );
const fs = require( 'fs' );
const userLinksTemplate = fs.readFileSync( 'includes/templates/UserLinks.mustache', 'utf8' );
const menuTemplate = fs.readFileSync( 'includes/templates/Menu.mustache', 'utf8' );

const templateData = {
	'is-wide': false,
	'data-user-menu-overflow': {
		id: 'p-personal-more',
		class: 'mw-portlet mw-portlet-vector-user-menu-overflow vector-user-menu-overflow',
		label: 'Toggle sidebar',
		'html-items': `
			<li id="ca-uls" class="user-links-collapsible-item mw-list-item active"><a href="#" class="uls-trigger mw-ui-button mw-ui-quiet"><span class="mw-ui-icon mw-ui-icon-wikimedia-language"></span> <span>English</span></a></li>
			<li id="pt-userpage-2" class="user-links-collapsible-item mw-list-item"><a href="/wiki/User:Admin" class="mw-ui-button mw-ui-quiet" title="Your user page [⌃⌥.]" accesskey="."><span>Admin</span></a></li>
			<li id="pt-notifications-alert" class="mw-list-item"><a href="/wiki/Special:Notifications" class="mw-echo-notifications-badge mw-echo-notification-badge-nojs oo-ui-icon-bell mw-echo-notifications-badge-all-read" data-counter-num="0" data-counter-text="0" title="Your alerts"><span>Alerts (0)</span></a></li>
			<li id="pt-notifications-notice" class="mw-list-item"><a href="/wiki/Special:Notifications" class="mw-echo-notifications-badge mw-echo-notification-badge-nojs oo-ui-icon-tray mw-echo-notifications-badge-all-read" data-counter-num="0" data-counter-text="0" title="Your notices"><span>Notices (0)</span></a></li>
			<li id="pt-watchlist-2" class="user-links-collapsible-item mw-list-item"><a href="/wiki/Special:Watchlist" class="mw-ui-button mw-ui-quiet mw-ui-icon mw-ui-icon-element mw-ui-icon-watchlist mw-ui-icon-wikimedia-watchlist" title="A list of pages you are monitoring for changes [⌃⌥l]" accesskey="l"><span>Watchlist</span></a></li>
		`
	},
	'data-user-menu': {
		id: 'p-personal',
		class: 'mw-portlet mw-portlet-personal vector-user-menu vector-user-menu-logged-in vector-menu-dropdown',
		label: 'Personal tools',
		'html-items': `
			<li id="pt-userpage" class="user-links-collapsible-item mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-userAvatar mw-ui-icon-wikimedia-userAvatar" href="/wiki/User:Admin" title="Your user page [.]" accesskey="."><span>Admin</span></a></li>
			<li id="pt-mytalk" class="mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-userTalk mw-ui-icon-wikimedia-userTalk" href="/wiki/User_talk:Admin" title="Your talk page [n]" accesskey="n"><span>Talk</span></a></li>
			<li id="pt-sandbox" class="new mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-sandbox mw-ui-icon-wikimedia-sandbox" href="/w/index.php?title=User:Admin/sandbox&amp;action=edit&amp;redlink=1" title="Your sandbox (page does not exist)"><span>Sandbox</span></a></li>
			<li id="pt-preferences" class="mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-settings mw-ui-icon-wikimedia-settings" href="/wiki/Special:Preferences" title="Your preferences"><span>Preferences</span></a></li>
			<li id="pt-betafeatures" class="mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-labFlask mw-ui-icon-wikimedia-labFlask" href="/wiki/Special:Preferences#mw-prefsection-betafeatures" title="Beta features"><span>Beta</span></a></li>
			<li id="pt-watchlist" class="user-links-collapsible-item mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-watchlist mw-ui-icon-wikimedia-watchlist" href="/wiki/Special:Watchlist" title="A list of pages you are monitoring for changes [l]" accesskey="l"><span>Watchlist</span></a></li>
			<li id="pt-uploads" class="mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-imageGallery mw-ui-icon-wikimedia-imageGallery" href="/w/index.php?title=Special:ListFiles/Admin&amp;ilshowall=1" title="List of files you have uploaded"><span>Uploads</span></a></li>
			<li id="pt-mycontris" class="mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-userContributions mw-ui-icon-wikimedia-userContributions" href="/wiki/Special:Contributions/Admin" title="A list of your contributions [y]" accesskey="y"><span>Contributions</span></a></li>
			<li id="pt-custom" class="mw-list-item mw-list-item-js">Gadget added item</li>
		`
	}
};

const renderedHTML = mustache.render( userLinksTemplate, templateData, {
	Menu: menuTemplate
} );

module.exports = {
	userLinksHTML: renderedHTML
};
