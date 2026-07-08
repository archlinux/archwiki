<template>
	<div v-if="thumbs.length > 1" class="mmv-lightbox-nav">
		<cdx-button
			class="mmv-lightbox-nav__prev"
			:aria-label="$i18n( 'multimediaviewer-prev-image-alt-text' ).text()"
			@click="onPrev"
		>
			<cdx-icon :icon="cdxIconPrevious"></cdx-icon>
		</cdx-button>
		<span class="mmv-lightbox-nav__counter" aria-live="polite">
			{{ $i18n( 'multimediaviewer-current-image-number', currentIndex + 1, thumbs.length ).text() }}
		</span>
		<cdx-button
			class="mmv-lightbox-nav__next"
			:aria-label="$i18n( 'multimediaviewer-next-image-alt-text' ).text()"
			@click="onNext"
		>
			<cdx-icon :icon="cdxIconNext"></cdx-icon>
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent, inject, computed } = require( 'vue' );
const { CdxButton, CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconPrevious, cdxIconNext } = require( './icons.json' );

/** @typedef {import('./types').ViewerState} ViewerState */

// @vue/component
module.exports = exports = defineComponent( {
	name: 'LightboxNav',
	components: {
		CdxButton,
		CdxIcon
	},
	setup() {
		/** @type {ViewerState} */
		const state = inject( 'state' );
		const nextImageFn = inject( 'nextImage' );
		const prevImageFn = inject( 'prevImage' );

		const image = computed( () => state.image.value );
		const thumbs = computed( () => state.thumbs.value );

		const currentIndex = computed( () => {
			if ( !image.value ) {
				return 0;
			}
			const idx = thumbs.value.indexOf( image.value );
			return idx >= 0 ? idx : 0;
		} );

		function onNext() {
			nextImageFn();
		}

		function onPrev() {
			prevImageFn();
		}

		return {
			thumbs,
			currentIndex,
			onNext,
			onPrev,
			cdxIconPrevious,
			cdxIconNext
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mmv-lightbox-nav {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: @spacing-100;
	padding: @spacing-75 @spacing-100;
	flex-shrink: 0;
	border-top: solid 1px @border-color-muted;

	&__counter {
		font-size: @font-size-small;
		opacity: @opacity-icon-subtle;
	}
}
</style>
