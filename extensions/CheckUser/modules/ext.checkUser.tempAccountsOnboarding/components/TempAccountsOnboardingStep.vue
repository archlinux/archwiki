<template>
	<div class="ext-checkuser-temp-account-onboarding-dialog-step">
		<div
			:class="imageClasses"
			role="img"
			:aria-label="imageAriaLabel">
		</div>
		<div class="ext-checkuser-temp-account-onboarding-dialog-main-body">
			<h5>
				<slot name="title"></slot>
			</h5>
			<div class="ext-checkuser-temp-account-onboarding-dialog-content">
				<slot name="content"></slot>
			</div>
		</div>
	</div>
</template>

<script>
/**
 * A component to define the structure of a step in the Temporary Accounts
 * onboarding dialog.
 */
// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingStep',
	props: {
		/** Aria label for the image shown in the step */
		imageAriaLabel: { type: String, default: '' },
		/** The name of the step used to generate the image to be shown in the step */
		stepName: { type: String, required: true }
	},
	computed: {
		/**
		 * @return {string} The CSS classes for the image element
		 */
		imageClasses() {
			return 'ext-checkuser-temp-account-onboarding-dialog-image ' +
				'ext-checkuser-image-temp-accounts-onboarding-' + this.stepName;
		}
	}
};
</script>
