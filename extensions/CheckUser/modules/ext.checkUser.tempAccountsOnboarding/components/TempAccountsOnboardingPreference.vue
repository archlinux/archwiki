<template>
	<h6 class="ext-checkuser-temp-account-onboarding-dialog-preference-title">
		{{ sectionTitle }}
	</h6>
	<div
		v-if="checkboxDescriptionMessageKey !== ''"
		class="ext-checkuser-temp-account-onboarding-dialog-preference-description"
	>
		<!-- eslint-disable vue/no-v-html-->
		<p
			v-for="( paragraph, key ) in parseWithParagraphBreaks( checkboxDescriptionMessageKey )"
			:key="key"
			v-html="paragraph"
		></p>
		<!-- eslint-enable vue/no-v-html-->
	</div>
	<cdx-field
		:is-fieldset="true"
		class="ext-checkuser-temp-account-onboarding-dialog-preference"
		:status="checkboxFieldErrorState"
		:messages="checkboxFieldMessages"
	>
		<cdx-checkbox
			v-for="( checkbox ) in checkboxes"
			:key="checkbox.name"
			:model-value="preferenceValues[ checkbox.name ].isChecked"
			:disabled="checkboxDisabledStates[ checkbox.name ]"
			@update:model-value="newIsChecked =>
				onPreferencesChange( checkbox.name, newIsChecked )"
		>
			<span v-i18n-html="checkbox.checkboxMessageKey"></span>
		</cdx-checkbox>
	</cdx-field>
	<cdx-field class="ext-checkuser-temp-account-onboarding-dialog-save-preference">
		<cdx-button
			action="progressive"
			@click="onSavePreferenceButtonClick"
		>
			{{ savePreferenceButtonText }}
		</cdx-button>
	</cdx-field>
	<!-- eslint-disable vue/no-v-html-->
	<p
		v-if="preferencePostscript !== ''"
		class="ext-checkuser-temp-account-onboarding-dialog-preference-postscript"
		v-html="preferencePostscript"
	></p>
	<!-- eslint-enable vue/no-v-html-->
</template>

<script>

const { computed, ref } = require( 'vue' );
const { CdxCheckbox, CdxButton, CdxField } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingPreference',
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		CdxCheckbox,
		CdxButton,
		CdxField
	},
	props: {
		/** The message key for the text above the preference checkbox (optional) */
		checkboxDescriptionMessageKey: { type: String, required: false, default: '' },
		/** The text used as the section title displayed above the preference */
		sectionTitle: { type: String, required: true },
		/** The message key for the text below the preference checkbox (optional) */
		preferencePostscript: { type: String, required: false, default: '' },
		/**
		 * An array of objects where each object contains all the information needed
		 * to generate a preferences checkbox:
		 *   - name: name of the preference to be updated
		 *   - initialIsChecked: the initial checked state of the checkbox
		 *   - checkboxMessageKey: the key of the message used as the checkbox label
		 *   - checkedStatusStorageKey: the key for the value that's saved to session storage
		 *                              after an update
		 *   - setValue: an object determining that value to update the preference to
		 *               depending on whether or not the checkbox is checked. It should
		 *               be set like: { checked: 1, unchecked 0 }
		 *   - isDisabled: an object that declares parameters to check against while rendered
		 *                 in order to disable/enable the checkbox. It should be set like:
		 *                 { otherCheckboxName: { checkboxProperty: bool } }
		 *                 Currently, only the isChecked checkboxProperty is supported.
		 */
		checkboxes: { type: Array, required: true }
	},
	setup( props, { expose } ) {
		/**
		 * False if no request has been made, empty string for successful request, and
		 * string for an error.
		 */
		const lastOptionsUpdateError = ref( false );
		const preferenceUpdateSuccessful = computed( () => lastOptionsUpdateError.value === '' );
		const attemptedToMoveWithoutPressingSave = ref( false );

		let savePreferenceButtonText;
		if ( mw.config.get( 'wgCheckUserGlobalPreferencesExtensionLoaded' ) ) {
			savePreferenceButtonText = mw.msg(
				'checkuser-temporary-accounts-onboarding-dialog-save-global-preference'
			);
		} else {
			savePreferenceButtonText = mw.msg(
				'checkuser-temporary-accounts-onboarding-dialog-save-preference'
			);
		}

		/**
		 * What type of message to show to the user underneath the preference checkbox:
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
			error: mw.msg(
				'checkuser-temporary-accounts-onboarding-dialog-preference-error', lastOptionsUpdateError.value
			),
			warning: mw.msg(
				'checkuser-temporary-accounts-onboarding-dialog-preference-warning', savePreferenceButtonText
			),
			success: mw.msg( 'checkuser-temporary-accounts-onboarding-dialog-preference-success' )
		} ) );

		// Keep track of the value of the preference value on the client
		// and also separately server, along with any mismatch in these values.
		const preferenceUpdateInProgress = ref( false );
		const preferenceValues = ref( {} );
		const serverPreferenceValues = ref( {} );
		props.checkboxes.forEach( ( checkbox ) => {
			preferenceValues.value[ checkbox.name ] = {
				isChecked: checkbox.initialIsChecked
			};
			serverPreferenceValues.value[ checkbox.name ] = {
				isChecked: checkbox.initialIsChecked
			};
		} );

		/**
		 * Compare a checkbox's `isDisabled` parameters to the current state
		 * of the checkboxes and track whether or not the checkbox should be disabled.
		 * This depends on props.checkboxes to describe what checkboxes are
		 * available to check against and prefereceValues.value to describe the
		 * current state of those checkboxes.
		 */
		const checkboxDisabledStates = computed( () => {
			const isDisabled = {};

			for ( const thisCheckbox of props.checkboxes ) {
				const name = thisCheckbox.name;
				const disabledParams = thisCheckbox.isDisabled;

				// Default to enabled
				isDisabled[ name ] = false;

				if ( !disabledParams ) {
					// No parameters to check, checkbox is always enabled
					continue;
				}

				// Gather all the requirement checks. All need to pass for this check to return true
				const requirements = [];

				// Check against each parameter in each checkbox
				for ( const checkboxName in disabledParams ) {
					const checkbox = preferenceValues.value[ checkboxName ];
					if ( !checkbox ) {
						continue;
					}

					for ( const requirement in disabledParams[ checkboxName ] ) {
						requirements.push(
							checkbox[ requirement ] ===
							disabledParams[ checkboxName ][ requirement ]
						);
					}
				}

				isDisabled[ name ] = requirements.reduce( ( acc, curr ) => acc && curr, true );
			}

			return isDisabled;
		} );

		const preferenceCheckboxStatesNotYetSaved = computed(
			() => props.checkboxes.some( ( checkbox ) => (
				( preferenceValues.value[ checkbox.name ].isChecked !==
				serverPreferenceValues.value[ checkbox.name ].isChecked ) &&
				!checkboxDisabledStates.value[ checkbox.name ]
			) )
		);

		/**
		 * Handles a click of the "Save preference" button.
		 */
		function onSavePreferenceButtonClick() {
			// Ignore duplicate clicks of the button to avoid race conditions
			if ( preferenceUpdateInProgress.value ) {
				return;
			}

			preferenceUpdateInProgress.value = true;
			const newPreferenceValues = {};
			for ( const key in preferenceValues.value ) {
				// Convert the checkbox checked status to values to save to preferences
				if ( Object.prototype.hasOwnProperty.call( preferenceValues.value, key ) ) {
					const checkboxValues = props.checkboxes
						.find( ( checkbox ) => checkbox.name === key )
						.setValue;
					if ( !checkboxValues ) {
						continue;
					}

					// Like in an HTML form if a checkbox is disabled, its value shouldn't be used
					if ( checkboxDisabledStates.value[ key ] ) {
						continue;
					}

					let newValue = preferenceValues.value[ key ].isChecked ?
						checkboxValues.checked : checkboxValues.unchecked;

					// Dynamically calculated values are supported
					if ( typeof newValue === 'function' ) {
						newValue = newValue();
					}

					newPreferenceValues[ key ] = newValue;
				}
			}
			const api = new mw.Api();
			api.saveOptions( newPreferenceValues, { global: 'create' } ).then(
				() => {
					preferenceUpdateInProgress.value = false;
					lastOptionsUpdateError.value = '';
					serverPreferenceValues.value = preferenceValues.value;
					for ( const key in newPreferenceValues ) {
						if ( Object.prototype.hasOwnProperty.call( newPreferenceValues, key ) ) {
							const storageKey = props.checkboxes
								.find( ( checkbox ) => checkbox.name === key )
								.checkedStatusStorageKey;
							if ( !storageKey ) {
								return;
							}
							mw.storage.session.set(
								storageKey,
								newPreferenceValues[ key ] ? 'checked' : ''
							);
						}
					}
				},
				( error, result ) => {
					preferenceUpdateInProgress.value = false;
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
		 * Save the state of the checkboxes on the step
		 *
		 * @param {string} key Key of the preference checkbox to update
		 * @param {boolean} newIsChecked Whether the preference checkbox is checked
		 */
		function onPreferencesChange( key, newIsChecked ) {
			// Set the ref value to indicate the new state
			preferenceValues.value[ key ].isChecked = newIsChecked;

			// A checkbox's state change can affect another checkbox's disabled
			// state. Check for that here and force an uncheck of those checkboxes
			// in order to comply with HTML spec
			for ( const checkboxName in checkboxDisabledStates.value ) {
				// Convert the checkbox checked status to values to save to preferences
				if ( Object.prototype.hasOwnProperty.call( preferenceValues.value, key ) ) {
					if ( checkboxDisabledStates.value[ checkboxName ] ) {
						preferenceValues.value[ checkboxName ].isChecked = false;
					}
				}
			}

			lastOptionsUpdateError.value = false;
			attemptedToMoveWithoutPressingSave.value = false;
		}

		/**
		 * Returns whether this step will allow dialog to navigate to another step.
		 *
		 * Used to warn the user if they have not saved the changes to the
		 * preference checkbox.
		 *
		 * @return {boolean}
		 */
		function canMoveToAnotherStep() {
			const returnValue = !preferenceCheckboxStatesNotYetSaved.value ||
				!!lastOptionsUpdateError.value;
			attemptedToMoveWithoutPressingSave.value = !returnValue;
			return returnValue;
		}

		/**
		 * Used to indicate if the user should be warned before they close the dialog,
		 * so that they can be alerted if they have not saved the changes to the
		 * preference.
		 *
		 * @return {boolean}
		 */
		function shouldWarnBeforeClosingDialog() {
			const returnValue = preferenceCheckboxStatesNotYetSaved.value &&
				!lastOptionsUpdateError.value;
			attemptedToMoveWithoutPressingSave.value = returnValue;
			return returnValue;
		}

		/**
		 * Manually process the paragraph breaks in messages
		 *
		 * @param {string} messageKey
		 * @return {string[]} - array of strings split on the double newline
		 */
		function parseWithParagraphBreaks( messageKey ) {
			// * wikimedia-checkuser-tempaccount-enable-preference-description
			// * HACK: linter gets mad if only one message key is present
			return mw.message( messageKey ).parse().split( '\n\n' );
		}
		// Expose method to check if we can move to another step so that the step can expose this
		// to the overall dialog component.
		expose( { canMoveToAnotherStep, shouldWarnBeforeClosingDialog } );
		return {
			checkboxFieldErrorState,
			checkboxFieldMessages,
			savePreferenceButtonText,
			onSavePreferenceButtonClick,
			parseWithParagraphBreaks,
			preferenceValues,
			onPreferencesChange,
			checkboxDisabledStates
		};
	}
};
</script>
