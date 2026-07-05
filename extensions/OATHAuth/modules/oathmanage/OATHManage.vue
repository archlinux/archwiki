<template>
	<!-- Passkeys section (show first if user has 2FA keys) -->
	<div v-if="hasPasskeys" class="mw-special-OATHManage-passkeys">
		<h3>{{ $i18n( 'oathauth-passkeys-header' ) }}</h3>

		<cdx-accordion
			v-for="key in data.passkeys"
			:key="key.id"
			separation="outline"
		>
			<template #title>
				{{ key.name }}
			</template>
			<template #description>
				{{ key.timestamp }}
			</template>
			<form :action="formAction" class="mw-special-OATHManage-authmethods__method-actions">
				<input
					type="hidden"
					name="title"
					:value="pageTitle">
				<input
					type="hidden"
					name="module"
					:value="key.module">
				<input
					type="hidden"
					name="keyId"
					:value="key.id">
				<input
					type="hidden"
					name="warn"
					value="1">
				<cdx-button
					action="destructive"
					weight="primary"
					type="submit"
					name="action"
					value="delete"
				>
					{{ $i18n( 'oathauth-authenticator-delete' ) }}
				</cdx-button>
			</form>
		</cdx-accordion>
		<div class="mw-special-OATHManage-authmethods__addform">
			<add-passkey-button></add-passkey-button>
		</div>
	</div>

	<!-- Empty passkeys section -->
	<div
		v-if="hasKeys && !hasPasskeys"
		class="mw-special-OATHManage-passkeys mw-special-OATHManage-passkeys--no-keys"
	>
		<h3>{{ $i18n( 'oathauth-passkeys-header' ) }}</h3>
		<div class="mw-special-OATHManage-authmethods__addform">
			<p class="mw-special-OATHManage-passkeys__placeholder">
				{{ $i18n( 'oathauth-passkeys-placeholder' ) }}
			</p>
			<add-passkey-button></add-passkey-button>
		</div>
	</div>

	<!-- 2FA/Auth methods section -->
	<div
		class="mw-special-OATHManage-authmethods"
		:class="{ 'mw-special-OATHManage-authmethods--no-keys': !hasKeys }">
		<h3>{{ $i18n( 'oathauth-authenticator-header' ) }}</h3>
		<cdx-message v-if="isRequiredToHave2FA && hasKeys" inline class="mw-special-OATHManage-mandatory-2fa">
			{{ $i18n( 'oathauth-2fa-required' ) }}
		</cdx-message>
		<cdx-message v-if="isRequiredToHave2FA && !hasKeys" class="mw-special-OATHManage-mandatory-2fa">
			{{ $i18n( 'oathauth-2fa-required' ) }}
			<ul>
				<li v-for="( { text, count }, wiki ) in groupsRequiring2FAPerWiki" :key="wiki">
					{{ $i18n( 'oathauth-2fa-required-groups-on-project', count, text, wiki ) }}
				</li>
			</ul>
		</cdx-message>
		<cdx-accordion
			v-for="key in data.keys"
			:key="key.id"
			separation="outline">
			<template #title>
				{{ key.name }}
			</template>
			<template #description>
				{{ key.timestamp }}
			</template>
			<form :action="formAction" class="mw-special-OATHManage-authmethods__method-actions">
				<input
					type="hidden"
					name="title"
					:value="pageTitle">
				<input
					type="hidden"
					name="module"
					:value="key.module">
				<input
					type="hidden"
					name="keyId"
					:value="key.id">
				<input
					type="hidden"
					name="warn"
					value="1">
				<cdx-button
					action="destructive"
					weight="primary"
					type="submit"
					name="action"
					value="delete"
					:disabled="!canRemoveKeys"
				>
					{{ $i18n( 'oathauth-authenticator-delete' ) }}
				</cdx-button>
			</form>
			<groups-with-2fa-notice v-if="!canRemoveKeys" :groups="groupsRequiring2FA"></groups-with-2fa-notice>
		</cdx-accordion>

		<form :action="formAction" class="mw-special-OATHManage-authmethods__addform">
			<input
				type="hidden"
				name="title"
				:value="pageTitle">
			<input
				type="hidden"
				name="action"
				value="enable">

			<p v-if="!hasKeys" class="mw-special-OATHManage-authmethods__placeholder">
				{{ $i18n( 'oathauth-authenticator-placeholder' ) }}
			</p>

			<cdx-button
				v-for="module in data.modules"
				:key="module.name"
				type="submit"
				name="module"
				:value="module.name"
			>
				{{ module.labelMessage }}
			</cdx-button>
		</form>
	</div>

	<!-- Empty passkey without MFA section -->
	<div v-if="!hasKeys" class="mw-special-OATHManage-passkeys--no-keys">
		<h3>{{ $i18n( 'oathauth-passkeys-header' ) }}</h3>
		<div class="mw-special-OATHManage-authmethods__addform">
			<p class="mw-special-OATHManage-passkeys__placeholder">
				{{ $i18n( 'oathauth-passkeys-placeholder' ) }}
			</p>
			<p class="mw-special-OATHManage-passkeys__placeholder">
				{{ $i18n( 'oathauth-passkeys-no2fa' ) }}
			</p>
		</div>
	</div>
</template>

<script>
const { defineComponent, reactive, computed } = require( 'vue' );
const { CdxAccordion, CdxButton, CdxMessage } = require( './codex.js' );
const AddPasskeyButton = require( './AddPasskeyButton.vue' );
const GroupsWith2FANotice = require( './GroupsWith2FANotice.vue' );

module.exports = exports = defineComponent( {
	components: {
		CdxAccordion,
		CdxButton,
		CdxMessage,
		AddPasskeyButton,
		'groups-with-2fa-notice': GroupsWith2FANotice
	},
	setup() {
		const data = reactive( mw.config.get( 'wgOATHManageData' ) );
		const hasKeys = computed( () => data.keys.length > 0 );
		const hasPasskeys = computed( () => data.passkeys.length > 0 );
		const groupsRequiring2FA = reactive( data.groupsRequiring2FA );
		const isRequiredToHave2FA = computed( () => data.groupsRequiring2FA.length > 0 );
		const canRemoveKeys = computed( () => !isRequiredToHave2FA.value || data.keys.length > 1 );

		const groupsRequiring2FAPerWiki = computed( () => {
			const wikis = {};
			data.groupsRequiring2FA.forEach( ( entry ) => {
				if ( !( entry.wiki in wikis ) ) {
					wikis[ entry.wiki ] = [];
				}
				wikis[ entry.wiki ].push( ...entry.groupNames );
			} );
			for ( const wiki in wikis ) {
				wikis[ wiki ] = {
					text: mw.language.listToText( wikis[ wiki ] ),
					count: wikis[ wiki ].length
				};
			}
			return wikis;
		} );

		const formAction = mw.config.get( 'wgScript' );
		const pageTitle = mw.config.get( 'wgPageName' );

		return {
			data,
			formAction,
			pageTitle,
			hasKeys,
			hasPasskeys,
			isRequiredToHave2FA,
			canRemoveKeys,
			groupsRequiring2FA,
			groupsRequiring2FAPerWiki
		};
	}
} );
</script>
