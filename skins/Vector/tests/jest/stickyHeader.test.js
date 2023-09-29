const mustache = require( 'mustache' );
const fs = require( 'fs' );
const stickyHeaderTemplate = fs.readFileSync( 'includes/templates/StickyHeader.mustache', 'utf8' );
const buttonTemplate = fs.readFileSync( 'includes/templates/Button.mustache', 'utf8' );
const sticky = require( '../../resources/skins.vector.es6/stickyHeader.js' );
const { userLinksHTML, dropdownPartials } = require( './userLinksData.js' );

const defaultButtonsTemplateData = [ {
	href: '#',
	id: 'ca-talk-sticky-header',
	event: 'talk-sticky-header',
	icon: 'wikimedia-speechBubbles',
	'is-quiet': true,
	'tab-index': '-1',
	class: 'sticky-header-icon'
}, {
	href: '#',
	id: 'ca-history-sticky-header',
	event: 'history-sticky-header',
	icon: 'wikimedia-history',
	'is-quiet': true,
	'tab-index': '-1',
	class: 'sticky-header-icon'
}, {
	href: '#',
	id: 'ca-watchstar-sticky-header',
	event: 'watch-sticky-header',
	icon: 'wikimedia-star',
	'is-quiet': true,
	'tab-index': '-1',
	class: 'sticky-header-icon mw-watchlink'
} ];

const editButtonsTemplateData = [ {
	href: '#',
	id: 'ca-ve-edit-sticky-header',
	event: 've-edit-sticky-header',
	icon: 'wikimedia-edit',
	'is-quiet': true,
	'tab-index': '-1',
	class: 'sticky-header-icon'
}, {
	href: '#',
	id: 'ca-edit-sticky-header',
	event: 'wikitext-edit-sticky-header',
	icon: 'wikimedia-wikiText',
	'is-quiet': true,
	'tab-index': '-1',
	class: 'sticky-header-icon'
}, {
	href: '#',
	id: 'ca-viewsource-sticky-header',
	event: 'wikimedia-editLock',
	icon: 'wikimedia-star',
	'is-quiet': true,
	'tab-index': '-1',
	class: 'sticky-header-icon'
} ];

const templateData = {
	'data-toc': {
		'array-sections': []
	},
	'data-sticky-header-toc-dropdown': {
		id: 'vector-sticky-header-toc',
		class: 'mw-portlet mw-portlet-sticky-header-toc vector-sticky-header-toc',
		'html-items': '',
		'html-vector-menu-checkbox-attributes': 'tabindex="-1"',
		'html-vector-menu-heading-attributes': 'tabindex="-1"',
		'heading-class': 'mw-ui-button mw-ui-quiet mw-ui-icon mw-ui-icon-element mw-ui-icon-wikimedia-listBullet'
	},
	'data-buttons': [ {
		id: 'p-lang-btn-sticky-header',
		class: 'mw-interlanguage-selector',
		'is-quiet': true,
		tabindex: '-1',
		label: '0 languages',
		'html-vector-button-icon': '<span class="mw-ui-icon mw-ui-icon-wikimedia-language"></span>',
		event: 'ui.dropdown-p-lang-btn-sticky-header'
	} ],
	'data-button-start': {
		label: 'search',
		icon: 'wikimedia-search',
		'is-quiet': true,
		tabindex: '-1',
		class: 'vector-sticky-header-search-toggle',
		event: 'ui.vector-sticky-search-form.icon'
	},
	'data-search': {},
	'data-icons': defaultButtonsTemplateData.concat( editButtonsTemplateData )
};

const renderedHTML = mustache.render(
	stickyHeaderTemplate, templateData, Object.assign( {}, dropdownPartials, {
		Button: buttonTemplate,
		SearchBox: '<div> </div>' // ignore SearchBox for this test
	} ) );

beforeEach( () => {
	document.body.innerHTML = renderedHTML;
} );

test( 'Sticky header renders', () => {
	expect( document.body.innerHTML ).toMatchSnapshot();
} );

describe( 'sticky header', () => {
	test( 'prepareUserLinksDropdown removes gadgets from dropdown', async () => {
		const menu = document.createElement( 'div' );
		menu.innerHTML = userLinksHTML;
		const userLinksDropdown = /** @type {Element} */ ( menu.querySelector( '#' + sticky.USER_LINKS_DROPDOWN_ID ) );
		const newMenu = sticky.prepareUserLinksDropdown( userLinksDropdown );
		// check classes have been updated and removed.
		expect( userLinksDropdown.querySelectorAll( '.user-links-collapsible-item' ).length > 0 ).toBeTruthy();
		expect( userLinksDropdown.querySelectorAll( '.mw-list-item-js' ).length > 0 ).toBeTruthy();
		expect( newMenu.querySelectorAll( '.user-links-collapsible-item' ).length ).toBe( 0 );
		expect( newMenu.querySelectorAll( '.mw-list-item-js' ).length ).toBe( 0 );
	} );
} );
