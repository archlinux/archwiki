<template>
	<div class="ext-checkuser-userinfocard-header">
		<div class="ext-checkuser-userinfocard-header-main">
			<cdx-icon :icon="cdxIconUserAvatar"></cdx-icon>
			<div class="ext-checkuser-userinfocard-header-userinfo">
				<span
					ref="focusTrapRef"
					tabindex="-1"></span>
				<div class="ext-checkuser-userinfocard-header-username">
					<a
						:href="userPageUrl"
						:class="[ userPageIsKnown ? 'mw-userlink' : 'new' ]"
						@click="onUsernameClick"
					>{{ username }}</a>
				</div>
			</div>
		</div>
		<div class="ext-checkuser-userinfocard-header-controls">
			<user-card-menu
				:username="username"
				:user-page-watched="userPageWatched"
				:special-central-auth-url="specialCentralAuthUrl"
				:gender="gender"
			></user-card-menu>
			<cdx-button
				:aria-label="closeAriaLabel"
				weight="quiet"
				@click="$emit( 'close' )"
			>
				<cdx-icon :icon="cdxIconClose"></cdx-icon>
			</cdx-button>
		</div>
	</div>
</template>

<script>
const { ref, onActivated, onMounted, nextTick } = require( 'vue' );
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const UserCardMenu = require( './UserCardMenu.vue' );
const { cdxIconUserAvatar, cdxIconClose } = require( './icons.json' );
const useInstrument = require( '../composables/useInstrument.js' );

// @vue/component
module.exports = exports = {
	name: 'UserCardHeader',
	components: {
		CdxIcon,
		CdxButton,
		UserCardMenu
	},
	props: {
		username: {
			type: String,
			required: true
		},
		gender: {
			type: String,
			default: 'unknown'
		},
		userPageUrl: {
			type: String,
			required: true
		},
		userPageIsKnown: {
			type: Boolean,
			required: true
		},
		userPageWatched: {
			type: Boolean,
			default: false
		},
		specialCentralAuthUrl: {
			type: String,
			default: ''
		}
	},
	emits: [ 'close' ],
	setup() {
		const focusTrapRef = ref();
		const logEvent = useInstrument();
		const closeAriaLabel = mw.msg( 'checkuser-userinfocard-close-button-aria-label' );

		function onUsernameClick() {
			logEvent( 'link_click', {
				subType: 'user_page',
				source: 'card_header'
			} );
		}

		function focusOnRef() {
			// Wait for the DOM to update before focusing
			nextTick( () => {
				if ( focusTrapRef.value ) {
					focusTrapRef.value.focus( { preventScroll: true } );
				}
			} );
		}

		onMounted( () => {
			focusOnRef();
		} );

		onActivated( () => {
			focusOnRef();
		} );

		return {
			cdxIconUserAvatar,
			cdxIconClose,
			closeAriaLabel,
			onUsernameClick,
			focusTrapRef
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-header {
	width: @size-full;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
}

.ext-checkuser-userinfocard-header-main {
	display: flex;
	align-items: flex-start;
	gap: @spacing-50;
	flex: 1;
	min-width: 0;

	/* stylelint-disable-next-line selector-class-pattern */
	.cdx-icon {
		height: @size-200;
	}
}

.ext-checkuser-userinfocard-header-userinfo {
	display: flex;
	align-items: flex-start;
	align-self: center;
	gap: @spacing-25;
	flex: 1;
	min-width: 0;
}

.ext-checkuser-userinfocard-header-username {
	margin: @spacing-0;
	font-weight: @font-weight-bold;
	line-height: @line-height-small;
	word-break: break-word;
	overflow-wrap: break-word;
	flex: 1;
	min-width: 0;
}

.ext-checkuser-userinfocard-header-controls {
	display: flex;
	align-items: flex-start;
	gap: @spacing-25;
}
</style>
