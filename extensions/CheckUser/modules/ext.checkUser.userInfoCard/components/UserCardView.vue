<template>
	<!-- Header teleport - only when data is ready -->
	<teleport :to="headerContainer" :disabled="!headerContainer">
		<user-card-header
			v-if="!loading && !error"
			:username="userCard.username"
			:gender="userCard.gender"
			:user-page-url="userCard.userPageUrl"
			:user-page-is-known="userCard.userPageIsKnown"
			:user-page-watched="userCard.userPageWatched"
			:special-central-auth-url="userCard.specialCentralAuthUrl"
			@close="$emit( 'close' )"
		></user-card-header>
	</teleport>

	<!-- Body teleport - handles all states -->
	<teleport :to="bodyContainer" :disabled="!bodyContainer">
		<user-card-loading-view v-if="loading"></user-card-loading-view>

		<user-info-card-error
			v-else-if="error"
			:message="error"
		></user-info-card-error>

		<div v-else class="ext-checkuser-userinfocard-view">
			<!-- eslint-disable max-len -->
			<user-card-body
				:username="userCard.username"
				:gender="userCard.gender"
				:joined-date="userCard.joinedDate"
				:joined-relative="userCard.joinedRelativeTime"
				:is-registered-with-unknown-time="userCard.isRegisteredWithUnknownTime"
				:active-blocks="userCard.activeBlocksCount"
				:past-blocks="userCard.pastBlocksCount"
				:global-edits="userCard.globalEditCount"
				:groups="userCard.groups"
				:global-groups="userCard.globalGroups"
				:local-edits="userCard.localEditCount"
				:local-edits-reverted="userCard.localEditRevertedCount"
				:new-articles="userCard.newArticlesCount"
				:thanks-received="userCard.thanksReceivedCount"
				:thanks-sent="userCard.thanksGivenCount"
				:checks="userCard.checkUserChecks"
				:can-access-temporary-account-ip-addresses="userCard.canAccessTemporaryAccountIpAddresses"
				:last-checked="userCard.checkUserLastCheck"
				:active-wikis="userCard.activeWikis"
				:recent-local-edits="userCard.recentLocalEdits"
				:total-local-edits="userCard.totalLocalEdits"
				:last-edit-timestamp="userCard.lastEditTimestamp"
				:number-of-ip-reveals="userCard.numberOfIpReveals"
				:ip-reveal-last-check="userCard.ipRevealLastCheck"
				:has-ip-reveal-info="userCard.hasIpRevealInfo"
				:has-edit-in-last-60-days="userCard.hasEditInLast60Days"
				:special-central-auth-url="userCard.specialCentralAuthUrl"
				:global-restrictions="userCard.globalRestrictions"
				:global-restrictions-date="userCard.globalRestrictionsDate"
				:temp-accounts-on-ip-count="userCard.tempAccountsOnIPCount"
			></user-card-body>
			<!--eslint-enable-->
		</div>
	</teleport>
</template>

<script>
const UserCardBody = require( './UserCardBody.vue' );
const UserCardHeader = require( './UserCardHeader.vue' );
const UserCardLoadingView = require( './UserCardLoadingView.vue' );
const UserInfoCardError = require( './UserInfoCardError.vue' );
const DateFormatter = require( 'mediawiki.DateFormatter' );
const { processEditCountByDay, parseMediaWikiTimestamp } = require( '../util.js' );

// @vue/component
module.exports = exports = {
	name: 'UserCardView',
	components: {
		UserCardHeader,
		UserCardBody,
		UserCardLoadingView,
		UserInfoCardError
	},
	props: {
		username: {
			type: [ String ],
			required: true
		},
		headerContainer: {
			type: [ Object, HTMLElement ],
			default: null
		},
		bodyContainer: {
			type: [ Object, HTMLElement ],
			default: null
		}
	},
	emits: [ 'close' ],
	setup( props ) {
		const { ref, reactive, onActivated, onMounted } = require( 'vue' );

		// State
		const loading = ref( false );
		const error = ref( null );
		const userCard = reactive( {
			userPageUrl: '',
			userPageIsKnown: false,
			username: '',
			gender: 'unknown',
			joinedDate: '',
			joinedRelativeTime: '',
			isRegisteredWithUnknownTime: false,
			globalEditCount: 0,
			thanksReceivedCount: null,
			thanksGivenCount: null,
			activeBlocksCount: 0,
			pastBlocksCount: 0,
			localEditCount: 0,
			localEditRevertedCount: null,
			lastEditTimestamp: '',
			newArticlesCount: null,
			checkUserChecks: 0,
			checkUserLastCheck: '',
			activeWikis: {},
			groups: '',
			globalGroups: '',
			userPageWatched: false,
			canAccessTemporaryAccountIpAddresses: false,
			specialCentralAuthUrl: '',
			numberOfIpReveals: 0,
			ipRevealLastCheck: '',
			hasIpRevealInfo: false,
			globalRestrictions: null,
			globalRestrictionsTimestamp: null,
			tempAccountsOnIPCount: []
		} );

		// Methods
		function fetchUserInfo() {
			if ( !props.username || props.username.trim().length === 0 ) {
				return;
			}

			loading.value = true;
			error.value = null;

			const token = mw.user.tokens.get( 'csrfToken' );
			const rest = new mw.Rest();
			const payload = {
				token,
				username: props.username
			};
			// T404682
			const language = mw.config.get( 'wgUserLanguage' );

			rest.post( '/checkuser/v0/userinfo?uselang=' + language, payload )
				.then( ( userInfo ) => {
					if ( !userInfo ) {
						throw new Error( mw.msg( 'checkuser-userinfocard-error-no-data' ) );
					}

					const {
						name,
						gender,
						firstRegistration,
						localRegistration,
						lastEditTimestamp,
						globalEditCount,
						thanksReceived,
						thanksGiven,
						userPageIsKnown,
						newArticlesCount,
						totalEditCount,
						activeLocalBlocksAllWikis,
						pastBlocksOnLocalWiki,
						revertedEditCount,
						userPageWatched,
						checkUserChecks,
						checkUserLastCheck,
						canAccessTemporaryAccountIpAddresses,
						activeWikis,
						groups,
						globalGroups,
						specialCentralAuthUrl,
						numberOfIpReveals,
						ipRevealLastCheck,
						globalRestrictions,
						globalRestrictionsTimestamp,
						tempAccountsOnIPCount
					} = userInfo;
					const userTitleObj = mw.Title.makeTitle( 2, name );
					const userPageUrl = userTitleObj.getUrl();
					const { processedData, totalEdits } = processEditCountByDay(
						userInfo.editCountByDay
					);

					// Update reactive state
					userCard.userPageUrl = userPageUrl;
					userCard.userPageIsKnown = !!userPageIsKnown;
					userCard.username = name;
					userCard.gender = gender;

					// Parse and format firstRegistration date
					const firstRegDate = parseMediaWikiTimestamp( firstRegistration );
					userCard.joinedDate = firstRegDate ?
						DateFormatter.formatDate( firstRegDate ) :
						'';
					userCard.joinedRelativeTime = firstRegDate ?
						DateFormatter.formatRelativeTimeOrDate( firstRegDate ) :
						'';

					const lastEditDate = parseMediaWikiTimestamp( lastEditTimestamp );
					userCard.lastEditTimestamp = lastEditDate ?
						DateFormatter.formatTimeAndDate( lastEditDate ) :
						'';

					// If localRegistration is null, the account is registered but was done so
					// before user_registration timestamps were recorded. Pass this bool check
					// through so that these accounts can be correctly identified later.
					const isRegisteredWithUnknownTime = localRegistration === null;
					userCard.isRegisteredWithUnknownTime = isRegisteredWithUnknownTime;

					userCard.globalEditCount = globalEditCount;
					userCard.thanksReceivedCount = thanksReceived;
					userCard.thanksGivenCount = thanksGiven;
					userCard.recentLocalEdits = processedData;
					userCard.hasEditInLast60Days = processedData.some( ( item ) => item.count > 0 );
					userCard.totalLocalEdits = totalEdits;
					userCard.newArticlesCount = newArticlesCount;
					userCard.localEditCount = totalEditCount;
					userCard.localEditRevertedCount = revertedEditCount;
					userCard.userPageWatched = !!userPageWatched;
					userCard.activeBlocksCount = activeLocalBlocksAllWikis;
					userCard.pastBlocksCount = pastBlocksOnLocalWiki;
					userCard.checkUserChecks = checkUserChecks;
					userCard.specialCentralAuthUrl = specialCentralAuthUrl;
					userCard.canAccessTemporaryAccountIpAddresses =
						canAccessTemporaryAccountIpAddresses;
					userCard.tempAccountsOnIPCount = tempAccountsOnIPCount;

					// Parse and format checkUserLastCheck date
					const lastCheckDate = parseMediaWikiTimestamp( checkUserLastCheck );
					userCard.checkUserLastCheck = lastCheckDate ?
						DateFormatter.formatDate( lastCheckDate ) :
						'';

					// If numberOfIpReveals is present, the user may see IP Reveal info.
					// Including zero serves to explicitly show "IP Reveals: 0" instead
					// of hiding the row altogether. OTOH, if the API doesn't provide a
					// value, numberOfIpReveals is undefined and the row is hidden.
					const hasIpRevealInfo = ( numberOfIpReveals >= 0 );
					userCard.hasIpRevealInfo = hasIpRevealInfo;

					if ( hasIpRevealInfo ) {
						const lastIpRevealCheck = ipRevealLastCheck ?
							parseMediaWikiTimestamp( ipRevealLastCheck ) :
							null;

						if ( numberOfIpReveals > 0 && lastIpRevealCheck ) {
							userCard.ipRevealLastCheck =
								DateFormatter.formatDate( lastIpRevealCheck );
						}

						userCard.numberOfIpReveals = numberOfIpReveals;
					}

					userCard.activeWikis = !activeWikis || Array.isArray( activeWikis ) ?
						{} : activeWikis;
					userCard.groups = groups;
					userCard.globalGroups = globalGroups;
					userCard.globalRestrictions = globalRestrictions;

					const globalRestrictionsDate =
						parseMediaWikiTimestamp( globalRestrictionsTimestamp );
					userCard.globalRestrictionsDate = globalRestrictionsDate ?
						DateFormatter.formatDate( globalRestrictionsDate ) : '';

					loading.value = false;
				} )
				.catch( ( err, errOptions ) => {
					// Retrieving the error message from mw.Rest().post()
					const { xhr } = errOptions || {};
					const responseJSON = ( xhr && xhr.responseJSON ) || {};
					const userLang = mw.config.get( 'wgUserLanguage' );
					if (
						responseJSON.messageTranslations &&
						responseJSON.messageTranslations[ userLang ]
					) {
						error.value = responseJSON.messageTranslations[ userLang ];
					} else if ( err.message ) {
						error.value = err.message;
					} else {
						error.value = mw.msg( 'checkuser-userinfocard-error-generic' );
					}
					loading.value = false;
				} );
		}

		// Lifecycle hooks for keep-alive - triggered on every activation
		onActivated( () => {
			if ( !userCard.username && !loading.value ) {
				fetchUserInfo();
			}
		} );

		// Regular Vue lifecycle hook - triggered only once per key (username)
		onMounted( () => {
			fetchUserInfo();
		} );

		return {
			loading,
			error,
			userCard
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-view {
	display: flex;
	flex-direction: column;
}
</style>
