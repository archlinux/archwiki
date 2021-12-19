/**
 * @external SearchData
 */

import searchBoxTemplate from '!!raw-loader!../includes/templates/SearchBox.mustache';
import Button from '!!raw-loader!../includes/templates/Button.mustache';
import { htmlUserLanguageAttributes } from './utils';

/**
 * @type {SearchData}
 */
const legacySearchBoxData = {
	'form-action': '/w/index.php',
	class: 'vector-search-box vector-search-show-thumbnail',
	'html-user-language-attributes': htmlUserLanguageAttributes,
	'msg-search': 'Search',
	'html-input': '<input type="search" name="search" placeholder="Search Wikipedia" title="Search Wikipedia [⌃⌥f]" accesskey="f" id="searchInput" autocomplete="off">',
	'page-title': 'Special:Search',
	'html-button-search-fallback': '<input type="submit" name="fulltext" value="Search" title="Search pages for this text" id="mw-searchButton" class="searchButton mw-fallbackSearchButton"/>',
	'html-button-search': '<input type="submit" name="go" value="Go" title="Go to a page with this exact name if it exists" id="searchButton" class="searchButton">'
};

/**
 * @type {SearchData}
 */
const searchBoxData = Object.assign( {}, legacySearchBoxData, {
	class: 'vector-search-box vector-search-show-thumbnail vector-search-box-collapses',
	'is-collapsible': true,
	'data-collapse-icon': {
		icon: 'wikimedia-search',
		'is-quiet': true,
		class: 'search-toggle',
		href: '/wiki/Special:Search',
		label: 'Search'
	}
} );

const SEARCH_TEMPLATE_PARTIALS = {
	Button
};

export {
	SEARCH_TEMPLATE_PARTIALS,
	searchBoxTemplate,
	legacySearchBoxData,
	searchBoxData
};
