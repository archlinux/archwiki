const Vue = require( 'vue' );
const { h } = Vue;
const MultiselectLookupLanguageSelector = require( './MultiselectLookupLanguageSelector.vue' );

function getMultiselectLookupLanguageSelector( config ) {
	const {
		selectableLanguages = null,
		selectedLanguage = null,
		menuConfig = {},
		apiUrl = null,
		placeholder = null,
		disabled = false,
		required = false,
		menuItemSlot = null,
		onLanguageChange = null,
		inputId = ''
	} = config;

	return Vue.createMwApp( {
		data() {
			return {
				inputId,
				apiUrl: apiUrl || mw.util.wikiScript( 'api' ),
				selectedLanguage,
				selectableLanguages,
				menuConfig,
				placeholder,
				disabled,
				required
			};
		},
		render() {
			return h( MultiselectLookupLanguageSelector, {
				searchApiUrl: this.apiUrl,
				selected: this.selectedLanguage,
				selectableLanguages: this.selectableLanguages,
				inputId: this.inputId,
				placeholder: this.placeholder,
				disabled: this.disabled,
				required: this.required,
				'onUpdate:selected': ( newValue ) => {
					this.selectedLanguage = newValue;
					if ( onLanguageChange ) {
						onLanguageChange( newValue );
					}
				},
				menuConfig: this.menuConfig
			}, {
				'menu-item': menuItemSlot
			} );
		}
	} );
}

module.exports = {
	getMultiselectLookupLanguageSelector
};
