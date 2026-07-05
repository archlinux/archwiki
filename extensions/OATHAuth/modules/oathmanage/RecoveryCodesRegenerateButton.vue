<template>
	<cdx-button @click.prevent="open = true">
		{{ createLabel }}
	</cdx-button>
	<cdx-dialog
		v-model:open="open"
		:title="createLabel"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="onPrimaryAction"
		@default="open = false"
	>
		<p v-i18n-html="warningMessage"></p>
	</cdx-dialog>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton, CdxDialog } = require( '../codex.js' );
const config = require( './config.json' );

module.exports = exports = defineComponent( {
	components: {
		CdxButton,
		CdxDialog
	},
	setup() {
		const createLabel = mw.message( 'oathauth-recoverycodes-create-label' )
			.params( [ config.OATHRecoveryCodesCount ] ).text();
		const warningMessage = mw.message( 'oathauth-recoverycodes-regenerate-warning' )
			.params( [ config.OATHRecoveryCodesCount ] );

		const primaryAction = {
			label: createLabel,
			actionType: 'destructive'
		};
		const defaultAction = {
			label: mw.message( 'cancel' ).text()
		};

		const open = ref( false );

		function onPrimaryAction() {
			// TODO actually regenerate recovery codes
		}

		return {
			createLabel,
			warningMessage,
			primaryAction,
			defaultAction,
			open,
			onPrimaryAction
		};
	}
} );
</script>
