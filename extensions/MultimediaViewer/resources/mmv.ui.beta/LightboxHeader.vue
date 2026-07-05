<template>
	<div class="mmv-lightbox-header">
		<div v-if="image" class="mmv-lightbox-header__text">
			<div id="mmv-lightbox-title" class="mmv-lightbox-header__title">
				{{ imageTitle }}
			</div>
			<div class="mmv-lightbox-header__author">
				{{ author }}
			</div>
		</div>
	</div>
</template>

<script>
const { defineComponent, inject, computed } = require( 'vue' );
const { HtmlUtils } = require( 'mmv.common' );

/** @typedef {import('./types').ViewerState} ViewerState */

// @vue/component
module.exports = exports = defineComponent( {
	name: 'LightboxHeader',
	setup() {
		/** @type {ViewerState} */
		const state = inject( 'state' );
		const image = computed( () => state.image.value );
		const imageInfo = computed( () => state.imageInfo.value );

		const imageTitle = computed( () => {
			if ( image.value && image.value.filePageTitle ) {
				return image.value.filePageTitle.getNameText();
			}
			return '';
		} );

		const author = computed( () => {
			if ( imageInfo.value && imageInfo.value.author ) {
				return HtmlUtils.htmlToText( imageInfo.value.author );
			}
			return '';
		} );

		return {
			image,
			imageTitle,
			author
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mmv-lightbox-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	padding: @spacing-75 @spacing-100;
	// Reserve space on the right for the absolutely-positioned close button.
	padding-right: calc( @spacing-100 + @size-200 + @spacing-75 );
	flex-shrink: 0;

	&__text {
		flex: 1;
		min-width: 0;
	}

	&__title {
		font-weight: @font-weight-bold;
		font-size: @font-size-medium;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}

	&__author {
		font-size: @font-size-small;
		color: @color-subtle;
		line-height: @line-height-small;
		margin-top: @spacing-25;

		// Add a zero-width space so that there is always 1 line worth of text
		// content in the author area (to prevent layout shift while the metadata
		// is loaded)
		&::before {
			content: '\200b';
		}
	}
}
</style>
