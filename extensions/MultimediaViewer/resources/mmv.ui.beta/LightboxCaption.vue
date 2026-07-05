<template>
	<div v-if="image" class="mmv-lightbox-caption">
		<div
			v-if="image.caption"
			class="mmv-lightbox-caption__text"
			v-html="captionHtml"
		></div>
		<a
			class="mmv-lightbox-caption__link"
			:href="filePageUrl"
			:title="$i18n( 'multimediaviewer-file-page' ).text()"
		>
			{{ licenseText }}
		</a>
	</div>
</template>

<script>
const { defineComponent, inject, computed } = require( 'vue' );
const { HtmlUtils } = require( 'mmv.common' );

/** @typedef {import('./types').ViewerState} ViewerState */

// @vue/component
module.exports = exports = defineComponent( {
	name: 'LightboxCaption',
	setup() {
		/** @type {ViewerState} */
		const state = inject( 'state' );

		const image = computed( () => state.image.value );
		const imageInfo = computed( () => state.imageInfo.value );

		const captionHtml = computed( () => {
			if ( image.value && image.value.caption ) {
				return HtmlUtils.htmlToTextWithTags( image.value.caption );
			}
			return '';
		} );

		const filePageUrl = computed( () => {
			if ( image.value && image.value.filePageTitle ) {
				return image.value.filePageTitle.getUrl();
			}
			return '';
		} );

		const licenseText = computed( () => {
			if ( imageInfo.value && imageInfo.value.license ) {
				return imageInfo.value.license.getShortName();
			}
			return mw.msg( 'multimediaviewer-description-page-button-text' );
		} );

		return {
			image,
			captionHtml,
			filePageUrl,
			licenseText
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mmv-lightbox-caption {
	flex-shrink: 0;
	padding: @spacing-75 @spacing-100;

	&__text {
		font-size: @font-size-small;
		opacity: @opacity-icon-base;
		margin-bottom: @spacing-50;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}

	&__link {
		font-size: @font-size-small;
	}
}
</style>
