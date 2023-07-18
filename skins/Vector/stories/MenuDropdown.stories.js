import mustache from 'mustache';
import { vectorMenuTemplate, moreData, variantsData } from './MenuDropdown.stories.data';

export default {
	title: 'MenuDropdown'
};

export const more = () => mustache.render( vectorMenuTemplate, moreData );

export const variants = () => mustache.render( vectorMenuTemplate, variantsData );
