<template>
	<!-- Focus trap start -->
	<div
		tabindex="0"
		@focus="restoreFocus"
	></div>
	<cdx-popover
		ref="popoverRef"
		:key="username"
		v-model:open="isOpen"
		:anchor="currentTrigger"
		placement="bottom-start"
		:render-in-place="true"
		class="ext-checkuser-userinfocard-popover"
		role="dialog"
		aria-labelledby="ext-checkuser-userinfocard-button"
	>
		<template #header>
			<!-- Container for teleported header content -->
			<div
				v-if="isOpen"
				ref="headerContainer"
				class="ext-checkuser-userinfocard-header-container"
			></div>
		</template>

		<!-- Container for teleported body content -->
		<div
			v-if="isOpen"
			ref="bodyContainer"
			class="ext-checkuser-userinfocard-body-container"
		></div>
	</cdx-popover>
	<!-- Focus trap end -->
	<div
		tabindex="0"
		@focus="restoreFocus"
	></div>

	<!--
		Separate cached component, visually attached to popover.
		CdxPopover mounts/destroys child component, so caching with <keep-alive>
		only works when the component is mounted outside the popover.
	-->
	<keep-alive>
		<user-card-view
			v-if="isOpen"
			:key="componentKey"
			:username="username"
			:header-container="headerContainer"
			:body-container="bodyContainer"
			@close="close"
		></user-card-view>
	</keep-alive>
</template>

<script>
const { ref, computed, watch } = require( 'vue' );
const { CdxPopover } = require( '@wikimedia/codex' );
const { hashUsername } = require( '../util.js' );
const UserCardView = require( './UserCardView.vue' );
const useInstrument = require( '../composables/useInstrument.js' );

// @vue/component
module.exports = exports = {
	name: 'App',
	components: {
		CdxPopover,
		UserCardView
	},
	setup() {
		const isOpen = ref( false );
		const currentTrigger = ref( null );
		const username = ref( null );
		const headerContainer = ref( null );
		const bodyContainer = ref( null );
		const popoverRef = ref( null );
		let previouslyFocused = null;

		// Initialize instrumentation
		const logEvent = useInstrument();

		function open( target ) {
			currentTrigger.value = target;
			isOpen.value = true;
			logEvent( 'open', { source: 'button' } );
		}

		function close() {
			isOpen.value = false;
			currentTrigger.value = null;
			logEvent( 'close', { source: 'button' } );
		}

		function setUserInfo( newUsername ) {
			username.value = newUsername;
		}

		// Expose this function so init.js can see if the popover is open
		function isPopoverOpen() {
			return isOpen.value;
		}

		/**
		 * Returns a key to be used to identify the component that serves to
		 * ensure the component is cached when the user changes.
		 */
		const componentKey = computed(
			() => hashUsername( username.value ) || 'default'
		);

		watch( isOpen, ( dialogOpen ) => {
			if ( dialogOpen ) {
				// Stash the currently focused element so we can restore it later.
				previouslyFocused = document.activeElement;
			}
		} );

		const restoreFocus = () => {
			// Restore focus to the previously-focused element, if there was one
			// (and if it still exists in the document).
			if (
				previouslyFocused instanceof HTMLElement &&
				document.contains( previouslyFocused )
			) {
				previouslyFocused.focus();
				previouslyFocused = null;
				close();
			}
		};

		return {
			isOpen,
			currentTrigger,
			username,
			headerContainer,
			bodyContainer,
			popoverRef,
			open,
			close,
			setUserInfo,
			isPopoverOpen,
			componentKey,
			restoreFocus
		};
	},
	expose: [
		'open',
		'close',
		'setUserInfo',
		'isPopoverOpen'
	]
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-popover {
	// Don't add max-width here as CdxPopover uses it for controlling the card going
	// outside the view window
	width: @size-2400;
	padding-bottom: @spacing-0;
}

.ext-checkuser-userinfocard-header-container,
.ext-checkuser-userinfocard-body-container {
	display: contents;
}
</style>
