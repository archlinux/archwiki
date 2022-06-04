const Vue = require( 'vue' );
const VueTestUtils = require( '@vue/test-utils' );
const App = require( '../../resources/skins.vector.search/App.vue' );

// @ts-ignore
Vue.directive( 'i18n-html', () => {} );

const defaultProps = {
	id: 'searchform',
	searchAccessKey: 'f',
	searchTitle: 'search',
	searchPlaceholder: 'Search MediaWiki',
	searchQuery: ''
};

const mount = ( /** @type {Object} */ customProps ) => {
	// @ts-ignore
	return VueTestUtils.shallowMount( App, {
		propsData: Object.assign( {}, defaultProps, customProps ),
		mocks: {
			$i18n: ( /** @type {string} */ str ) => ( {
				text: () => str
			} )
		}
	} );
};

describe( 'App', () => {
	it( 'renders a typeahead search component', () => {
		const wrapper = mount();
		expect(
			wrapper.element
		).toMatchSnapshot();
	} );
} );
