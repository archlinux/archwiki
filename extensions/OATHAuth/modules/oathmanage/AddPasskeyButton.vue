<template>
	<cdx-button
		:disabled="!supportsPasskeys"
		@click.prevent="open = true"
	>
		{{ $i18n( 'oathauth-passkeys-add' ) }}
	</cdx-button>
	<add-passkey-dialog v-model:open="open"></add-passkey-dialog>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton } = require( './codex.js' );
const AddPasskeyDialog = require( './AddPasskeyDialog.vue' );

/**
 * Feature detection for the browser's support for passkeys. If the browser doesn't support the
 * passkey features we need, this returns false, and the "Add passkey" button will be disabled.
 *
 * @return {boolean}
 */
async function checkPasskeySupport() {
	if ( !window.PublicKeyCredential ) {
		return false;
	}
	// Workaround for T415089: Firefox on Linux returns false for userVerifyingPlatformAuthenticator
	// even when certain browser extensions are installed that should cause it to return true.
	// To provide a better user experience, skip this check for Firefox on Linux.
	const profile = $.client.profile();
	if (
		profile.layout === 'gecko' &&
		( profile.platform === 'linux' || profile.platform === 'solaris' )
	) {
		return true;
	}

	// Try the mode modern getClientCapabilities API first
	if ( window.PublicKeyCredential.getClientCapabilities ) {
		const capabilities = await window.PublicKeyCredential.getClientCapabilities();
		return capabilities.userVerifyingPlatformAuthenticator;
	}
	// If that's not available, fall back to the older API
	if ( window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable ) {
		return await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
	}
	return false;
}

module.exports = exports = defineComponent( {
	components: {
		CdxButton,
		AddPasskeyDialog
	},
	setup() {
		const open = ref( false );
		const supportsPasskeys = ref( true );
		checkPasskeySupport().then( ( support ) => {
			supportsPasskeys.value = support;
		} );

		return {
			supportsPasskeys,
			open
		};
	}
} );
</script>
