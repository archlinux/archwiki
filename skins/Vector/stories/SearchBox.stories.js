import mustache from 'mustache';
import '../resources/skins.vector.styles/SearchBox.less';
import '../resources/skins.vector.styles/layouts/screen.less';
import { searchBoxData, legacySearchBoxData, searchBoxTemplate,
	SEARCH_TEMPLATE_PARTIALS
} from './SearchBox.stories.data';

export default {
	title: 'SearchBox'
};

export const legacySimpleSearch = () => `
	${mustache.render( searchBoxTemplate, legacySearchBoxData, SEARCH_TEMPLATE_PARTIALS )}
`;

export const simpleSearch = () => `
	${mustache.render( searchBoxTemplate, searchBoxData, SEARCH_TEMPLATE_PARTIALS )}
`;
