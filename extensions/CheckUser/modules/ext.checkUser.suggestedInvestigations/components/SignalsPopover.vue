<template>
	<cdx-popover
		v-model:open="open"
		placement="bottom"
		:render-in-place="true"
		:use-close-button="true"
		:anchor="anchor"
		:title="$i18n( 'checkuser-suggestedinvestigations-risk-signals-popover-title' ).text()"
		:close-button-label="$i18n(
			'checkuser-suggestedinvestigations-risk-signals-popover-close-label'
		).text()"
		class="ext-checkuser-suggestedinvestigations-signals-popover"
	>
		<!--
		Security Note: This use of v-html is considered acceptable because:
		- HTML is taken from mw.config.get set by the server with those messages
			appropriately HTML escaped
		- MediaWiki messages used here do not include unescaped placeholders
		-->
		<!-- eslint-disable-next-line vue/no-v-html -->
		<span v-html="popoverHtml"></span>
	</cdx-popover>
</template>

<script>
const { ref } = require( 'vue' ),
	{ CdxPopover } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'SignalsPopover',
	components: { CdxPopover },
	props: {
		/**
		 * The element used as the anchor for the popover
		 */
		anchor: {
			type: HTMLElement,
			required: true
		}
	},
	setup() {
		const open = ref( true );

		const signals = mw.config.get( 'wgCheckUserSuggestedInvestigationsSignals' );
		// Uses messages which start with
		// "checkuser-suggestedinvestigations-risk-signals-popover-body" such as:
		// * checkuser-suggestedinvestigations-risk-signals-popover-body-dev-signal-1
		// * checkuser-suggestedinvestigations-risk-signals-popover-body-dev-signal-2
		const signalDescriptions = signals.map( ( signal ) => mw.message(
			'checkuser-suggestedinvestigations-risk-signals-popover-body-' + signal
		).parse() );

		let popoverHtml = mw.message( 'checkuser-suggestedinvestigations-risk-signals-popover-body-intro' ).escaped();
		popoverHtml += '<ul>' +
			signalDescriptions.map( ( description ) => '<li>' + description + '</li>' ).join( '\n' ) +
			'</ul>';

		return { open, popoverHtml };
	},
	methods: {
		/**
		 * @return {boolean} Whether the signals popover is currently open
		 */
		// eslint-disable-next-line vue/no-unused-properties
		isPopoverOpen() {
			return this.open;
		},
		/**
		 * Opens the signals popover
		 */
		// eslint-disable-next-line vue/no-unused-properties
		openPopover() {
			this.open = true;
		},
		/**
		 * Closes the signals popover
		 */
		// eslint-disable-next-line vue/no-unused-properties
		closePopover() {
			this.open = false;
		}
	}
};
</script>
