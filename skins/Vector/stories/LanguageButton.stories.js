import mustache from 'mustache';
import '../resources/skins.vector.styles/LanguageButton.less';
import { vectorMenuTemplate } from './MenuDropdown.stories.data';
import { languageData } from './LanguageButton.stories.data';

export default {
	title: 'LanguageButton'
};

// mw-page-container is needed to enable the 20x20 icon
// mw-body-header can be removed when VectorLanguageInHeader is true and
// old language inside portal in modern Vector is no longer supported.
const CONTAINER_CLASS_MODERN_VECTOR = 'mw-body-header mw-page-container';

/**
 * It allows us to support old and new renderings.
 *
 * @param {string|HTMLElement} htmlOrElement
 * @param {string} className of containing element
 * @return {HTMLElement}
 */
const wrapLanguageButton = ( htmlOrElement, className ) => {
	const node = document.createElement( 'div' );
	node.setAttribute( 'class', className );
	if ( typeof htmlOrElement === 'string' ) {
		node.innerHTML = htmlOrElement;
	} else {
		node.appendChild( htmlOrElement );
	}
	return node;
};

export const languageButton = () => mustache.render( vectorMenuTemplate, languageData );

export const languageButtonWhenULSEnabled = () => wrapLanguageButton(
	wrapLanguageButton(
		wrapLanguageButton(
			mustache.render( vectorMenuTemplate, languageData ),
			'vector-menu--hide-dropdown'
		),
		CONTAINER_CLASS_MODERN_VECTOR
	),
	'client-js'
);
