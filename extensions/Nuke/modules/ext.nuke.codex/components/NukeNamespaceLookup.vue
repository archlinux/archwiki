<template>
	<cdx-field :status="status" :messages="messages">
		<cdx-multiselect-lookup
			id="nuke-namespace-lookup"
			v-model:input-chips="chips"
			v-model:selected="selection"
			v-model:input-value="inputValue"
			:menu-items="menuItems"
			:menu-config="menuConfig"
			:placeholder="$i18n( 'mw-widgets-titlesmultiselect-placeholder' ).text()"
			@input="onInput"
			@update:selected="onSelection"
			@blur="validateInstantly"
			@keydown.enter="validateInstantly"
		></cdx-multiselect-lookup>
		<template #label>
			{{ $i18n( 'nuke-namespace' ).text() }}
		</template>
	</cdx-field>
	<textarea
		class="ext-nuke-form-hidden"
		name="namespace"
		:value="chips.map( chip => chip.value ).join( '\n' )"></textarea>
</template>

<script>
// `mediawiki.api` does not have exports. Instead, it sets the `mw.Api` global.
require( 'mediawiki.api' );
const { defineComponent, onMounted, ref, nextTick } = require( 'vue' );
const { CdxField, CdxMultiselectLookup } = require( '../codex.js' );

module.exports = exports = defineComponent( {
	name: 'NukeNamespaceLookup',
	components: {
		CdxField, CdxMultiselectLookup
	},
	props: {
		namespaces: {
			type: String,
			required: false,
			default: ''
		}
	},
	setup( props ) {
		const chips = ref( [] );
		const selection = ref( [] );
		const inputValue = ref( '' );

		const namespaces = ref( [] );
		const menuItems = ref( [] );

		const menuConfig = {
			visibleItemLimit: 6
		};

		const status = ref( 'default' );

		const messages = {
			warning: mw.msg( 'nuke-namespace-invalid' ),
			error: mw.msg( 'nuke-namespace-invalid' )
		};

		/**
		 * Maybe set a warning message when the user moves out of the field or hits enter.
		 */
		function validateInstantly() {
			// Await nextTick in case the user has selected a menu item via the Enter key - this
			// will ensure the selection ref has been updated.
			nextTick( () => {
				// Set warning status if there's input. This might happen if a user types something
				// but doesn't select an item from the menu.
				status.value = inputValue.value.length > 0 ? 'warning' : 'default';
			} );
		}

		/**
		 * Clear warning or error after a selection is made.
		 */
		function onSelection() {
			if ( selection.value !== null ) {
				status.value = 'default';
			}
		}

		/**
		 * Handle lookup input.
		 *
		 * @param {string} value The new input value
		 */
		function onInput( value ) {
			// Reset menu items if the input was cleared.
			if ( !value ) {
				menuItems.value = namespaces.value;
				return;
			}

			// Make sure this data is still relevant first.
			if ( inputValue.value !== value ) {
				return;
			}

			// Update menuItems.
			menuItems.value = namespaces.value.filter(
				( namespace ) => namespace.label
					.toLocaleLowerCase()
					.includes( value.toLocaleLowerCase() )
			);
		}

		/**
		 * Get a list of namespaces.
		 *
		 * @return {Promise}
		 */
		function getNamespaces() {
			return ( new mw.Api() ).get( {
				action: 'query',
				meta: 'siteinfo',
				siprop: 'namespaces'
			} );
		}

		function formatData( namespaceData ) {
			const formattedData = [
				// Main namespace
				{
					value: 0,
					label: mw.msg( 'blanknamespace' )
				}
			];
			for ( const namespaceId of Object.keys( namespaceData ) ) {
				// Only select namespaces that have an ID > 0.
				// This will exclude the main namespace, and any virtual namespaces we have.
				if ( 'canonical' in namespaceData[ namespaceId ] && +namespaceId > 0 ) {
					formattedData.push( {
						value: parseInt( namespaceId ),
						label: namespaceData[ namespaceId ].canonical
					} );
				}
			}
			return formattedData;
		}

		onMounted( () => {
			getNamespaces()
				.then( ( data ) => {
					// Store formatted namespaces.
					namespaces.value = formatData( data.query.namespaces );
					// Set initial menu items.
					menuItems.value = namespaces.value;

					// Select namespaces based on existing input data.
					const selectedNamespaces = props.namespaces.split( '\n' )
						.map( ( namespaceId ) => parseInt( namespaceId.trim() ) );
					chips.value = namespaces.value.filter(
						( namespace ) => selectedNamespaces.includes( namespace.value )
					);
					selection.value = chips.value.map( ( v ) => v.value );
				} );
		} );

		return {
			chips,
			selection,
			inputValue,
			menuItems,
			menuConfig,
			status,
			messages,
			validateInstantly,
			onSelection,
			onInput
		};
	}
} );
</script>
