<template>
	<cdx-dialog
		:open="dialogOpen"
		class="ext-checkuser-temp-account-onboarding-dialog"
		:title="$i18n( 'checkuser-temporary-accounts-onboarding-dialog-title' ).text()"
		:hide-title="true"
		@update:open="onUpdateOpen"
	>
		<!-- Dialog Header -->
		<template #header>
			<div class="ext-checkuser-temp-account-onboarding-dialog__header">
				<div class="ext-checkuser-temp-account-onboarding-dialog__header__top">
					<h4 class="ext-checkuser-temp-account-onboarding-dialog__header__top__title">
						{{ $i18n( 'checkuser-temporary-accounts-onboarding-dialog-title' ).text() }}
					</h4>
					<cdx-button
						class="ext-checkuser-temp-account-onboarding-dialog__header__top__button"
						weight="quiet"
						@click="onFinish"
					>
						{{ $i18n(
							'checkuser-temporary-accounts-onboarding-dialog-skip-all'
						).text() }}
					</cdx-button>
				</div>
				<temp-accounts-onboarding-stepper
					v-model:model-value="currentStep"
					class="ext-checkuser-temp-account-onboarding-dialog__header__stepper"
					:total-steps="steps.length"
				></temp-accounts-onboarding-stepper>
			</div>
		</template>
		<!-- Dialog Content -->
		<div>
			<div
				ref="stepWrapperRef"
				class="ext-checkuser-temp-account-onboarding-dialog-content"
				@touchstart="onTouchStart"
				@touchmove="onTouchMove">
				<transition :name="computedTransitionName">
					<slot :name="currentSlotName"></slot>
				</transition>
			</div>
		</div>
		<!-- Dialog Footer -->
		<template #footer>
			<div class="ext-checkuser-temp-account-onboarding-dialog__footer">
				<div class="ext-checkuser-temp-account-onboarding-dialog__footer__navigation">
					<cdx-button
						v-if="currentStep !== 1"
						class="
							ext-checkuser-temp-account-onboarding-dialog__footer__navigation--prev
						"
						:aria-label="$i18n(
							'checkuser-temporary-accounts-onboarding-dialog-previous-label'
						).text()"
						@click="navigatePrev"
					>
						<cdx-icon
							:icon="cdxIconPrevious"
							:icon-label="$i18n(
								'checkuser-temporary-accounts-onboarding-dialog-previous-label'
							).text()"
						></cdx-icon>
					</cdx-button>
					<cdx-button
						v-if="currentStep === steps.length"
						weight="primary"
						action="progressive"
						class="
							ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next
						"
						@click="onFinish"
					>
						{{ $i18n(
							'checkuser-temporary-accounts-onboarding-dialog-close-label'
						).text() }}
					</cdx-button>
					<cdx-button
						v-else
						weight="primary"
						action="progressive"
						:aria-label="$i18n(
							'checkuser-temporary-accounts-onboarding-dialog-next-label'
						).text()"
						class="
							ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next
						"
						@click="navigateNext"
					>
						<cdx-icon
							:icon="cdxIconNext"
							:icon-label="$i18n(
								'checkuser-temporary-accounts-onboarding-dialog-next-label'
							).text()"
						></cdx-icon>
					</cdx-button>
				</div>
			</div>
		</template>
	</cdx-dialog>
</template>

<script>

const { ref, computed, watch } = require( 'vue' );
const { CdxDialog, CdxButton, CdxIcon, useComputedDirection } = require( '@wikimedia/codex' );
const { cdxIconNext, cdxIconPrevious } = require( './icons.json' );
const TempAccountsOnboardingStepper = require( './TempAccountsOnboardingStepper.vue' );
const TRANSITION_NAMES = {
	LEFT: 'ext-checkuser-temp-account-onboarding-left',
	RIGHT: 'ext-checkuser-temp-account-onboarding-right'
};

/**
 * The Temporary Accounts onboarding dialog component. This defines the structure of the dialog and
 * the user of the component will define the content.
 */
// @vue/component
module.exports = exports = {
	name: 'TempAccountsOnboardingDialog',
	components: {
		CdxDialog,
		CdxButton,
		CdxIcon,
		TempAccountsOnboardingStepper
	},
	props: {
		/**
		 * An array of the objects that describe each step for the dialog.
		 * Each object should contain at least the step name. It may contain
		 * a Vue ref to the component that is the step.
		 *
		 * @type {Array.<{name: string, ref: ref}>}
		 */
		steps: {
			type: Object,
			required: true
		}
	},
	setup( props ) {
		const dialogOpen = ref( true );

		// Work out the slot name based on the current step.
		const currentStep = ref( 1 );
		const currentSlotName = computed( () => props.steps[ currentStep.value - 1 ].name );

		// Work out whether the page is rtl or ltr. Needed to decide which
		// direction the animation for the step transition should go.
		const stepWrapperRef = ref( null );
		const computedDir = useComputedDirection( stepWrapperRef );
		const isRtl = computed( () => computedDir.value === 'rtl' );

		// Set up the variables needed to perform navigation and also
		// the associated animations.
		const initialX = ref( null );
		const currentNavigation = ref( null );
		const computedTransitionSet = computed( () => isRtl.value ?
			{ next: TRANSITION_NAMES.LEFT, prev: TRANSITION_NAMES.RIGHT } :
			{ next: TRANSITION_NAMES.RIGHT, prev: TRANSITION_NAMES.LEFT } );
		const computedTransitionName = computed(
			() => computedTransitionSet.value[ currentNavigation.value ]
		);

		/**
		 * Returns whether the dialog should allow a user to navigate to another step.
		 * Value is controlled by the current step.
		 *
		 * @return {boolean}
		 */
		function canMoveToAnotherStep() {
			const currentStepRef = props.steps[ currentStep.value - 1 ].ref;
			return !currentStepRef ||
				currentStepRef.value === null ||
				!( 'canMoveToAnotherStep' in currentStepRef.value ) ||
				currentStepRef.value.canMoveToAnotherStep();
		}

		/**
		 * Method used to navigate forward.
		 *
		 * Does nothing if the current step is the last defined step,
		 * or if the current step is preventing moving to another step.
		 */
		function navigateNext() {
			if ( currentStep.value < props.steps.length && canMoveToAnotherStep() ) {
				currentNavigation.value = 'next';
				currentStep.value++;
			}
		}

		/**
		 * Method used to navigate backwards.
		 *
		 * Does nothing if the current step is the first step,
		 * or if the current step is preventing moving to another step.
		 */
		function navigatePrev() {
			if ( currentStep.value > 1 && canMoveToAnotherStep() ) {
				currentNavigation.value = 'prev';
				currentStep.value--;
			}
		}

		/**
		 * Handles a user starting a touch on their screen.
		 * Used to allow a user to navigate using a swipe of
		 * their screen.
		 *
		 * @param {TouchEvent} e
		 */
		function onTouchStart( e ) {
			const touchEvent = e.touches[ 0 ];
			initialX.value = touchEvent.clientX;
		}

		/**
		 * Return if the touch movement was a
		 * swipe to the left of the screen.
		 *
		 * @param {Touch} touch
		 * @return {boolean}
		 */
		const isSwipeToLeft = ( touch ) => {
			const newX = touch.clientX;
			return initialX.value > newX;
		};

		/**
		 * Handles a user swiping to the right
		 */
		const onSwipeToRight = () => {
			if ( isRtl.value === true ) {
				navigateNext();
			} else {
				navigatePrev();
			}
		};

		/**
		 * Handles a user swiping to the left
		 */
		const onSwipeToLeft = () => {
			if ( isRtl.value === true ) {
				navigatePrev();
			} else {
				navigateNext();
			}
		};

		/**
		 * Handles a user finishing a touch where there was
		 * a movement in a direction.
		 * Used to allow a user to navigate using a swipe of
		 * their screen.
		 *
		 * @param {TouchEvent} e
		 */
		function onTouchMove( e ) {
			if ( !initialX.value ) {
				return;
			}
			if ( isSwipeToLeft( e.touches[ 0 ] ) ) {
				onSwipeToLeft();
			} else {
				onSwipeToRight();
			}
			initialX.value = null;
		}

		/**
		 * Returns whether the dialog should ignore an attempt to close the dialog
		 * on the first attempt to do so.
		 *
		 * Used to warn a user about unsaved changes in a dialog step.
		 *
		 * @return {boolean}
		 */
		function shouldWarnBeforeClosingDialog() {
			const currentStepRef = props.steps[ currentStep.value - 1 ].ref;
			return currentStepRef &&
				currentStepRef.value !== null &&
				'shouldWarnBeforeClosingDialog' in currentStepRef.value &&
				currentStepRef.value.shouldWarnBeforeClosingDialog();
		}

		// Keep a track of whether we have warned the user when they attempted
		// to close the dialog. If the step is moved, then we reset this as
		// each step may have a different warning.
		const warnedUserBeforeClosingDialog = ref( false );
		watch( currentStep, () => {
			warnedUserBeforeClosingDialog.value = false;
		} );

		/**
		 * Handles a close of the dialog when the dialog should not be seen again.
		 * The dialog is hidden for the user in future page loads via setting a
		 * preference.
		 *
		 * The close is prevented if the current step indicates that a warning should
		 * be displayed before closing the dialog.
		 */
		function onFinish() {
			if ( shouldWarnBeforeClosingDialog() && !warnedUserBeforeClosingDialog.value ) {
				warnedUserBeforeClosingDialog.value = true;
				return;
			}
			const api = new mw.Api();
			api.saveOption( 'checkuser-temporary-accounts-onboarding-dialog-seen', 1 );
			dialogOpen.value = false;
		}

		/**
		 * Handle an attempted change to the open status of the dialog
		 * via an "Escape" button press or clicking outside the dialog.
		 *
		 * Using this method means that the dialog will be shown to the
		 * user again on a new page load. This is done to avoid the user
		 * not seeing the dialog if they accidentally close it.
		 *
		 * @param {boolean} newVal The new open status of the dialog
		 */
		function onUpdateOpen( newVal ) {
			if ( shouldWarnBeforeClosingDialog() && !warnedUserBeforeClosingDialog.value ) {
				warnedUserBeforeClosingDialog.value = true;
				return;
			}
			dialogOpen.value = newVal;
		}

		return {
			cdxIconNext,
			cdxIconPrevious,
			currentStep,
			computedTransitionName,
			currentSlotName,
			onTouchStart,
			onTouchMove,
			stepWrapperRef,
			navigateNext,
			navigatePrev,
			onFinish,
			dialogOpen,
			onUpdateOpen
		};
	}
};
</script>
