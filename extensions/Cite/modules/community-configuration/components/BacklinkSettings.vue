<template>
	<h3>{{ $i18n( 'cite-configuration-backlink-title' ).text() }}</h3>

	<p>{{ $i18n( 'cite-configuration-backlink-description' ).text() }}</p>

	<p v-if="cldrAlphabet">
		{{ $i18n( 'cite-configuration-backlink-alpha-suggestion' ).text() }}
	</p>

	<cdx-field v-if="cldrAlphabet">
		<cdx-text-input v-model="cldrAlphabetText" readonly></cdx-text-input>
	</cdx-field>

	<cdx-field>
		<cdx-text-area
			v-model="backlinkAlphabetText"
			placeholder="a b c d e f g h i j k l m n o p q r s t u v w x y z"
			@input="newValue"
		></cdx-text-area>
		<template #label>
			{{ $i18n( 'cite-configuration-backlink-marker-label' ).text() }}
		</template>
		<template #description>
			{{ $i18n( 'cite-configuration-backlink-marker-description' ).text() }}
		</template>
		<template #help-text>
			{{ $i18n( 'cite-configuration-backlink-marker-help' ).text() }}
		</template>
	</cdx-field>
</template>

<script>

const { ref } = require( 'vue' );
const { CdxField, CdxTextInput, CdxTextArea } = require( '../codex.js' );

// @vue/component
module.exports = exports = {
	components: {
		CdxField,
		CdxTextInput,
		CdxTextArea
	},
	props: {
		backlinkAlphabet: { type: String, required: true },
		cldrAlphabet: { type: String, required: true }
	},
	emits: [ 'update' ],
	setup( props ) {
		return {
			backlinkAlphabetText: ref( props.backlinkAlphabet ),
			cldrAlphabetText: props.cldrAlphabet.join( ' ' ).toLowerCase()
		};
	},
	methods: {
		newValue() {
			this.$emit( 'update', this.backlinkAlphabetText );
		}
	}
};
</script>
