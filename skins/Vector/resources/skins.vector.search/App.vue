<template>
	<wvui-typeahead-search
		:id="id"
		ref="searchForm"
		:client="getClient"
		:domain="domain"
		:suggestions-label="$i18n( 'searchresults' ).text()"
		:accesskey="searchAccessKey"
		:title="searchTitle"
		:article-path="articlePath"
		:placeholder="searchPlaceholder"
		:aria-label="searchPlaceholder"
		:search-page-title="searchPageTitle"
		:initial-input-value="searchQuery"
		:button-label="$i18n( 'searchbutton' ).text()"
		:form-action="action"
		:search-language="language"
		:show-thumbnail="showThumbnail"
		:show-description="showDescription"
		:highlight-query="highlightQuery"
		:auto-expand-width="autoExpandWidth"
		@fetch-start="instrumentation.onFetchStart"
		@fetch-end="instrumentation.onFetchEnd"
		@suggestion-click="instrumentation.onSuggestionClick"
		@submit="onSubmit"
	>
		<template #default>
			<input type="hidden"
				name="title"
				:value="searchPageTitle"
			>
			<input type="hidden"
				name="wprov"
				:value="wprov"
			>
		</template>
		<template #search-footer-text="{ searchQuery }">
			<span v-i18n-html:vector-searchsuggest-containing="[ searchQuery ]"></span>
		</template>
	</wvui-typeahead-search>
</template>

<script>
/* global SubmitEvent */
const wvui = require( 'wvui-search' ),
	client = require( './restSearchClient.js' ),
	instrumentation = require( './instrumentation.js' );

module.exports = {
	name: 'App',
	components: wvui,
	mounted() {
		// access the element associated with the wvui-typeahead-search component
		// eslint-disable-next-line no-jquery/variable-pattern
		const wvuiSearchForm = this.$refs.searchForm.$el;

		if ( this.autofocusInput ) {
			// TODO: The wvui-typeahead-search component does not accept an autofocus parameter
			// or directive. This can be removed when its does.
			wvuiSearchForm.querySelector( 'input' ).focus();
		}
	},
	computed: {
		/**
		 * @return {string}
		 */
		articlePath: () => mw.config.get( 'wgScript' ),
		/**
		 * Allow wikis eg. Hebrew Wikipedia to replace the default search API client
		 *
		 * @return {module:restSearchClient~SearchClient}
		 */
		getClient: () => {
			return client( mw.config );
		},
		language: () => {
			return mw.config.get( 'wgUserLanguage' );
		},
		domain: () => {
			// It might be helpful to allow this to be configurable in future.
			return mw.config.get( 'wgVectorSearchHost', location.host );
		}
	},
	props: {
		id: {
			type: String,
			required: true
		},
		searchPageTitle: {
			type: String,
			default: 'Special:Search'
		},
		autofocusInput: {
			type: Boolean,
			default: false
		},
		action: {
			type: String,
			default: ''
		},
		/** The keyboard shortcut to focus search. */
		searchAccessKey: {
			type: String
		},
		/** The access key informational tip for search. */
		searchTitle: {
			type: String
		},
		/** The ghost text shown when no search query is entered. */
		searchPlaceholder: {
			type: String
		},
		/**
		 * The search query string taken from the server-side rendered input immediately before
		 * client render.
		 */
		searchQuery: {
			type: String
		},
		showThumbnail: {
			type: Boolean,
			default: true
		},
		showDescription: {
			type: Boolean,
			default: true
		},
		highlightQuery: {
			type: Boolean,
			default: true
		},
		autoExpandWidth: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			// -1 here is the default "active suggestion index" defined in the
			// `wvui-typeahead-search` component (see
			// https://gerrit.wikimedia.org/r/plugins/gitiles/wvui/+/c7af5d6d091ffb3beb4fd2723fdf50dc6bb2789b/src/components/typeahead-search/TypeaheadSearch.vue#167).
			wprov: instrumentation.getWprovFromResultIndex( -1 ),

			instrumentation: instrumentation.listeners
		};
	},
	methods: {
		/**
		 * @param {SubmitEvent} event
		 */
		onSubmit( event ) {
			this.wprov = instrumentation.getWprovFromResultIndex( event.index );

			instrumentation.listeners.onSubmit( event );
		}
	}
};
</script>
