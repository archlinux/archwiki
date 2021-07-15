<template>
	<div id="p-search">
		<wvui-typeahead-search
			id="searchform"
			ref="searchForm"
			:client="getClient"
			:domain="domain"
			:footer-search-text="$i18n('searchsuggest-containing').escaped()"
			:suggestions-label="$i18n('searchresults').escaped()"
			:accesskey="searchAccessKey"
			:title="searchTitle"
			:placeholder="searchPlaceholder"
			:aria-label="searchPlaceholder"
			:initial-input-value="searchQuery"
			:button-label="$i18n( 'search' ).escaped()"
			:form-action="action"
			:search-language="language"
			:show-thumbnail="showThumbnail"
			:show-description="showDescription"
			@fetch-start="instrumentation.onFetchStart"
			@fetch-end="instrumentation.onFetchEnd"
			@suggestion-click="instrumentation.onSuggestionClick"
		>
			<input type="hidden"
				name="title"
				value="Special:Search"
			>
			<input type="hidden"
				name="wprov"
				:value="wprov"
			>
		</wvui-typeahead-search>
	</div>
</template>

<script>
/* global SubmitEvent */
var wvui = require( 'wvui' ),
	instrumentation = require( './instrumentation.js' );

module.exports = {
	name: 'App',
	components: wvui,
	mounted: function () {
		// access the element associated with the wvui-typeahead-search component
		// eslint-disable-next-line no-jquery/variable-pattern
		var wvuiSearchForm = this.$refs.searchForm.$el;

		if ( this.autofocusInput ) {
			// TODO: The wvui-typeahead-search component accepts an id prop but does not
			// display that value as an HTML attribute on the form element.
			wvuiSearchForm.querySelector( 'form' ).setAttribute( 'id', 'searchform' );

			// TODO: The wvui-typeahead-search component does not accept an autofocus parameter
			// or directive. This can be removed when its does.
			wvuiSearchForm.querySelector( 'input' ).focus();
		}
	},
	computed: {
		/**
		 * Allow wikis eg. Hebrew Wikipedia to replace the default search API client
		 *
		 * @return {void|Object}
		 */
		getClient: function () {
			return mw.config.get( 'wgVectorSearchClient', undefined );
		},
		language: function () {
			return mw.config.get( 'wgUserLanguage' );
		},
		domain: function () {
			// It might be helpful to allow this to be configurable in future.
			return mw.config.get( 'wgVectorSearchHost', location.host );
		}
	},
	props: {
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
		}
	},
	data: function () {
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
		onSubmit: function ( event ) {
			this.wprov = instrumentation.getWprovFromResultIndex( event.index );

			instrumentation.listeners.onSubmit( event );
		}
	}
};
</script>
