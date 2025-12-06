<template>
	<cdx-menu-button
		v-model:selected="selection"
		:aria-label="ariaLabel"
		:menu-items="menuItems"
		@update:selected="onMenuSelect"
	>
		<cdx-icon :icon="cdxIconEllipsis"></cdx-icon>
	</cdx-menu-button>
</template>

<script>
const { ref, computed } = require( 'vue' );
const { CdxMenuButton, CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconEllipsis } = require( './icons.json' );
const useWatchList = require( '../composables/useWatchList.js' );
const useInstrument = require( '../composables/useInstrument.js' );

// @vue/component
module.exports = exports = {
	name: 'UserCardMenu',
	components: { CdxMenuButton, CdxIcon },
	props: {
		username: {
			type: String,
			required: true
		},
		gender: {
			type: String,
			default: 'unknown'
		},
		ariaLabel: {
			type: String,
			default: ( props ) => mw.msg( 'checkuser-userinfocard-open-menu-aria-label', props.gender )
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
	setup( props ) {
		const selection = ref( null );
		const {
			toggleWatchList,
			watchListLabel
		} = useWatchList( props.username, props.gender, props.userPageWatched );

		// Initialize instrumentation
		const logEvent = useInstrument();
		const contributionsLink = mw.Title.makeTitle(
			-1, `Contributions/${ props.username }`
		).getUrl();
		const checkUserLink = mw.Title.makeTitle( -1, 'CheckUser' ).getUrl(
			{ user: props.username }
		);
		const blockUserLink = mw.Title.makeTitle(
			-1, `Block/${ props.username }`
		).getUrl();
		const turnOffLink = mw.Title.makeTitle(
			-1, 'Preferences'
		).getUrl() + '#mw-prefsection-rendering-advancedrendering';

		// Get permission configs
		const canViewIPAddresses = mw.config.get( 'wgCheckUserCanPerformCheckUser' );
		const canBlock = mw.config.get( 'wgCheckUserCanBlock' );

		// Computed is necessary for the watchListLabel item
		const menuItems = computed( () => {
			const items = [
				{
					label: mw.msg( 'checkuser-userinfocard-menu-view-contributions', props.gender ),
					value: 'view-contributions',
					url: contributionsLink
				}
			];

			if ( props.specialCentralAuthUrl ) {
				items.push( {
					label: mw.msg( 'checkuser-userinfocard-menu-view-global-account', props.gender ),
					value: 'view-global-account',
					url: props.specialCentralAuthUrl
				} );
			}

			items.push( {
				label: watchListLabel.value,
				value: 'toggle-watchlist'
			} );

			const showXToolsLink = mw.config.get( 'wgCheckUserUserInfoCardShowXToolsLink' );
			if ( showXToolsLink ) {
				const xToolsUrl = `https://xtools.wmcloud.org/ec/${ mw.config.get( 'wgDBname' ) }/${ encodeURIComponent( props.username ) }`;
				items.push( {
					label: mw.msg( 'checkuser-userinfocard-menu-view-xtools' ),
					value: 'view-xtools',
					url: xToolsUrl
				} );
			}

			if ( canViewIPAddresses ) {
				items.push( {
					label: mw.msg( 'checkuser-userinfocard-menu-check-ip', props.gender ),
					value: 'check-ip',
					url: checkUserLink
				} );
			}

			if ( canBlock ) {
				items.push( {
					label: mw.msg( 'checkuser-userinfocard-menu-block-user', props.gender ),
					value: 'block-user',
					url: blockUserLink
				} );
			}

			items.push( ...[
				{
					label: mw.msg( 'checkuser-userinfocard-menu-provide-feedback' ),
					value: 'provide-feedback',
					url: 'https://www.mediawiki.org/wiki/Talk:Trust_and_Safety_Product/Anti-abuse_signals/User_Info'
				},
				{
					label: mw.msg( 'checkuser-userinfocard-menu-turn-off' ),
					value: 'turn-off',
					url: turnOffLink
				}
			] );

			return items;
		} );

		function onMenuSelect( value ) {
			// Log the menu selection
			logEvent( 'link_click', {
				subType: value,
				source: 'card_menu'
			} );

			if ( value === 'toggle-watchlist' ) {
				toggleWatchList();
			}
			selection.value = null;
		}

		return {
			selection,
			menuItems,
			onMenuSelect,
			cdxIconEllipsis
		};
	}
};
</script>
