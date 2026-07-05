<template>
	<cdx-field
		class="ext-checkuser-suggestedinvestigations-filter-dialog-username-filter"
	>
		<template #label>
			{{ $i18n(
				'checkuser-suggestedinvestigations-filter-dialog-username-filter-header'
			).text() }}
		</template>
		<cdx-multiselect-lookup
			v-model:input-chips="selectedUsernameChips"
			v-model:selected="computedSelectedUsernames"
			v-model:input-value="inputValue"
			:menu-items="suggestedUsernames"
			:menu-config="menuConfig"
			:placeholder="$i18n(
				'checkuser-suggestedinvestigations-filter-dialog-username-filter-placeholder'
			).text()"
			name="filter-username"
			@update:input-value="loadSuggestedUsernames"
		>
		</cdx-multiselect-lookup>
	</cdx-field>
</template>

<script>
const { ref, computed, onMounted, onUnmounted } = require( 'vue' ),
	{ CdxField, CdxMultiselectLookup } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'FilterDialogUsernameFilter',
	components: {
		CdxField,
		CdxMultiselectLookup
	},
	props: {
		/**
		 * A list of usernames that should be already selected in the field
		 * Must be bound with `v-model:selected-usernames`.
		 */
		selectedUsernames: {
			type: Array,
			required: true
		}
	},
	emits: [
		'update:selected-usernames'
	],
	setup( props, ctx ) {
		const windowHeight = ref( window.innerHeight );
		let usernameLookupDebounce = null;

		const computedSelectedUsernames = computed( {
			get: () => props.selectedUsernames,
			set: ( value ) => ctx.emit( 'update:selected-usernames', value )
		} );
		const selectedUsernameChips = ref( props.selectedUsernames.map( ( username ) => ( {
			label: username, value: username
		} ) ) );
		const suggestedUsernames = ref( [] );
		const inputValue = ref( '' );

		/**
		 * Called when the browser window is resized.
		 *
		 * This function updates the reference containing
		 * the current height of the window to adjust the
		 * number of menu items shown for the username lookup field.
		 */
		function onWindowResize() {
			windowHeight.value = window.innerHeight;
		}

		onMounted( () => {
			window.addEventListener( 'resize', onWindowResize );
		} );

		onUnmounted( () => {
			window.removeEventListener( 'resize', onWindowResize );
		} );

		/**
		 * The configuration settings for the Codex MultiLookup username component.
		 *
		 * This sets the visibleItemLimit to a proportion of the height such
		 * that the dropdown menu should not overflow the bottom of the dialog.
		 */
		const menuConfig = computed( () => ( {
			visibleItemLimit: Math.min(
				Math.max(
					Math.floor( windowHeight.value / 150 ),
					2
				),
				4
			)
		} ) );

		/**
		 * Load username suggestions for the username lookup component
		 * using the 'allusers' query API. The results are set as the
		 * suggestedUsernames reference for further use.
		 *
		 * Calling this method repeatedly is safe as the API call is
		 * debounced using a 100ms delay.
		 *
		 * @param {string} value The text the user has typed into the input field
		 */
		function loadSuggestedUsernames( value ) {
			// Clear any other yet to be run API calls to get the suggested usernames.
			clearTimeout( usernameLookupDebounce );

			// Do nothing if we have no input.
			if ( !value ) {
				suggestedUsernames.value = [];
				return;
			}

			// Debounce the API calls using a 100ms delay.
			usernameLookupDebounce = setTimeout( () => {
				new mw.Api().get( {
					action: 'query',
					list: 'allusers',
					auprefix: value,
					limit: '10'
				} ).then( ( data ) => {
					// If the return data structure is not expected or no
					// users are found, then just display no suggestions.
					if (
						!data ||
						!data.query ||
						!data.query.allusers ||
						!Array.isArray( data.query.allusers )
					) {
						suggestedUsernames.value = [];
						return;
					}

					suggestedUsernames.value = data.query.allusers.map(
						( user ) => ( { value: user.name } )
					);
				} ).catch( ( error ) => {
					suggestedUsernames.value = [];
					mw.log.error( error );
				} );
			}, 100 );
		}

		return {
			inputValue,
			selectedUsernameChips,
			computedSelectedUsernames,
			suggestedUsernames,
			menuConfig,
			windowHeight,
			loadSuggestedUsernames
		};
	},
	expose: [
		// Expose internal functions and variables used in tests in order
		// to prevent linter errors about unused properties
		'windowHeight'
	]
};
</script>
