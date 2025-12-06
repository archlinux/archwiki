<template>
	<cdx-field id="nuke-target-lookup" :messages="messages">
		<cdx-lookup
			v-model:selected="selection"
			v-model:input-value="inputValue"
			:menu-items="menuItems"
			:menu-config="menuConfig"
			name="target"
			@update:input-value="onUpdateInputValue"
		></cdx-lookup>
		<template #label>
			{{ $i18n( 'nuke-userorip' ).text() }}
		</template>
	</cdx-field>
</template>

<script>
// `mediawiki.api` does not have exports. Instead, it sets the `mw.Api` global.
require( 'mediawiki.api' );
require( 'mediawiki.util' );
const { defineComponent, ref } = require( 'vue' );
const { CdxField, CdxLookup } = require( '../codex.js' );

module.exports = exports = defineComponent( {
	name: 'NukeTargetLookup',
	components: {
		CdxField, CdxLookup
	},
	props: {
		target: {
			type: String,
			required: false,
			default: ''
		}
	},
	setup( props ) {
		const api = ref( new mw.Api() );

		const selection = ref( null );
		const inputValue = ref( props.target );
		const menuItems = ref( [] );

		const menuConfig = {
			visibleItemLimit: 10
		};

		const messages = {};

		/**
		 * Get search results.
		 *
		 * @param {string} searchTerm
		 *
		 * @return {Promise}
		 */
		function fetchResults( searchTerm ) {
			return api.value.get( {
				action: 'query',
				list: 'allusers',
				auprefix: searchTerm,
				aulimit: 10
			} );
		}

		/**
		 * Handle lookup input.
		 *
		 * @param {string} value
		 */
		function onUpdateInputValue( value ) {
			// Clear menu items if there is no input.
			if ( !value ) {
				menuItems.value = [];
				return;
			}

			fetchResults( value )
				.then( ( data ) => {
					// Make sure this data is still relevant first.
					if ( inputValue.value !== value ) {
						return;
					}

					// Reset the menu items if there are no results.
					if ( !data.query || !data.query.allusers || data.query.allusers.length === 0 ) {
						menuItems.value = [];
						return;
					}

					menuItems.value = data.query.allusers.map( ( result ) => ( {
						label: result.name,
						value: result.name
					} ) );
				} )
				.catch( () => {
					menuItems.value = [];
				} );
		}

		return {
			selection,
			messages,
			inputValue,
			menuItems,
			menuConfig,
			onUpdateInputValue: mw.util.debounce( onUpdateInputValue, 100 )
		};
	}
} );
</script>
