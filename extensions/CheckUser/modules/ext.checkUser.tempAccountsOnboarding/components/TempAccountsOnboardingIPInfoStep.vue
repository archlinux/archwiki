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
			<temp-accounts-onboarding-preference
				v-if="shouldShowIPInfoPreference"
				ref="preferenceRef"
				:section-title="$i18n(
					'checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-title'
				).text()"
				:checkboxes="checkboxes"
			></temp-accounts-onboarding-preference>
		</template>
	</temp-accounts-onboarding-step>
</template>

<script>

const { ref } = require( 'vue' );
const TempAccountsOnboardingStep = require( './TempAccountsOnboardingStep.vue' );
const TempAccountsOnboardingPreference = require( './TempAccountsOnboardingPreference.vue' );

// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingIPInfoStep',
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		TempAccountsOnboardingStep,
		TempAccountsOnboardingPreference
	},
	setup( props, { expose } ) {
		// Hide the IPInfo preference checkbox if the user has already checked the preference.
		let initialIPInfoPreferenceValue;
		if ( mw.storage.session.get( 'mw-checkuser-ipinfo-preference-checked-status' ) ) {
			initialIPInfoPreferenceValue = mw.storage.session.get( 'mw-checkuser-ipinfo-preference-checked-status' );
		} else {
			initialIPInfoPreferenceValue = mw.config.get( 'wgCheckUserIPInfoPreferenceChecked' );
		}
		const shouldShowIPInfoPreference = !initialIPInfoPreferenceValue &&
			mw.config.get( 'wgCheckUserUserHasIPInfoRight' );

		const preferenceRef = ref( null );

		// Parse the message as would be for content in a page, such that two newlines creates a new
		// paragraph block.
		const paragraphs = mw.message(
			'checkuser-temporary-accounts-onboarding-dialog-ip-info-step-content'
		).parse().split( '\n\n' );

		const checkboxes = [
			{
				checkedStatusStorageKey: 'mw-checkuser-ipinfo-preference-checked-status',
				checkboxMessageKey: 'ipinfo-preference-use-agreement',
				initialValue: initialIPInfoPreferenceValue,
				name: 'ipinfo-use-agreement',
				setValue: {
					checked: 1,
					unchecked: 0
				}
			}
		];

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
			shouldShowIPInfoPreference,
			paragraphs,
			preferenceRef,
			checkboxes
		};
	}
};
</script>
