<template>
	<div class="ext-checkuser-userinfocard-body">
		<p class="ext-checkuser-userinfocard-joined">
			{{ joined }}
		</p>
		<info-row
			v-if="globalRestrictionsLabel"
			:icon="cdxIconAlert"
			icon-class="ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks"
		>
			<!--
			Security Note: This use of v-html is considered acceptable because:
			- Props are set via internal API with no user input (from UserCardBody.vue)
			- MediaWiki messages used here do not include unescaped placeholders
			-->
			<!-- eslint-disable-next-line vue/no-v-html -->
			<span v-html="globalRestrictionsLabel"></span>
		</info-row>
		<info-row-with-links
			v-for="( row, idx ) in infoRows"
			:key="idx"
			:icon="row.icon"
			:icon-class="row.iconClass"
			:message-key="row.messageKey"
			:tooltip-key="row.tooltipKey"
			:main-value="row.mainValue"
			:main-link="row.mainLink"
			:main-link-log-id="row.mainLinkLogId"
			:suffix-value="row.suffixValue"
			:suffix-link="row.suffixLink"
			:suffix-link-log-id="row.suffixLinkLogId"
		></info-row-with-links>
		<!-- HTML derived from message parsed on server-side -->
		<!-- eslint-disable vue/no-v-html -->
		<p
			v-if="groups && groups.length > 0"
			class="ext-checkuser-userinfocard-groups"
			v-html="formattedGroups"
		>
		</p>
		<!-- HTML derived from message parsed on server-side -->
		<!-- eslint-disable vue/no-v-html -->
		<p
			v-if="globalGroups && globalGroups.length > 0"
			class="ext-checkuser-userinfocard-global-groups"
			v-html="formattedGlobalGroups"
		>
		</p>
		<!-- eslint-enable vue/no-v-html -->
		<p
			v-if="activeWikisList && activeWikisList.length > 0"
			class="ext-checkuser-userinfocard-active-wikis"
		>
			<strong>{{ activeWikisLabel }}</strong>:
			<template v-for="( wiki, idx ) in activeWikisList" :key="idx">
				<a
					:href="wiki.url"
					@click="onWikiLinkClick( wiki.wikiId )"
				>
					{{ wiki.wikiId }}
				</a>{{ idx < activeWikisList.length - 1 ? ', ' : '' }}
			</template>
		</p>
		<user-activity-chart
			v-if="hasEditInLast60Days"
			:username="username"
			:recent-local-edits="recentLocalEdits"
			:total-local-edits="totalLocalEdits"
			:last-edit-timestamp="lastEditTimestamp"
		></user-activity-chart>
		<div class="ext-checkuser-userinfocard-gradient"></div>
	</div>
</template>

<script>
const { computed } = require( 'vue' );
const {
	cdxIconAlert,
	cdxIconEdit,
	cdxIconArticles,
	cdxIconHeart,
	cdxIconSearch,
	cdxIconUserTemporary,
	cdxIconUserTemporaryLocation
} = require( './icons.json' );
const InfoRow = require( './InfoRow.vue' );
const InfoRowWithLinks = require( './InfoRowWithLinks.vue' );
const UserActivityChart = require( './UserActivityChart.vue' );
const useInstrument = require( '../composables/useInstrument.js' );

// @vue/component
module.exports = exports = {
	name: 'UserCard',
	components: { InfoRow, InfoRowWithLinks, UserActivityChart },
	props: {
		username: {
			type: String,
			default: ''
		},
		gender: {
			type: String,
			default: 'unknown'
		},
		joinedDate: {
			type: String,
			default: ''
		},
		joinedRelative: {
			type: String,
			default: ''
		},
		isRegisteredWithUnknownTime: {
			type: Boolean,
			default: false
		},
		activeBlocks: {
			type: Number,
			default: 0
		},
		pastBlocks: {
			type: Number,
			default: 0
		},
		globalEdits: {
			type: Number,
			default: 0
		},
		localEdits: {
			type: Number,
			default: 0
		},
		localEditsReverted: {
			type: Number,
			default: null
		},
		newArticles: {
			type: Number,
			default: null
		},
		thanksReceived: {
			type: Number,
			default: null
		},
		thanksSent: {
			type: Number,
			default: null
		},
		checks: {
			type: Number,
			default: 0
		},
		lastChecked: {
			type: String,
			default: ''
		},
		canAccessTemporaryAccountIpAddresses: {
			type: Boolean,
			default: false
		},
		groups: {
			type: String,
			default: ''
		},
		globalGroups: {
			type: String,
			default: ''
		},
		activeWikis: {
			// Active wikis and their URLs
			// Expected format: { [wikiId: string]: string }
			type: Object,
			default: () => ( {} )
		},
		hasEditInLast60Days: {
			type: Boolean,
			default: false
		},
		recentLocalEdits: {
			// Expected format: [ { date: Date, count: number }, ... ]
			type: Array,
			default: () => ( [] )
		},
		totalLocalEdits: {
			type: Number,
			default: 0
		},
		lastEditTimestamp: {
			type: String,
			default: ''
		},
		specialCentralAuthUrl: {
			type: String,
			default: ''
		},
		hasIpRevealInfo: {
			type: Boolean,
			default: false
		},
		numberOfIpReveals: {
			type: Number,
			default: 0
		},
		ipRevealLastCheck: {
			type: String,
			default: ''
		},
		globalRestrictions: {
			type: String,
			default: null
		},
		globalRestrictionsDate: {
			type: String,
			default: null
		},
		tempAccountsOnIpCount: {
			type: Array,
			default: () => ( [] )
		}
	},
	setup( props ) {
		const logEvent = useInstrument();

		// Users with no local registration date created their account
		// before that data was being logged. Reflect that accordingly:
		const joined = props.isRegisteredWithUnknownTime ?
			mw.msg( 'checkuser-userinfocard-joined-unknowndate', props.gender ) :
			mw.msg( 'checkuser-userinfocard-joined', props.joinedDate, props.joinedRelative, props.gender );

		const formattedGroups = computed( () => props.groups );
		const formattedGlobalGroups = computed( () => props.globalGroups );

		const activeWikisLabel = mw.msg( 'checkuser-userinfocard-active-wikis-label' );
		const activeWikisList = computed( () => Object.keys( props.activeWikis ).map(
			( wikiId ) => ( { wikiId, url: props.activeWikis[ wikiId ] } )
		) );

		const pastBlocksLink = mw.Title.makeTitle( -1, 'Log/block' ).getUrl(
			{ page: props.username }
		);
		const globalEditsLink = mw.Title.makeTitle(
			-1, `GlobalContributions/${ props.username }`
		).getUrl();
		const localEditsLink = mw.Title.makeTitle(
			-1, `Contributions/${ props.username }`
		).getUrl();
		const localRevertedEditsLink = mw.Title.makeTitle(
			-1, `Contributions/${ props.username }`
		).getUrl( { tagfilter: 'mw-reverted' } );
		const newArticlesLink = mw.Title.makeTitle( -1, 'Contributions' ).getUrl(
			{ target: props.username, namespace: 0, newOnly: 1 }
		);
		const thanksReceivedLink = mw.Title.makeTitle( -1, 'Log/thanks' ).getUrl(
			{ page: props.username }
		);
		const thanksSentLink = mw.Title.makeTitle( -1, 'Log/thanks' ).getUrl(
			{ user: props.username }
		);
		const checksLink = mw.Title.makeTitle( -1, 'CheckUserLog' ).getUrl(
			{ cuSearch: props.username }
		);

		let globalRestrictionsLabel = null;
		if ( props.globalRestrictions ) {
			const specialPage = props.globalRestrictions === 'locked' ? 'CentralAuth' : 'GlobalBlockList';
			const linkTarget = mw.Title.makeTitle( -1, specialPage + '/' + props.username );

			if ( props.globalRestrictionsDate ) {
				// Possible variants:
				// * checkuser-userinfocard-global-restrictions-locked
				// * checkuser-userinfocard-global-restrictions-blocked
				// * checkuser-userinfocard-global-restrictions-blocked-disabled
				globalRestrictionsLabel = mw.message(
					'checkuser-userinfocard-global-restrictions-' + props.globalRestrictions,
					props.gender,
					linkTarget.getPrefixedText(),
					props.globalRestrictionsDate
				).parse();
			} else if ( props.globalRestrictions === 'locked' ) {
				// For 'locked' it may happen that the date is not available, when relevant log
				// entry has its action deleted. Global blocks do not offer a similar option.
				globalRestrictionsLabel = mw.message(
					'checkuser-userinfocard-global-restrictions-locked-no-date',
					props.gender,
					linkTarget.getPrefixedText()
				).parse();
			}
		}

		const maxEdits = mw.config.get( 'wgCheckUserGEUserImpactMaxEdits' ) || 1000;
		const maxThanks = mw.config.get( 'wgCheckUserGEUserImpactMaxThanks' ) || 1000;
		const canViewCheckUserLog = mw.config.get( 'wgCheckUserCanViewCheckUserLog' );
		const canAccessTemporaryAccountLog = mw.config.get( 'wgCheckUserCanAccessTemporaryAccountLog' );
		const canAccessTemporaryAccountIpAddresses = computed(
			() => props.canAccessTemporaryAccountIpAddresses
		);

		function formatCount( count, max ) {
			return count >= max ?
				mw.msg( 'checkuser-userinfocard-count-exceeds-max-to-display', mw.language.convertNumber( max ) ) :
				mw.language.convertNumber( count );
		}

		const infoRows = computed( () => {
			const rows = [];

			// Active blocks: display this only when the number of active blocks is greater than 0
			if ( props.activeBlocks > 0 ) {
				rows.push( {
					icon: cdxIconAlert,
					iconClass: 'ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks',
					messageKey: 'checkuser-userinfocard-active-blocks-from-all-wikis',
					mainValue: mw.language.convertNumber( props.activeBlocks ),
					mainLink: props.specialCentralAuthUrl,
					mainLinkLogId: 'active_blocks'
				} );
			}

			// Past blocks: display this only when the number of past blocks is greater than 0
			// and if the user has the ability to create blocks.
			if ( mw.config.get( 'wgCheckUserCanBlock' ) && props.pastBlocks > 0 ) {
				rows.push( {
					icon: cdxIconAlert,
					iconClass: 'ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks',
					messageKey: 'checkuser-userinfocard-past-blocks',
					mainValue: mw.language.convertNumber( props.pastBlocks ),
					mainLink: pastBlocksLink,
					mainLinkLogId: 'past_blocks'
				} );
			}

			rows.push( {
				icon: cdxIconEdit,
				iconClass: 'ext-checkuser-userinfocard-icon',
				messageKey: 'checkuser-userinfocard-global-edits',
				mainValue: mw.language.convertNumber( props.globalEdits ),
				mainLink: globalEditsLink,
				mainLinkLogId: 'global_edits'
			} );

			const localEditsRow = {
				icon: cdxIconEdit,
				iconClass: 'ext-checkuser-userinfocard-icon',
				mainValue: mw.language.convertNumber( props.localEdits ),
				mainLink: localEditsLink,
				mainLinkLogId: 'local_edits'
			};
			if ( props.localEditsReverted !== null ) {
				localEditsRow.messageKey = 'checkuser-userinfocard-local-edits';
				localEditsRow.suffixValue = mw.language.convertNumber( props.localEditsReverted );
				localEditsRow.suffixLink = localRevertedEditsLink;
				localEditsRow.suffixLinkLogId = 'reverted_local_edits';
			} else {
				localEditsRow.messageKey = 'checkuser-userinfocard-local-edits-reverts-unknown';
			}
			rows.push( localEditsRow );

			if ( props.newArticles !== null ) {
				rows.push( {
					icon: cdxIconArticles,
					iconClass: 'ext-checkuser-userinfocard-icon',
					messageKey: 'checkuser-userinfocard-new-articles',
					mainValue: formatCount( props.newArticles, maxEdits ),
					mainLink: newArticlesLink,
					mainLinkLogId: 'new_articles'
				} );
			}

			if ( props.thanksReceived !== null && props.thanksSent !== null ) {
				rows.push( {
					icon: cdxIconHeart,
					iconClass: 'ext-checkuser-userinfocard-icon',
					messageKey: 'checkuser-userinfocard-thanks',
					mainValue: formatCount( props.thanksReceived, maxThanks ),
					mainLink: thanksReceivedLink,
					mainLinkLogId: 'thanks_received',
					suffixValue: formatCount( props.thanksSent, maxThanks ),
					suffixLink: thanksSentLink,
					suffixLinkLogId: 'thanks_sent'
				} );
			}

			if ( canViewCheckUserLog ) {
				if ( props.lastChecked ) {
					rows.push( {
						icon: cdxIconSearch,
						iconClass: 'ext-checkuser-userinfocard-icon',
						messageKey: 'checkuser-userinfocard-checks',
						mainValue: mw.language.convertNumber( props.checks ),
						mainLink: checksLink,
						mainLinkLogId: 'last_checked',
						suffixValue: props.lastChecked
					} );
				} else {
					rows.push( {
						icon: cdxIconSearch,
						iconClass: 'ext-checkuser-userinfocard-icon',
						messageKey: 'checkuser-userinfocard-checks-empty',
						mainValue: mw.language.convertNumber( props.checks ),
						mainLink: checksLink,
						mainLinkLogId: 'last_checked'
					} );
				}
			}

			if ( props.hasIpRevealInfo ) {
				const row = {
					icon: cdxIconUserTemporaryLocation,
					iconClass: 'ext-checkuser-userinfocard-icon',
					mainValue: mw.language.convertNumber( props.numberOfIpReveals )
				};

				if ( props.numberOfIpReveals > 0 ) {
					row.messageKey = 'checkuser-userinfocard-ip-revealed-count';
					row.suffixValue = props.ipRevealLastCheck;
				} else {
					row.messageKey = 'checkuser-userinfocard-ip-revealed-never';
				}

				if ( canAccessTemporaryAccountLog ) {
					const title = 'Log/checkuser-temporary-account';
					row.mainLink = mw.Title.makeTitle( -1, title )
						.getUrl( { page: `User:${ props.username }` } );
				}

				rows.push( row );
			}

			if ( canAccessTemporaryAccountIpAddresses.value ) {
				rows.push( {
					icon: cdxIconUserTemporaryLocation,
					iconClass: 'ext-checkuser-userinfocard-icon',
					messageKey: 'checkuser-userinfocard-temporary-account-viewer-opted-in'
				} );
			}

			const tempAccountsOnIpCount = props.tempAccountsOnIpCount;
			if ( mw.util.isTemporaryUser( props.username ) && tempAccountsOnIpCount.length === 2 ) {
				const bucketRangeStart = tempAccountsOnIpCount[ 0 ];
				const bucketRangeEnd = tempAccountsOnIpCount[ 1 ];

				let bucketMsgKey = 'checkuser-temporary-account-bucketcount-';
				if ( bucketRangeStart === bucketRangeEnd ) {
					if ( bucketRangeStart === 0 ) {
						bucketMsgKey += 'min';
					} else {
						bucketMsgKey += 'max';
					}
				} else {
					bucketMsgKey += 'range';
				}

				// Uses:
				// * checkuser-temporary-account-bucketcount-min
				// * checkuser-temporary-account-bucketcount-range
				// * checkuser-temporary-account-bucketcount-max
				const bucketMsg = mw.msg(
					bucketMsgKey,
					mw.language.convertNumber( bucketRangeStart ),
					mw.language.convertNumber( bucketRangeEnd )
				);

				rows.push( {
					icon: cdxIconUserTemporary,
					iconClass: 'ext-checkuser-userinfocard-icon',
					messageKey: 'checkuser-userinfocard-temporary-account-bucketcount',
					mainValue: bucketMsg,
					tooltipKey: 'checkuser-userinfocard-temporary-account-bucketcount-tooltip'
				} );
			}
			return rows;
		} );

		function onWikiLinkClick( wikiId ) {
			logEvent( 'link_click', {
				subType: 'active_wiki',
				source: 'card_body',
				context: wikiId
			} );
		}

		return {
			joined,
			formattedGroups,
			formattedGlobalGroups,
			activeWikisLabel,
			activeWikisList,
			globalRestrictionsLabel,
			infoRows,
			onWikiLinkClick,
			cdxIconAlert
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-body {
	padding: @spacing-0;
	font-size: @font-size-small;
	padding-bottom: @spacing-100;
	overflow: auto;
}

.ext-checkuser-userinfocard-joined {
	margin-top: @spacing-0;
	margin-bottom: @spacing-50;
}

.ext-checkuser-userinfocard-icon {
	margin-right: @spacing-25;

	// this should be deep enough so `.cdx-icon` alone doesn't overwrite this style
	/* stylelint-disable-next-line selector-class-pattern */
	&.cdx-icon {
		color: var( --color-subtle );

		&.ext-checkuser-userinfocard-icon-blocks {
			color: var( --color-icon-warning );
		}
	}
}

p.ext-checkuser-userinfocard-groups,
p.ext-checkuser-userinfocard-global-groups,
p.ext-checkuser-userinfocard-active-wikis {
	margin: @spacing-0 @spacing-0 @spacing-25;
}

p.ext-checkuser-userinfocard-groups {
	margin-top: @spacing-100;
}

.ext-checkuser-userinfocard-gradient {
	height: @spacing-200;
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	background: @background-color-base;
	/* @noflip */
	background: linear-gradient( 360deg, @background-color-base 0%, @background-color-transparent 100% );
}
</style>
