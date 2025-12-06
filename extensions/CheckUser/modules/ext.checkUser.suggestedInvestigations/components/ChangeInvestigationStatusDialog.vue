<template>
	<cdx-dialog
		v-model:open="open"
		:title="$i18n( 'checkuser-suggestedinvestigations-change-status-dialog-title' ).text()"
		:close-button-label="$i18n(
			'checkuser-suggestedinvestigations-change-status-dialog-close-label'
		).text()"
		:use-close-button="true"
		class="ext-checkuser-suggestedinvestigations-change-status-dialog"
	>
		<cdx-message
			v-if="statusUpdateErrorMessage !== ''"
			class="ext-checkuser-suggestedinvestigations-change-status-dialog-error-message"
			type="error"
		>
			{{ statusUpdateErrorMessage }}
		</cdx-message>
		<p class="ext-checkuser-suggestedinvestigations-change-status-dialog-description">
			{{ $i18n( 'checkuser-suggestedinvestigations-change-status-dialog-text' ).text() }}
		</p>
		<cdx-field
			:is-fieldset="true"
			class="ext-checkuser-suggestedinvestigations-change-status-dialog-status-radio"
		>
			<template #label>
				{{ $i18n(
					'checkuser-suggestedinvestigations-change-status-dialog-status-list-header'
				).text() }}
			</template>
			<cdx-radio
				v-for="radio in statusRadioOptions"
				:key="'radio-' + radio.value"
				v-model="selectedStatus"
				name="checkuser-suggestedinvestigations-change-status-dialog-status-option"
				:input-value="radio.value"
				@change="onFormFieldChange"
			>
				{{ radio.label }}
				<p
					v-if="radio.description !== null"
					class="cdx-label__description"
				>
					{{ radio.description }}
				</p>
			</cdx-radio>
		</cdx-field>
		<cdx-field
			v-if="showStatusReasonField"
			class="ext-checkuser-suggestedinvestigations-change-status-dialog-status-reason"
			:optional="true"
		>
			<template #label>
				{{ $i18n(
					'checkuser-suggestedinvestigations-change-status-dialog-status-reason-header'
				).text() }}
			</template>
			<p
				v-if="statusReasonSubtitle.value !== null"
				class="
					ext-checkuser-suggestedinvestigations-change-status-dialog-reason-description
				"
			>
				{{ statusReasonSubtitle }}
			</p>
			<character-limited-text-input
				v-model:text-content="statusReason"
				:byte-limit="255"
				class="
					ext-checkuser-suggestedinvestigations-change-status-dialog-status-reason__input
				"
				:placeholder="statusReasonPlaceholder"
				@change="onFormFieldChange"
			>
			</character-limited-text-input>
		</cdx-field>
		<div class="ext-checkuser-suggestedinvestigations-change-status-dialog-footer">
			<cdx-button
				class="
					ext-checkuser-suggestedinvestigations-change-status-dialog-footer__cancel-btn
				"
				@click="onCancelButtonClick"
			>
				{{ $i18n(
					'checkuser-suggestedinvestigations-change-status-dialog-cancel-btn'
				).text() }}
			</cdx-button>
			<cdx-button
				class="
					ext-checkuser-suggestedinvestigations-change-status-dialog-footer__submit-btn
				"
				weight="primary"
				action="progressive"
				@click="onSubmitButtonClick"
			>
				{{ $i18n(
					'checkuser-suggestedinvestigations-change-status-dialog-submit-btn'
				).text() }}
			</cdx-button>
		</div>
	</cdx-dialog>
</template>

<script>
const { ref, watch, computed } = require( 'vue' ),
	{ CdxButton, CdxDialog, CdxField, CdxRadio, CdxMessage } = require( '@wikimedia/codex' ),
	Constants = require( '../Constants.js' ),
	{ setCaseStatus } = require( '../rest.js' ),
	{ updateCaseStatusOnPage } = require( '../utils.js' ),
	CharacterLimitedTextInput = require( './CharacterLimitedTextInput.vue' );

// @vue/component
module.exports = exports = {
	name: 'ChangeInvestigationStatusDialog',
	components: {
		CdxButton,
		CdxDialog,
		CdxField,
		CdxRadio,
		CdxMessage,
		CharacterLimitedTextInput
	},
	props: {
		/**
		 * The case ID of the suggested investigations case that we are changing the status of.
		 */
		caseId: {
			type: Number,
			required: true
		},
		/**
		 * The selected status for the case being updated vid the dialog.
		 * This should be set to the current status when creating the component.
		 */
		initialStatus: {
			type: String,
			required: true
		},
		/**
		 * The reason given for the status of the case
		 * This should be set to the current reason when creating the component.
		 */
		initialStatusReason: {
			type: String,
			required: true
		}
	},
	setup( props ) {
		const open = ref( true );
		const formSubmissionInProgress = ref( false );
		const statusUpdateErrorMessage = ref( '' );

		const selectedStatus = ref( props.initialStatus );
		const statusReason = ref( props.initialStatusReason );

		const statusRadioOptions = ref( Constants.caseStatuses.map( ( status ) => {
			const statusOptions = {
				value: status,
				// Uses:
				// * checkuser-suggestedinvestigations-status-open
				// * checkuser-suggestedinvestigations-status-resolved
				// * checkuser-suggestedinvestigations-status-invalid
				label: mw.msg( 'checkuser-suggestedinvestigations-status-' + status ),
				description: null
			};
			if ( status === 'invalid' ) {
				statusOptions.description = mw.msg(
					'checkuser-suggestedinvestigations-status-description-invalid'
				);
			}
			return statusOptions;
		} ) );

		const statusReasonSubtitle = computed( () => {
			if ( selectedStatus.value === 'open' ) {
				return '';
			}

			// Uses:
			// * checkuser-suggestedinvestigations-change-status-dialog-reason-description-resolved
			// * checkuser-suggestedinvestigations-change-status-dialog-reason-description-invalid
			return mw.msg( 'checkuser-suggestedinvestigations-change-status-dialog-reason-description-' + selectedStatus.value );
		} );

		const statusReasonPlaceholder = computed( () => {
			if ( selectedStatus.value === 'open' ) {
				return '';
			}

			// Uses:
			// * checkuser-suggestedinvestigations-change-status-dialog-reason-placeholder-resolved
			// * checkuser-suggestedinvestigations-change-status-dialog-reason-placeholder-invalid
			return mw.msg( 'checkuser-suggestedinvestigations-change-status-dialog-reason-placeholder-' + selectedStatus.value );
		} );

		// Don't hide the reason field if the field has ever been non-empty.
		// This is so that a user can modify a reason that was already set before
		// and avoids the field disappearing unexpectedly if the user clears the input.
		const hasStatusReasonHadText = ref( statusReason.value !== '' );
		if ( !hasStatusReasonHadText.value ) {
			const unwatch = watch( statusReason, ( newStatusReason ) => {
				if ( newStatusReason !== '' ) {
					hasStatusReasonHadText.value = true;
					unwatch();
				}
			} );
		}

		const showStatusReasonField = computed( () => selectedStatus.value !== 'open' || hasStatusReasonHadText.value );

		/**
		 * Fired when any form fields have their value changed.
		 * Used to clear any error message set in the form.
		 */
		function onFormFieldChange() {
			statusUpdateErrorMessage.value = '';
		}

		function onCancelButtonClick() {
			open.value = false;
		}

		/**
		 * Handles a click of the submit button which includes making a REST API request
		 * to update the status of the investigation and displaying any errors if they occur.
		 */
		function onSubmitButtonClick() {
			// Ignore duplicate attempts to press the submit button to avoid race conditions
			if ( formSubmissionInProgress.value ) {
				return;
			}

			formSubmissionInProgress.value = true;
			statusUpdateErrorMessage.value = '';

			setCaseStatus( props.caseId, selectedStatus.value, statusReason.value )
				.then( ( data ) => {
					updateCaseStatusOnPage( props.caseId, data.status, data.reason );

					open.value = false;
					formSubmissionInProgress.value = false;
				} )
				.catch( ( err, errObject ) => {
					formSubmissionInProgress.value = false;

					let errMessage = errObject.exception;
					if (
						errObject.xhr &&
						errObject.xhr.responseJSON &&
						errObject.xhr.responseJSON.messageTranslations
					) {
						// If we have translated messages for this error, then try to find a
						// translation to display to the user using the language fallback chain
						// to find the most appropriate language that is defined
						const messageTranslations = errObject.xhr.responseJSON.messageTranslations;
						const fallbackChain = mw.language.getFallbackLanguageChain();

						for ( const language of fallbackChain ) {
							if ( messageTranslations[ language ] ) {
								errMessage = messageTranslations[ language ];
								break;
							}
						}
					}
					statusUpdateErrorMessage.value = errMessage;
				} );
		}

		return {
			open,
			selectedStatus,
			showStatusReasonField,
			statusReason,
			statusReasonSubtitle,
			statusReasonPlaceholder,
			statusRadioOptions,
			statusUpdateErrorMessage,
			onFormFieldChange,
			onCancelButtonClick,
			onSubmitButtonClick
		};
	}
};
</script>

<style lang="less">
@import ( reference ) 'mediawiki.skin.variables.less';

.ext-checkuser-suggestedinvestigations-change-status-dialog {
	.ext-checkuser-suggestedinvestigations-change-status-dialog-footer {
		float: right;

		.ext-checkuser-suggestedinvestigations-change-status-dialog-footer__cancel-btn {
			margin-right: @spacing-50;
		}
	}

	.ext-checkuser-suggestedinvestigations-change-status-dialog-reason-description,
	.ext-checkuser-suggestedinvestigations-change-status-description {
		margin-top: 0;
		color: @color-subtle;
	}

	.ext-checkuser-suggestedinvestigations-change-status-dialog-status-reason {
		margin-bottom: @spacing-150;
	}

	.ext-checkuser-suggestedinvestigations-change-status-dialog-error-message {
		padding-top: @spacing-75;
	}
}
</style>
