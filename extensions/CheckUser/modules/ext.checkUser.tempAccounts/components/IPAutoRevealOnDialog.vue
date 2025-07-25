<template>
	<cdx-dialog
		v-model:open="open"
		class="ext-checkuser-ip-auto-reveal-on-dialog"
		:title="$i18n( 'checkuser-ip-auto-reveal-on-dialog-title' ).text()"
		:use-close-button="true"
		:default-action="defaultAction"
		:primary-action="primaryAction"
		@default="open = false"
		@primary="onSubmit"
	>
		<p>
			{{ $i18n( 'checkuser-ip-auto-reveal-on-dialog-text' ).text() }}
		</p>
		<cdx-field>
			<template #label>
				{{ $i18n( 'checkuser-ip-auto-reveal-on-dialog-select-label' ).text() }}
			</template>
			<cdx-select
				v-model:selected="selected"
				class="ext-checkuser-ip-auto-reveal-on-dialog__select"
				:menu-items="menuItems"
				:default-label="$i18n( 'checkuser-ip-auto-reveal-on-dialog-select-default' ).text()"
				@update:selected="onChange"
			>
			</cdx-select>
		</cdx-field>
	</cdx-dialog>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxDialog, CdxField, CdxSelect } = require( '@wikimedia/codex' );
const { enableAutoReveal } = require( './../ipReveal.js' );

// The duration messages for the select were translated using PHP's Message::durationParams.
const durations = require( './../durations.json' );

// @vue/component
module.exports = exports = {
	name: 'IPAutoRevealOnDialog',
	components: {
		CdxDialog,
		CdxField,
		CdxSelect
	},
	setup() {
		const open = ref( true );
		const selected = ref( null );

		const defaultAction = {
			label: mw.message( 'checkuser-ip-auto-reveal-on-dialog-default-action' ).text()
		};
		const primaryAction = ref( {
			label: mw.message( 'checkuser-ip-auto-reveal-on-dialog-primary-action' ).text(),
			actionType: 'progressive',
			disabled: !selected.value
		} );

		const menuItems = durations.map( ( duration ) => ( {
			label: duration.translation,
			value: duration.seconds
		} ) );

		function onChange() {
			primaryAction.value.disabled = !selected.value;
		}

		function onSubmit() {
			enableAutoReveal( selected.value );
			open.value = false;

			mw.notify( mw.message( 'checkuser-ip-auto-reveal-notification-on' ), {
				classes: [ 'ext-checkuser-ip-auto-reveal-notification-on' ],
				type: 'success'
			} );
		}

		return {
			open,
			defaultAction,
			primaryAction,
			menuItems,
			selected,
			onChange,
			onSubmit
		};
	}
};
</script>
