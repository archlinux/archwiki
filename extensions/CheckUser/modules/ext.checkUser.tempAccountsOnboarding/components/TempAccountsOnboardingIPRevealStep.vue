<template>
	<temp-accounts-onboarding-step
		step-name="ip-reveal"
		:image-aria-label="$i18n(
			'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-image-aria-label'
		).text()"
	>
		<template #title>
			{{ $i18n(
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-title'
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
			<cdx-message
				v-if="preferenceNotice !== ''"
				class="ext-checkuser-temp-account-onboarding-dialog-preference-notice"
				inline
			>
				{{ preferenceNotice }}
			</cdx-message>
			<temp-accounts-onboarding-preference
				v-if="shouldShowIPRevealPreference"
				ref="preferenceRef"
				:checkbox-description-message-key="checkboxDescriptionMessageKey"
				:section-title="preferenceSectionTitle"
				:preference-postscript="preferencePostscript"
				:checkboxes="checkboxes"
			></temp-accounts-onboarding-preference>
		</template>
	</temp-accounts-onboarding-step>
</template>

<script>

const { ref } = require( 'vue' );
const TempAccountsOnboardingStep = require( './TempAccountsOnboardingStep.vue' );
const TempAccountsOnboardingPreference = require( './TempAccountsOnboardingPreference.vue' );
const { CdxMessage } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingIPRevealStep',
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		TempAccountsOnboardingStep,
		TempAccountsOnboardingPreference,
		CdxMessage
	},
	setup( props, { expose } ) {
		// Hide the IP reveal preference checkbox if the user has already checked the preference,
		// and display some help text to indicate that the user already enabled the preference
		// globally or locally.
		let initialIPRevealPreferenceValue;
		if ( mw.storage.session.get( 'mw-checkuser-ip-reveal-preference-checked-status' ) ) {
			initialIPRevealPreferenceValue = mw.storage.session.get( 'mw-checkuser-ip-reveal-preference-checked-status' );
		} else {
			initialIPRevealPreferenceValue = mw.config.get( 'wgCheckUserIPRevealPreferenceGloballyChecked' );
		}
		const shouldShowIPRevealPreference = !initialIPRevealPreferenceValue;

		let preferenceNotice = '';
		if ( mw.config.get( 'wgCheckUserGlobalPreferencesExtensionLoaded' ) ) {
			if ( initialIPRevealPreferenceValue ) {
				preferenceNotice = mw.msg(
					'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-globally-enabled'
				);
			} else if ( mw.config.get( 'wgCheckUserIPRevealPreferenceLocallyChecked' ) ) {
				preferenceNotice = mw.msg(
					'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-locally-enabled'
				);
			}
		} else if ( initialIPRevealPreferenceValue ) {
			preferenceNotice = mw.msg(
				'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-locally-enabled'
			);
		}

		const preferenceRef = ref( null );
		const checkboxDescriptionMessageKey = 'checkuser-tempaccount-enable-preference-description';

		// Construct the message keys for the step content, which are different if the
		// GlobalPreferences extension is installed.
		let contentMessageKey = 'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content';
		let preferenceSectionTitleMessageKey = 'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-title';
		let ipRevealCheckboxMessageKey = 'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-checkbox-text';
		let preferencePostscriptKey = 'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-postscript-text';
		let preferencePostscript = '';
		if ( mw.config.get( 'wgCheckUserGlobalPreferencesExtensionLoaded' ) ) {
			contentMessageKey += '-with-global-preferences';
			preferenceSectionTitleMessageKey += '-with-global-preferences';
			ipRevealCheckboxMessageKey += '-with-global-preferences';
			preferencePostscriptKey += '-with-global-preferences';
		}

		if ( mw.config.get( 'wgCheckUserTemporaryAccountAutoRevealPossible' ) ) {
			// Message contains a duration and needs to be translated via Message::durationParams
			preferencePostscript = require( './../defaultAutoRevealDuration.json' );
		}

		// Otherwise preferencePostscript can be translated normally
		if ( !preferencePostscript ) {
			// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-postscript-text
			// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-postscript-text-with-global-preferences
			preferencePostscript = mw.message( preferencePostscriptKey ).parse();
		}

		// Parse the message as would be for content in a page, such that two newlines creates a new
		// paragraph block.
		// Uses:
		// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content-base
		// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content
		// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content-with-global-preferences
		const paragraphs = mw.message( contentMessageKey ).parse().split( '\n\n' );

		// Uses:
		// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-title
		// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-title-with-global-preferences
		const preferenceSectionTitle = mw.msg( preferenceSectionTitleMessageKey );

		const checkboxes = [
			{
				checkedStatusStorageKey: 'mw-checkuser-ip-reveal-preference-checked-status',
				// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-checkbox-text
				// * checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-checkbox-text-with-global-preferences
				//     * uses: checkuser-tempaccount-reveal-ip-button-label
				checkboxMessageKey: ipRevealCheckboxMessageKey,
				initialIsChecked: initialIPRevealPreferenceValue,
				name: 'checkuser-temporary-account-enable',
				setValue: {
					checked: 1,
					unchecked: 0
				}
			}
		];

		if ( mw.config.get( 'wgCheckUserTemporaryAccountAutoRevealPossible' ) ) {
			checkboxes.push(
				{
					checkedStatusStorageKey: 'mw-checkuser-ip-autoreveal-preference-checked-status',
					checkboxMessageKey: 'checkuser-temporary-accounts-onboarding-dialog-ip-autoreveal-preference-checkbox-text',
					initialIsChecked: false,
					name: 'checkuser-temporary-account-enable-auto-reveal',
					setValue: {
						checked: () => Math.floor( Date.now() / 1000 ) + mw.config.get( 'wgCheckUserAutoRevealMaximumExpiry' ),
						unchecked: null
					},
					isDisabled: {
						'checkuser-temporary-account-enable': {
							isChecked: false
						}
					}
				}
			);
		}

		/**
		 * Returns whether this step will allow dialog to navigate to another step.
		 *
		 * Used to warn the user if they have not saved the changes to the IPInfo
		 * preference checkbox.
		 *
		 * @return {boolean}
		 */
		function canMoveToAnotherStep() {
			return preferenceRef.value === null ||
				preferenceRef.value.canMoveToAnotherStep();
		}

		/**
		 * Used to indicate if the user should be warned before they close the dialog,
		 * so that they can be alerted if they have not saved the changes to the
		 * IPInfo preference.
		 *
		 * @return {boolean}
		 */
		function shouldWarnBeforeClosingDialog() {
			return preferenceRef.value !== null &&
				preferenceRef.value.shouldWarnBeforeClosingDialog();
		}

		// Expose method to check if we can move to another step so that the dialog
		// can call it.
		expose( { canMoveToAnotherStep, shouldWarnBeforeClosingDialog } );
		return {
			paragraphs,
			preferenceNotice,
			preferenceRef,
			preferenceSectionTitle,
			checkboxDescriptionMessageKey,
			shouldShowIPRevealPreference,
			preferencePostscript,
			checkboxes
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-temp-account-onboarding-dialog-preference-postscript {
	padding-top: @spacing-100;
	font-size: @font-size-small;
}
</style>
