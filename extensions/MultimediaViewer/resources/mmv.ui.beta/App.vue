<template>
	<div
		v-if="isOpen"
		ref="lightbox"
		class="mmv-lightbox"
		:class="{ 'mmv-lightbox--chrome-hidden': !chromeVisible }"
		role="dialog"
		aria-modal="true"
		aria-labelledby="mmv-lightbox-title"
	>
		<div
			class="mmv-focus-sentinel"
			tabindex="0"
			@focus="onFocusTrapStart"
		></div>
		<div
			ref="focusHolder"
			class="mmv-focus-holder"
			tabindex="-1"
		></div>
		<template v-if="image">
			<lightbox-header></lightbox-header>
			<cdx-button
				class="mmv-lightbox-close"
				:aria-label="$i18n( 'multimediaviewer-close-popup-text' ).text()"
				@click="onClose"
			>
				<cdx-icon :icon="cdxIconClose"></cdx-icon>
			</cdx-button>
			<lightbox-image @click="onViewportClick"></lightbox-image>
			<lightbox-caption></lightbox-caption>
			<lightbox-nav></lightbox-nav>
		</template>
		<cdx-progress-bar
			v-else
			class="mmv-lightbox__progress"
			:aria-label="$i18n( 'multimediaviewer-loading' ).text()"
		></cdx-progress-bar>
		<div
			class="mmv-focus-sentinel"
			tabindex="0"
			@focus="onFocusTrapEnd"
		></div>
	</div>
</template>

<script>
const { defineComponent, inject, computed, useTemplateRef } = require( 'vue' );
const { CdxButton, CdxIcon, CdxProgressBar } = require( '@wikimedia/codex' );
const { cdxIconClose } = require( './icons.json' );
const LightboxHeader = require( './LightboxHeader.vue' );
const LightboxImage = require( './LightboxImage.vue' );
const LightboxCaption = require( './LightboxCaption.vue' );
const LightboxNav = require( './LightboxNav.vue' );
const { useFocusTrap } = require( './useFocusTrap.js' );

/** @typedef {import('./types').ViewerState} ViewerState */

// @vue/component
module.exports = exports = defineComponent( {
	name: 'MmvLightbox',
	components: {
		CdxButton,
		CdxIcon,
		CdxProgressBar,
		LightboxHeader,
		LightboxImage,
		LightboxCaption,
		LightboxNav
	},
	setup() {
		/** @type {ViewerState} */
		const state = inject( 'state' );
		const toggleChromeFn = inject( 'toggleChrome' );
		const closeFn = inject( 'close' );

		const lightboxRef = useTemplateRef( 'lightbox' );
		const focusHolderRef = useTemplateRef( 'focusHolder' );
		const isOpen = computed( () => state.isOpen.value );
		const image = computed( () => state.image.value );
		const chromeVisible = computed( () => state.chromeVisible.value );

		const { onFocusTrapStart, onFocusTrapEnd } = useFocusTrap(
			lightboxRef,
			isOpen,
			{ holderRef: focusHolderRef }
		);

		function onViewportClick( e ) {
			if ( e.target.closest( 'a, button, [role="button"]' ) ) {
				return;
			}
			toggleChromeFn();
		}

		function onClose() {
			closeFn();
		}

		return {
			isOpen,
			image,
			chromeVisible,
			onViewportClick,
			onClose,
			cdxIconClose,
			onFocusTrapStart,
			onFocusTrapEnd
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import ( reference ) 'mediawiki.skin.codex-design-tokens/theme-wikimedia-ui-mixin-dark.less';

.mmv-lightbox {
	.cdx-mode-dark();

	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	// Stack above .mw-mmv-overlay (z-index: 1000) which the bootstrap
	// re-appends on each viewer open, potentially after our mount point.
	z-index: calc( 1000 + @z-index-stacking-1 );
	display: flex;
	flex-direction: column;
	background-color: @background-color-interactive-subtle;
	color: @color-inverted-fixed;

	.mmv-focus-sentinel,
	.mmv-focus-holder {
		position: absolute;
		width: 0;
		height: 0;
		overflow: hidden;
	}

	&__progress {
		max-width: 80vw;
		min-width: 20vw;
		width: 20rem;
		margin: auto;
	}

	.mmv-lightbox-close {
		position: absolute;
		top: @spacing-75;
		right: @spacing-100;
		z-index: 2;
	}

	.mmv-lightbox-header,
	.mmv-lightbox-caption,
	.mmv-lightbox-nav {
		transition: opacity 0.2s ease, visibility 0.2s ease;
	}

	&--chrome-hidden {
		.mmv-lightbox-header,
		.mmv-lightbox-caption,
		.mmv-lightbox-nav {
			opacity: 0;
			visibility: hidden;
			pointer-events: none;
		}
	}
}
</style>
