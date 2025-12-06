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
		<p v-i18n-html:checkuser-ip-auto-reveal-on-dialog-text></p>
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
		<cdx-message
			v-if="enableError !== ''"
			type="error"
			:inline="true"
		>
			<p>{{ enableError }}</p>
		</cdx-message>
	</cdx-dialog>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxDialog, CdxField, CdxSelect, CdxMessage } = require( '@wikimedia/codex' );
const { enableAutoReveal } = require( './../ipReveal.js' );
const useInstrument = require( '../useInstrument.js' );

// The duration messages for the select were translated using PHP's Message::durationParams.
const durations = require( './../durations.json' );

// @vue/component
module.exports = exports = {
	name: 'IPAutoRevealOnDialog',
	components: {
		CdxDialog,
		CdxField,
		CdxSelect,
		CdxMessage
	},
	props: {
		toolLink: {
			type: Object,
			required: true
		}
	},
	setup( props ) {
		const logEvent = useInstrument();

		const open = ref( true );
		const selected = ref( null );
		const enableError = ref( '' );

		const defaultAction = {
			label: mw.msg( 'checkuser-ip-auto-reveal-on-dialog-default-action' )
		};
		const primaryAction = ref( {
			label: mw.msg( 'checkuser-ip-auto-reveal-on-dialog-primary-action' ),
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
			enableAutoReveal( selected.value ).then(
				() => {
					open.value = false;

					props.toolLink.text(
						mw.message( 'checkuser-ip-auto-reveal-link-sidebar-on' )
					);

					logEvent( 'session_start', { sessionLength: Number( selected.value ) } );

					mw.notify( mw.message( 'checkuser-ip-auto-reveal-notification-on' ), {
						classes: [ 'ext-checkuser-ip-auto-reveal-notification-on' ],
						type: 'success'
					} );
				},
				( error ) => {
					enableError.value = error;
				}
			);
		}

		return {
			open,
			enableError,
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
