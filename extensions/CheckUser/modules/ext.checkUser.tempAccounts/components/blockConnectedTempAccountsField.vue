<template>
	<!-- Hide feature if no connected accounts were found on a successful call.
	Check for the error state because in that case, even if no connected accounts were
	found, the feature should still be visible. -->
	<div
		v-if="visible &&
			( connectedTempAccounts.length || blockConnectedTempAccountsApiCallError )"
		class="ext-checkuser-tempaccount-specialblock-connectedaccounts"
	>
		<cdx-message
			v-if="blockConnectedTempAccountsApiCallError"
			type="warning"
		>
			{{ blockConnectedTempAccountsApiCallError }}
		</cdx-message>
		<p v-if="!blockConnectedTempAccountsApiCallError">
			<span
				v-if="connectedTempAccountsMessage"
				v-i18n-html="connectedTempAccountsMessage"
			>
			</span>
			<span
				v-i18n-html:checkuser-related-tas-specialblock-contribs-link="[ targetUser ]"
			>
			</span>
		</p>
		<cdx-checkbox
			v-if="!blockId"
			v-model="shouldBlockConnectedTempAccounts"
			name="block-connected-accounts"
			:disabled="!!blockConnectedTempAccountsApiCallError || tooManyAccountsToBlock"
		>
			{{ $i18n(
				'checkuser-related-tas-specialblock-checkbox-label'
			).text() }}
		</cdx-checkbox>
		<cdx-message
			v-if="shouldBlockConnectedTempAccounts"
			type="warning"
		>
			<span
				v-i18n-html:checkuser-related-tas-specialblock-confirm="[
					connectedTempAccounts.length
				]"
			>
			</span>
		</cdx-message>
		<cdx-message
			v-if="tooManyAccountsToBlock"
			type="warning"
		>
			<span
				v-i18n-html:checkuser-related-tas-specialblock-limit-exceeded-error="[
					maxAllowed
				]"
			>
			</span>
		</cdx-message>
	</div>
</template>

<script>
const { computed, defineComponent, ref, watch } = require( 'vue' ),
	{ CdxCheckbox, CdxMessage } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'BlockConnectedTempAccountsField',
	components: {
		CdxCheckbox,
		CdxMessage
	},
	props: {
		targetUser: { type: [ String, null ], required: true },
		blockId: { type: [ Number, null ], required: true }
	},
	setup( props ) {
		// Limit bulk blocking to 15 accounts, covering the vast majority of cases.
		// See T419526#11731721.
		const maxAllowed = 15;

		const visible = computed( () => mw.config.get( 'blockEnableMultiblocks' ) &&
			mw.config.get( 'wgTemporaryAccountIPRevealAllowed' ) &&
			mw.util.isTemporaryUser( props.targetUser ) );
		const connectedTempAccounts = ref( [] );
		const blockConnectedTempAccountsApiCallError = ref( '' );
		const connectedTempAccountsMessage = ref( null );
		const shouldBlockConnectedTempAccounts = ref( false );
		const tooManyAccountsToBlock = ref( false );
		function resetForm() {
			blockConnectedTempAccountsApiCallError.value = '';
			shouldBlockConnectedTempAccounts.value = false;
			tooManyAccountsToBlock.value = false;
		}

		watch( () => props.targetUser, async ( newTarget, currentTarget ) => {
			if ( newTarget === currentTarget ) {
				return;
			}

			// Reset state on new call
			connectedTempAccounts.value = [];
			connectedTempAccountsMessage.value = null;
			resetForm();

			// Do nothing if the input won't be visible
			if ( !visible.value ) {
				return;
			}

			// Instrument that user has access to the feature and is viewing a temporary account
			mw.track( 'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total', 1, {
				action: 'viewed-tempaccount'
			} );

			// Get all connected accounts
			try {
				const { connectedAccounts, ipsUsedCount } = await new mw.Rest().post(
					`/checkuser/v0/connectedtemporaryaccounts/${ props.targetUser }`,
					{ token: mw.user.tokens.get( 'csrfToken' ) }
				);
				connectedTempAccounts.value = connectedAccounts
					.filter( ( user ) => props.targetUser !== user );

				if ( !connectedTempAccounts.value.length ) {
					return;
				}

				// Instrument that the user has viewed a temp account with found connected accounts
				mw.track( 'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total', 1, {
					action: 'found-connected-tempaccounts'
				} );

				// Instrument number of connected accounts found per-instance
				mw.track( 'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total', 1, {
					action: 'found-connected-tempaccounts-count',
					count: connectedTempAccounts.value.length
				} );

				// Instrument the total number of connected accounts found
				mw.track(
					'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
					connectedTempAccounts.value.length,
					{
						action: 'found-connected-tempaccounts-sum'
					}
				);

				if ( connectedTempAccounts.value.length > maxAllowed ) {
					tooManyAccountsToBlock.value = true;
				}

				const userLinks = connectedTempAccounts.value.map(
					( target ) => mw.message( 'checkuser-related-tas-target', target ).parse()
				);
				const $listOfUserLinks = $( $.parseHTML( mw.language.listToText( userLinks ) ) );
				connectedTempAccountsMessage.value = mw.message(
					'checkuser-related-tas-specialblock-accounts-list',
					connectedTempAccounts.value.length,
					ipsUsedCount,
					props.targetUser,
					mw.config.get( 'wgCUDMaxAge' ) / ( 60 * 60 * 24 ),
					$listOfUserLinks
				);
			} catch ( e ) {
				blockConnectedTempAccountsApiCallError.value = mw.msg(
					'checkuser-related-tas-specialblock-accounts-list-error',
					[ props.targetUser, mw.user ]
				);
			}
		}, { immediate: true } );

		mw.hook( 'mw.special.block.doBlockParamsReady' ).add( ( params ) => {
			// Instrument that the user is making a block that can also bulk block and
			// whether or not the user has opted to do so
			if ( connectedTempAccounts.value.length && !tooManyAccountsToBlock.value ) {
				mw.track( 'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total', 1, {
					action: shouldBlockConnectedTempAccounts.value ?
						'is-bulk-blocking' : 'not-bulk-blocking'
				} );
			}
			if ( shouldBlockConnectedTempAccounts.value ) {
				params.blockConnectedTempAccounts = connectedTempAccounts.value;
			}
		} );

		mw.hook( 'mw.special.block.formReset' ).add( () => {
			resetForm();
		} );

		mw.hook( 'SpecialBlock.block' ).add( ( block ) => {
			const additionalBlocks = Object.entries( block.additionalBlocksStatuses )
				.filter( ( obj ) => !obj[ 1 ].length );
			const blocksAdditionalErrors = [].concat(
				...Object.values( block.additionalBlocksStatuses )
			);

			// Instrument proportion of successful additional blocks
			mw.track( 'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total', 1, {
				totalblocksattempted: Object.keys( block.additionalBlocksStatuses ).length,
				totalblockssucceeded: additionalBlocks.length,
				totalalreadyblocked: blocksAdditionalErrors.length
			} );

			// Instrument total sum of successful additional blocks
			if ( additionalBlocks.length ) {
				mw.track(
					'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
					additionalBlocks.length,
					{
						action: 'successfully-blocked-connected-tempaccount-sum'
					}
				);
			}

			// // Instrument total sum of already blocked accounts
			if ( blocksAdditionalErrors.length ) {
				mw.track(
					'stats.mediawiki_checkuser_connected_tempaccounts_bulkblock_total',
					blocksAdditionalErrors.length,
					{
						action: 'already-blocked-connected-tempaccount-sum'
					}
				);
			}
		} );

		return {
			blockConnectedTempAccountsApiCallError,
			connectedTempAccounts,
			connectedTempAccountsMessage,
			maxAllowed,
			shouldBlockConnectedTempAccounts,
			tooManyAccountsToBlock,
			visible
		};
	}
} );
</script>
