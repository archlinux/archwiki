const sticky = require( '../../resources/skins.vector.es6/stickyHeader.js' );

describe( 'sticky header', () => {
	test( 'prepareUserMenu removes gadgets from dropdown', async () => {
		const menu = document.createElement( 'div' );
		menu.innerHTML = `<input type="checkbox" id="p-personal-checkbox" role="button" aria-haspopup="true" data-event-name="ui.dropdown-p-personal" class="vector-menu-checkbox" aria-labelledby="p-personal-label" aria-expanded="true">
		<h3 id="p-personal-label" aria-label="" class="vector-menu-heading mw-ui-button mw-ui-quiet mw-ui-icon mw-ui-icon-element mw-ui-icon-wikimedia-userAvatar" aria-hidden="true">
			<span class="vector-menu-heading-label">Personal tools</span>
				<span class="vector-menu-checkbox-expanded">expanded</span>
				<span class="vector-menu-checkbox-collapsed">collapsed</span>
		</h3>
<div class="vector-menu-content">
	<ul class="vector-menu-content-list">
		<li id="pt-userpage" class="user-links-collapsible-item mw-list-item">
			<a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-userAvatar" href="/w/index.php?title=Special:Homepage&amp;source=personaltoolslink&amp;namespace=0" dir="auto" title="Your homepage [⌃⌥.]" accesskey="."><span>Jdlrobson</span></a>
		</li>
		<li id="pt-mytalk" class="mw-list-item">
			<a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-userTalk" href="/wiki/User_talk:Jdlrobson" title="Your talk page [⌃⌥n]" accesskey="n"><span>Talk</span></a>
		</li>
		<li id="pt-sandbox" class="mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-sandbox" href="/wiki/User:Jdlrobson/sandbox" title="Your sandbox"><span>Sandbox</span></a>
		</li>
		<li id="pt-preferences" class="mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-settings" href="/wiki/Special:Preferences" title="Your preferences"><span>Preferences</span></a>
		</li>
		<li id="pt-betafeatures" class="mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-labFlask" href="/wiki/Special:Preferences#mw-prefsection-betafeatures" title="Beta features"><span>Beta</span></a>
		</li>
		<li id="pt-watchlist" class="mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-watchlist" href="/wiki/Special:Watchlist" title="The list of pages you are monitoring for changes [⌃⌥L]" accesskey="L"><span>Watchlist</span></a>
		</li>
		<li id="pt-mycontris" class="mw-list-item"><a class="mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-userContributions" href="/wiki/Special:Contributions/Jdlrobson" title="A list of your contributions [⌃⌥y]" accesskey="y"><span>Contributions</span></a>
		</li>
		<li class="mw-list-item mw-list-item-js" id="cx-language"><a href="/w/index.php?title=Special:ContentTranslation&amp;campaign=contributionsmenu&amp;to=en" title="Add a new translation" class="mw-ui-icon mw-ui-icon-before mw-ui-icon-vector-gadget-cx-language"><span>Translations</span></a>
		</li>
		<li class="mw-list-item mw-list-item-js" id="cx-imageGallery"><a href="//commons.wikimedia.org/wiki/Special:MyUploads" title="A list of your uploaded media" class="mw-ui-icon mw-ui-icon-before mw-ui-icon-vector-gadget-cx-imageGallery"><span>Uploaded media</span></a></li>
		<li class="mw-list-item mw-list-item-js"><a href="Test"><span>Test</span></a></li>
	</ul>
	<div id="pt-logout" class="vector-user-menu-logout" title="Log out">
		<a data-mw="interface" href="/w/index.php?title=Special:UserLogout&amp;returnto=Main+Page&amp;returntoquery=safemode%3D1" icon="logOut" class="vector-menu-content-item vector-menu-content-item-logout mw-ui-icon mw-ui-icon-before mw-ui-icon-wikimedia-logOut"><span>Log out</span></a>
	</div>
</div>`;
		const newMenu = sticky.prepareUserMenu( menu );
		// check classes have been updated and removed.
		expect( newMenu.querySelectorAll( '.user-links-collapsible-item' ).length ).toBe( 0 );
		expect( newMenu.querySelectorAll( '.mw-list-item-js' ).length ).toBe( 0 );
	} );
} );
