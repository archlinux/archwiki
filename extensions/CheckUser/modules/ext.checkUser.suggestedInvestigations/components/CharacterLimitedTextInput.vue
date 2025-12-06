<template>
	<cdx-text-input
		v-bind="$attrs"
		v-model="computedTextContent"
	></cdx-text-input>
	<span
		v-if="remainingCharacters !== ''"
		class="ext-checkuser-suggestedinvestigations-dialog__reason-character-count">
		{{ remainingCharacters }}
	</span>
</template>

<script>
const { CdxTextInput } = require( '@wikimedia/codex' );
const { computed, watch } = require( 'vue' );
const { byteLength, trimByteLength } = require( 'mediawiki.String' );

// A Codex textarea with a character limit.
// @vue/component
module.exports = exports = {
	name: 'CharacterLimitedTextInput',
	components: {
		CdxTextInput
	},
	inheritAttrs: false,
	props: {
		/**
		 * The maximum number of bytes accepted by this textarea.
		 */
		byteLimit: { type: Number, required: true },
		/**
		 * The value of this text field.
		 * Must be bound with `v-model:text-content`.
		 */
		textContent: { type: String, required: true }
	},
	emits: [
		'update:text-content'
	],
	setup( props, ctx ) {
		const byteLimit = props.byteLimit;

		const computedTextContent = computed( {
			get: () => props.textContent,
			set: ( value ) => ctx.emit( 'update:text-content', value )
		} );

		const remainingCharacters = computed( () => {
			if ( computedTextContent.value === '' ) {
				return '';
			}

			const remaining = byteLimit - byteLength( computedTextContent.value );

			// Only show the character counter as the user is approaching the limit,
			// to avoid confusion stemming from our definition of a character not matching
			// the user's own expectations of what counts as a character.
			// This is consistent with other features such as VisualEditor.
			if ( remaining > 99 ) {
				return '';
			}

			return mw.language.convertNumber( remaining );
		} );

		watch( computedTextContent, () => {
			if ( byteLength( computedTextContent.value ) > byteLimit ) {
				const { newVal } = trimByteLength( '', computedTextContent.value, byteLimit );
				computedTextContent.value = newVal;
			}
		} );

		return {
			computedTextContent,
			remainingCharacters
		};
	}
};
</script>

<style lang="less">
@import ( reference ) 'mediawiki.skin.variables.less';

.ext-checkuser-suggestedinvestigations-dialog__reason-character-count {
	color: @color-subtle;
	float: right;
}
</style>
