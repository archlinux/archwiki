<template>
	<div class="mmv-lightbox-image">
		<img
			v-if="image"
			class="mmv-lightbox-image__el"
			:src="displayUrl"
			:width="imageWidth"
			:height="imageHeight"
			:alt="image.alt || ''"
		>
	</div>
</template>

<script>
const { defineComponent, inject, computed } = require( 'vue' );

/** @typedef {import('./types').ViewerState} ViewerState */

// @vue/component
module.exports = exports = defineComponent( {
	name: 'LightboxImage',
	setup() {
		/** @type {ViewerState} */
		const state = inject( 'state' );

		const image = computed( () => state.image.value );
		const displayUrl = computed( () => state.displayUrl.value );

		const imageWidth = computed( () => {
			if ( image.value && image.value.originalWidth &&
				!isNaN( image.value.originalWidth ) ) {
				return image.value.originalWidth;
			}
			return undefined;
		} );
		const imageHeight = computed( () => {
			if ( image.value && image.value.originalHeight &&
				!isNaN( image.value.originalHeight ) ) {
				return image.value.originalHeight;
			}
			return undefined;
		} );

		return {
			image,
			displayUrl,
			imageWidth,
			imageHeight
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mmv-lightbox-image {
	flex: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 0;

	&__el {
		max-width: @size-full;
		max-height: @size-full;
		object-fit: contain;
	}
}
</style>
