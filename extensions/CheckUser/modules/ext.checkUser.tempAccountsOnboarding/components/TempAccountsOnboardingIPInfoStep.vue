<template>
	<temp-accounts-onboarding-step
		step-name="ip-info"
		:image-aria-label="$i18n(
			'checkuser-temporary-accounts-onboarding-dialog-ip-info-step-image-aria-label'
		).text()"
	>
		<template #title>
			{{ $i18n(
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-step-title'
			).text() }}
		</template>
		<template #content>
			<!-- eslint-disable vue/no-v-html-->
			<p
				v-for="( paragraph, key ) in paragraphs"
				:key="key"
				v-html="paragraph"
			></p>
			<!-- eslint-enable vue/no-v-html-->
			<template v-if="shouldShowIPInfoPreference">
				<h6 class="ext-checkuser-temp-account-onboarding-dialog-ip-info-preference-title">
					{{ $i18n(
						'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-title'
					).text() }}
				</h6>
				<cdx-field
					:is-fieldset="true"
					class="ext-checkuser-temp-account-onboarding-dialog-ip-info-preference"
					:status="checkboxFieldErrorState"
					:messages="checkboxFieldMessages"
				>
					<cdx-checkbox
						:model-value="ipInfoPreferenceValue"
						@update:model-value="onPreferenceChange"
					>
						<span v-i18n-html="'ipinfo-preference-use-agreement'"></span>
					</cdx-checkbox>
				</cdx-field>
				<cdx-field
					class="ext-checkuser-temp-account-onboarding-dialog-ip-info-save-preference"
				>
					<cdx-button
						action="progressive"
						@click="onSavePreferenceButtonClick"
					>
						{{ $i18n(
							'checkuser-temporary-accounts-onboarding-dialog-ip-info-save-preference'
						).text() }}
					</cdx-button>
				</cdx-field>
			</template>
		</template>
	</temp-accounts-onboarding-step>
</template>

<script>

const { computed, ref } = require( 'vue' );
const { CdxCheckbox, CdxButton, CdxField } = require( '@wikimedia/codex' );
const TempAccountsOnboardingStep = require( './TempAccountsOnboardingStep.vue' );

// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingIPInfoStep',
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		TempAccountsOnboardingStep,
		CdxCheckbox,
		CdxButton,
		CdxField
	},
	setup( props, { expose } ) {
		// Hide the IPInfo preference checkbox if the user has already checked the preference.
		let initialIPInfoPreferenceValue;
		if ( mw.storage.session.get( 'mw-checkuser-ipinfo-preference-checked-status' ) ) {
			initialIPInfoPreferenceValue = mw.storage.session.get( 'mw-checkuser-ipinfo-preference-checked-status' );
		} else {
			initialIPInfoPreferenceValue = mw.user.options.get( 'ipinfo-use-agreement' ) !== '0' &&
				mw.user.options.get( 'ipinfo-use-agreement' ) !== 0;
		}
		const shouldShowIPInfoPreference = !initialIPInfoPreferenceValue &&
			mw.config.get( 'wgCheckUserUserHasIPInfoRight' );

		// Keep a track of variables needed to determine what message to show.
		/**
		 * False if no request has been made, empty string for successful request, and
		 * string for an error.
		 */
		const lastOptionsUpdateError = ref( false );
		const preferenceUpdateSuccessful = computed( () => lastOptionsUpdateError.value === '' );
		const attemptedToMoveWithoutPressingSave = ref( false );

		/**
		 * What type of message to show to the user underneath the IPInfo preference checkbox:
		 * * 'error' means that the preference failed to save after pressing the submit button
		 * * 'success' means that the preference saved after pressing the submit button
		 * * 'warning' means the user has attempted to leave this step without saving the
		 *     preference value using the submit button
		 * * 'default' means to display no text underneath the checkbox
		 */
		const checkboxFieldErrorState = computed( () => {
			if ( lastOptionsUpdateError.value ) {
				return 'error';
			}
			if ( preferenceUpdateSuccessful.value ) {
				return 'success';
			}
			if ( attemptedToMoveWithoutPressingSave.value ) {
				return 'warning';
			}
			return 'default';
		} );

		// Create the success, warning, and error messages for the user.
		const checkboxFieldMessages = computed( () => ( {
			error: mw.message(
				'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-error', lastOptionsUpdateError.value
			).text(),
			warning: mw.message( 'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-warning' ).text(),
			success: mw.message( 'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-success' ).text()
		} ) );

		// Keep a track of the value of the IPInfo preference on the client
		// and also separately server, along with any mismatch in these values.
		const ipInfoPreferenceValue = ref( initialIPInfoPreferenceValue );
		const serverIPInfoPreferenceValue = ref( initialIPInfoPreferenceValue );
		const preferenceCheckboxStateNotYetSaved = computed(
			() => ipInfoPreferenceValue.value !== serverIPInfoPreferenceValue.value
		);
		const ipInfoPreferenceUpdateInProgress = ref( false );

		/**
		 * Handles a click of the "Save preference" button.
		 */
		function onSavePreferenceButtonClick() {
			// Ignore duplicate clicks of the button to avoid race conditions
			if ( ipInfoPreferenceUpdateInProgress.value ) {
				return;
			}

			ipInfoPreferenceUpdateInProgress.value = true;
			const newPreferenceValue = ipInfoPreferenceValue.value;
			const api = new mw.Api();
			api.saveOption( 'ipinfo-use-agreement', newPreferenceValue ? 1 : 0 ).then(
				() => {
					ipInfoPreferenceUpdateInProgress.value = false;
					lastOptionsUpdateError.value = '';
					serverIPInfoPreferenceValue.value = newPreferenceValue;
					mw.storage.session.set(
						'mw-checkuser-ipinfo-preference-checked-status', newPreferenceValue ? 'checked' : ''
					);
				},
				( error, result ) => {
					ipInfoPreferenceUpdateInProgress.value = false;
					// Display a user-friendly error message if we have it,
					// otherwise use the error code.
					if ( result && result.error && result.error.info ) {
						lastOptionsUpdateError.value = result.error.info;
					} else {
						lastOptionsUpdateError.value = error;
					}
				}
			);
		}

		/**
		 * Handles when the IPInfo preference checkbox is checked or unchecked.
		 *
		 * @param {boolean} newValue Whether the preference checkbox is checked
		 */
		function onPreferenceChange( newValue ) {
			// Set the ref value to indicate the new state
			ipInfoPreferenceValue.value = newValue;
			lastOptionsUpdateError.value = false;
			attemptedToMoveWithoutPressingSave.value = false;
		}

		// Parse the message as would be for content in a page, such that two newlines creates a new
		// paragraph block.
		const paragraphs = mw.message(
			'checkuser-temporary-accounts-onboarding-dialog-ip-info-step-content'
		).parse().split( '\n\n' );

		/**
		 * Returns whether this step will allow dialog to navigate to another step.
		 *
		 * Used to warn the user if they have not saved the changes to the IPInfo
		 * preference checkbox.
		 *
		 * @return {boolean}
		 */
		function canMoveToAnotherStep() {
			const returnValue = !preferenceCheckboxStateNotYetSaved.value ||
				!!lastOptionsUpdateError.value;
			attemptedToMoveWithoutPressingSave.value = !returnValue;
			return returnValue;
		}

		/**
		 * Used to indicate if the user should be warned before they close the dialog,
		 * so that they can be alerted if they have not saved the changes to the
		 * IPInfo preference.
		 *
		 * @return {boolean}
		 */
		function shouldWarnBeforeClosingDialog() {
			const returnValue = preferenceCheckboxStateNotYetSaved.value &&
				!lastOptionsUpdateError.value;
			attemptedToMoveWithoutPressingSave.value = returnValue;
			return returnValue;
		}

		// Expose method to check if we can move to another step so that the dialog
		// can call it.
		expose( { canMoveToAnotherStep, shouldWarnBeforeClosingDialog } );
		return {
			checkboxFieldErrorState,
			checkboxFieldMessages,
			shouldShowIPInfoPreference,
			ipInfoPreferenceValue,
			onPreferenceChange,
			onSavePreferenceButtonClick,
			paragraphs
		};
	}
};
</script>
