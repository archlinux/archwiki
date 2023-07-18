import pageToolbarTemplate from '!!raw-loader!../includes/templates/PageToolbar.mustache';
import { namespaceTabsData, pageActionsData } from './MenuTabs.stories.data';
import { menuTemplate } from './Menu.stories.data';

const PAGE_TOOLBAR_TEMPLATE_DATA = {
	'data-portlets': {
		'data-views': pageActionsData,
		'data-namespaces': namespaceTabsData
	}
};

const PAGE_TOOLBAR_TEMPLATE_DATA_LEGACY = {
	'data-portlets': {
		'data-views': Object.assign( {}, pageActionsData, {
			class: 'vector-menu-tabs-legacy'
		} ),
		'data-namespaces': Object.assign( {}, namespaceTabsData, {
			class: 'vector-menu-tabs-legacy'
		} )
	}
};

const PAGE_TOOLBAR_PARTIALS = {
	Menu: menuTemplate
};

export { pageToolbarTemplate, PAGE_TOOLBAR_TEMPLATE_DATA,
	PAGE_TOOLBAR_TEMPLATE_DATA_LEGACY, PAGE_TOOLBAR_PARTIALS };
