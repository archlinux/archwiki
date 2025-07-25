<template>
	<div class="ext-checkuser-temp-account-onboarding-dialog-stepper">
		<span class="ext-checkuser-temp-account-onboarding-dialog-stepper__label">
			{{ stepperLabelText }}
		</span>
		<div class="ext-checkuser-temp-account-onboarding-dialog-stepper__dots">
			<div
				v-for="step in [ ...Array( totalSteps ).keys() ]"
				:key="step"
				class="ext-checkuser-temp-account-onboarding-dialog-stepper__dots__dot"
				:class="{
					'ext-checkuser-temp-account-onboarding-dialog-stepper__dots__dot--active':
						step === ( modelValue - 1 )
				}"
			></div>
		</div>
	</div>
</template>

<script>
// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingStepper',
	props: {
		/** The current step (starts from 1) */
		modelValue: { type: Number, required: true },
		/** The total number of steps */
		totalSteps: { type: Number, required: true }
	},
	computed: {
		/**
		 * @return {string} The label shown next to the stepper icons describing
		 *    the current step and total steps
		 */
		stepperLabelText() {
			return this.$i18n(
				'checkuser-temporary-accounts-onboarding-dialog-stepper-label',
				mw.language.convertNumber( this.modelValue ),
				mw.language.convertNumber( this.totalSteps )
			).text();
		}
	}
};
</script>
