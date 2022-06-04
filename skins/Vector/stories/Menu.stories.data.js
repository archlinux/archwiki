import menuTemplate from '!!raw-loader!../includes/templates/Menu.mustache';
import { helperMakeMenuData } from './utils';

/**
 * @type {MenuDefinition}
 */
const defaultMenu = helperMakeMenuData(
	'generic',
	`<li><a href='#'>Item 1</a></li>
<li><a href='#'>Item 2</a></li>
<li><a href='#'>Item 3</a></li>`
);

export { menuTemplate, defaultMenu };
