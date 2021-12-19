import template from '!!raw-loader!../includes/templates/StickyHeader.mustache';
import Button from '!!raw-loader!../includes/templates/Button.mustache';

const NO_ICON = {
	icon: 'none',
	'is-quiet': true,
	class: 'sticky-header-icon'
};

const data = {
	title: 'Audre Lorde',
	heading: 'Introduction',
	'is-visible': true,
	'data-primary-action': {
		id: 'p-lang-btn-sticky-header',
		class: 'mw-interlanguage-selector',
		'is-quiet': true,
		label: '196 languages',
		'html-vector-button-icon': `<span class="mw-ui-icon mw-ui-icon-wikimedia-language"></span>`
	},
	'data-button-start': NO_ICON,
	'data-button-end': NO_ICON,
	'data-buttons': [
		NO_ICON, NO_ICON, NO_ICON, NO_ICON
	]
};

export const STICKY_HEADER_TEMPLATE_PARTIALS = {
	Button
};

export { template, data };
