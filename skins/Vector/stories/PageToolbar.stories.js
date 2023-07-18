import mustache from 'mustache';
import { pageToolbarTemplate,
	PAGE_TOOLBAR_TEMPLATE_DATA,
	PAGE_TOOLBAR_PARTIALS } from './PageToolbar.stories.data';
import '../resources/skins.vector.styles/components/PageToolbar.less';

export default {
	title: 'PageToolbar'
};

export const defaultState = () => mustache.render(
	pageToolbarTemplate,
	PAGE_TOOLBAR_TEMPLATE_DATA,
	PAGE_TOOLBAR_PARTIALS
);
