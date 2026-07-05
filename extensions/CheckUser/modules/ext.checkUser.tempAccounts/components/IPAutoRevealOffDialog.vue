<template>
	<cdx-dialog
		v-model:open="open"
		class="ext-checkuser-ip-auto-reveal-off-dialog"
		:title="$i18n( 'checkuser-ip-auto-reveal-off-dialog-title' ).text()"
		:use-close-button="true"
		:default-action="defaultAction"
		:primary-action="primaryAction"
		@default="onExtend"
		@primary="onRemove"
	>
		<!-- eslint-disable vue/no-v-html -->
		<p v-html="expiry"></p>
		<cdx-message
			v-if="extendError !== ''"
			type="error"
			:inline="true"
		>
			<p>{{ extendError }}</p>
		</cdx-message>
		<cdx-message
			v-if="disableError !== ''"
			type="error"
			:inline="true"
		>
			<p>{{ disableError }}</p>
		</cdx-message>
		<template #footer-text>
			<p v-i18n-html:checkuser-ip-auto-reveal-off-dialog-text-info></p>
		</template>
	</cdx-dialog>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxDialog, CdxMessage } = require( '@wikimedia/codex' );
const { setAutoRevealStatus } = require( './../ipRevealUtils.js' );
const { disableAutoReveal } = require( './../ipReveal.js' );
const useInstrument = require( '../useInstrument.js' );

// The duration message for the error was translated using PHP's Message::durationParams.
const maxDurationError = require( './../maxDurationError.json' );

// @vue/component
module.exports = exports = {
	name: 'IPAutoRevealOffDialog',
	components: {
		CdxDialog,
		CdxMessage
	},
	props: {
		expiryTimestamp: {
			type: Number,
			required: true
		},
		toolLink: {
			type: Object,
			required: true
		}
	},
	setup( props ) {
		const logEvent = useInstrument();

		const open = ref( true );
		const extendError = ref( '' );
		const disableError = ref( '' );

		const defaultAction = {
			label: mw.msg( 'checkuser-ip-auto-reveal-off-dialog-extend-action' )
		};
		const primaryAction = ref( {
			label: mw.msg( 'checkuser-ip-auto-reveal-off-dialog-off-action' ),
			actionType: 'progressive'
		} );

		function onExtend() {
			const currentExpiryInSeconds = props.expiryTimestamp;
			const extendBySeconds = 10 * 60;

			let newRelativeExpiryInSeconds;
			if ( currentExpiryInSeconds === 0 ) {
				newRelativeExpiryInSeconds = extendBySeconds;
			} else {
				const newExpiryInSeconds = currentExpiryInSeconds + extendBySeconds;
				newRelativeExpiryInSeconds = newExpiryInSeconds - Math.round( Date.now() / 1000 );
			}

			setAutoRevealStatus( newRelativeExpiryInSeconds ).then(
				() => {
					open.value = false;
					logEvent( 'session_extend' );
				},
				() => {
					extendError.value = maxDurationError.translation;
				}
			);
		}

		function onRemove() {
			disableAutoReveal().then(
				() => {
					open.value = false;

					props.toolLink.text(
						mw.message( 'checkuser-ip-auto-reveal-link-sidebar' )
					);

					mw.notify( mw.message( 'checkuser-ip-auto-reveal-notification-off' ), {
						classes: [ 'ext-checkuser-ip-auto-reveal-notification-off' ],
						type: 'success'
					} );

					logEvent( 'session_end' );
				},
				() => {
					disableError.value = mw.msg( 'checkuser-ip-auto-reveal-off-dialog-error-disable' );
				}
			);
		}

		return {
			open,
			extendError,
			disableError,
			defaultAction,
			primaryAction,
			onExtend,
			onRemove
		};
	},
	data( props ) {
		const expiryTime = new Date( props.expiryTimestamp * 1000 );
		const secondsUntilExpiry = Math.round( ( expiryTime - Date.now() ) / 1000 );
		return {
			secondsUntilExpiry: secondsUntilExpiry,
			expiry: this.formatExpiryTime( secondsUntilExpiry )
		};
	},
	methods: {
		formatExpiryTime( secondsUntilExpiry ) {
			const daysUntilExpiry = Math.floor( secondsUntilExpiry / ( 3600 * 24 ) );
			const hoursUntilExpiry = Math.floor( secondsUntilExpiry / 3600 );
			const minutesUntilExpiry = Math.floor( secondsUntilExpiry / 60 );

			const remainderHours = hoursUntilExpiry % 24;
			const remainderMinutes = minutesUntilExpiry % 60;
			const remainderSeconds = secondsUntilExpiry % 60;

			const displayTime = String( remainderHours ) + ':' +
				( remainderMinutes < 10 ? '0' : '' ) + String( remainderMinutes ) + ':' +
				( remainderSeconds < 10 ? '0' : '' ) + String( remainderSeconds );

			return mw.message(
				'checkuser-ip-auto-reveal-off-dialog-text-expiry',
				displayTime,
				daysUntilExpiry
			).parse();

		}
	},
	watch: {
		secondsUntilExpiry: {
			handler( expiry ) {
				if ( expiry > 0 && this.open ) {
					// Display the time until expiry. Note that this isn't perfectly in
					// sync with clock time.
					setTimeout( () => {
						this.secondsUntilExpiry--;
						this.expiry = this.formatExpiryTime( this.secondsUntilExpiry );
					}, 1000 );
				}
			},
			immediate: true
		}
	}
};
</script>
