<template>
	<div v-if="visible">
		<div
			v-if="message"
			class="ext-checkuser-tempaccount-specialblock-ips"
		>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<label v-html="message"></label>
		</div>
		<cdx-button
			v-else
			action="progressive"
			weight="quiet"
			class="ext-checkuser-tempaccount-specialblock-ips-link"
			:disabled="isPerformerBlocked"
			@click="onClick"
		>
			{{ $i18n( 'checkuser-tempaccount-reveal-ip-button-label' ).text() }}
		</cdx-button>
		<cdx-toggle-button
			v-if="isPerformerBlocked"
			ref="blockDetailsToggle"
			v-model="showBlockDetails"
			quiet
			class="ext-checkuser-tempaccount-specialblock-block-details-toggle"
			:aria-label="$i18n( 'checkuser-tempaccount-reveal-blocked-title' ).text()"
			@update:model-value="onBlockDetailsPopoverToggled">
			<cdx-icon :icon="cdxIconInfo"></cdx-icon>
		</cdx-toggle-button>
		<!--
			CdxPopover uses the floating-ui library in a way that causes infinite recursion when
			mounted in JSDOM.
			Shallow rendering the component in turn fails if an anchor reference is provided,
			because vue-test-utils is unable to stringify the HTML element held within the ref.
			Work around the situation by using shallow rendering in tests and use a well-known
			window name to avoid passing the anchor in this case.
		-->
		<cdx-popover
			v-if="isPerformerBlocked"
			v-model:open="showBlockDetails"
			class="ext-checkuser-tempaccount-specialblock-block-details"
			:anchor="windowName !== 'ShowIPButtonTests' ? blockDetailsToggle : null"
		>
			<div
				v-if="areBlockDetailsLoading"
				class="ext-checkuser-tempaccount-specialblock-block-details-indicator-wrapper"
			>
				<cdx-progress-indicator
					:aria-label="$i18n( 'checkuser-tempaccount-reveal-blocked-loading' ).text()">
				</cdx-progress-indicator>
			</div>
			<template v-if="blockDetails">
				<h4>{{ $i18n( 'checkuser-tempaccount-reveal-blocked-header' ).text() }}</h4>
				<p>{{ $i18n( 'checkuser-tempaccount-reveal-blocked-description' ).text() }}</p>
				<!-- Safety: blockDetails is expected to contain HTML parsed by
				the wikitext parser server-side. -->
				<!-- eslint-disable-next-line vue/no-v-html -->
				<div class="ext-checkuser-tempaccount-specialblock-block-details-content" v-html="blockDetails"></div>
			</template>
			<cdx-message v-if="blockDetailsMsg" :type="blockDetailsMsg.type">
				{{ blockDetailsMsg.text }}
			</cdx-message>
		</cdx-popover>
	</div>
</template>

<script>
const { computed, defineComponent, ref, watch } = require( 'vue' );
const { CdxButton, CdxIcon, CdxMessage, CdxPopover, CdxProgressIndicator, CdxToggleButton } = require( '@wikimedia/codex' );
const { cdxIconInfo } = require( './icons.json' );
const { performFullRevealRequest } = require( './rest.js' );
const { getFormattedBlockDetails } = require( './api.js' );

module.exports = exports = defineComponent( {
	name: 'ShowIPButton',
	components: {
		CdxButton,
		CdxIcon,
		CdxMessage,
		CdxPopover,
		CdxProgressIndicator,
		CdxToggleButton
	},
	props: {
		targetUser: { type: [ String, null ], required: true }
	},
	setup( props ) {
		const visible = computed( () => mw.util.isTemporaryUser( props.targetUser ) );
		const message = ref( '' );
		const isPerformerBlocked = mw.config.get( 'wgCheckUserIsPerformerBlocked' );
		const showBlockDetails = ref( false );
		const blockDetailsToggle = ref();
		const blockDetails = ref( null );
		const blockDetailsMsg = ref( null );
		const areBlockDetailsLoading = computed(
			() => !( blockDetails.value || blockDetailsMsg.value )
		);
		const windowName = computed( () => window.name );

		watch( () => props.targetUser, () => {
			message.value = '';
		} );

		/**
		 * Handle the click event.
		 */
		function onClick() {
			performFullRevealRequest( props.targetUser, [], [] )
				.then( ( { ips } ) => {
					if ( ips.length ) {
						const ipLinks = ips.map( ( ip ) => {
							const a = document.createElement( 'a' );
							a.href = mw.util.getUrl( `Special:IPContributions/${ ip }` );
							a.textContent = ip;
							return a.outerHTML;
						} );
						message.value = mw.message(
							'checkuser-tempaccount-specialblock-ips',
							ipLinks.length,
							mw.language.listToText( ipLinks )
						).text();
					} else {
						message.value = mw.message(
							'checkuser-tempaccount-no-ip-results',
							Math.round( mw.config.get( 'wgCUDMaxAge' ) / 86400 )
						).text();
					}
				} )
				.catch( () => {
					message.value = mw.message( 'checkuser-tempaccount-reveal-ip-error' ).text();
				} );
		}

		/**
		 * Called when the block details toggle button is clicked.
		 *
		 * @param {boolean} isActive
		 */
		function onBlockDetailsPopoverToggled( isActive ) {
			if ( isActive && areBlockDetailsLoading.value ) {
				getFormattedBlockDetails()
					.then(
						( data ) => {
							const blockInfo = data &&
								data.query &&
								data.query.checkuserformattedblockinfo &&
								data.query.checkuserformattedblockinfo.details;

							if ( blockInfo ) {
								blockDetails.value = blockInfo;
							} else {
								blockDetailsMsg.value = {
									type: 'success',
									text: mw.message( 'checkuser-tempaccount-reveal-blocked-missingblock' ).text()
								};
							}
						},
						() => {
							blockDetailsMsg.value = {
								type: 'error',
								text: mw.message( 'checkuser-tempaccount-reveal-blocked-error' ).text()
							};
						}
					);
			}
		}

		return {
			cdxIconInfo,
			visible,
			message,
			isPerformerBlocked,
			showBlockDetails,
			areBlockDetailsLoading,
			blockDetailsToggle,
			blockDetails,
			blockDetailsMsg,
			onBlockDetailsPopoverToggled,
			onClick,
			windowName
		};
	}
} );
</script>

<style lang="less">
.ext-checkuser-tempaccount-specialblock-ips-link {
	padding-right: 0;
}

.ext-checkuser-tempaccount-specialblock-block-details-toggle {
	padding-left: 0;
	vertical-align: bottom;
}

.ext-checkuser-tempaccount-specialblock-block-details-indicator-wrapper {
	text-align: center;
}
</style>
