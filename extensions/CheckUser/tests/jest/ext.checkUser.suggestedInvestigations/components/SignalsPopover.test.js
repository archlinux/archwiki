'use strict';

const utils = require( '@vue/test-utils' ),
	{ nextTick } = require( 'vue' ),
	{ CdxToggleButton } = require( '@wikimedia/codex' ),
	{ mockJSConfig } = require( '../../utils.js' );

const SignalsPopover = require( '../../../../modules/ext.checkUser.suggestedInvestigations/components/SignalsPopover.vue' );

// We have to shallow mount this component because the CdxPopover component
// uses a library (@floating-ui/vue) which causes an infinite loop of rendering
// when being tested. As we don't need to test the structure of CdxPopover, we can
// instead just shallow mount it to avoid mocking a module used only by Codex.
const renderComponent = ( props ) => utils.shallowMount( SignalsPopover, { props: props } );

/**
 * Perform tests common to all tests of the suggested investigations change status
 * dialog and then return the dialog component
 *
 * @param {{ anchor }} props Passed through to {@link renderComponent}
 * @return {*} The wrapper
 */
const commonComponentTest = async ( props ) => {
	mockJSConfig( { wgCheckUserSuggestedInvestigationsSignals: [ 'dev-signal-1' ] } );

	// Render the component and wait for it to be rendered
	const wrapper = renderComponent( props );
	await nextTick();

	expect( wrapper.exists() ).toEqual( true );

	// Check the dialog element exists.
	const popover = wrapper.find(
		'.ext-checkuser-suggestedinvestigations-signals-popover'
	);
	expect( popover.exists() ).toEqual( true );

	// Check that the popover component has the correct properties
	expect( popover.attributes() ).toHaveProperty(
		'title', '(checkuser-suggestedinvestigations-risk-signals-popover-title)'
	);
	expect( popover.attributes() ).toHaveProperty(
		'closebuttonlabel', '(checkuser-suggestedinvestigations-risk-signals-popover-close-label)'
	);

	// Expect the body to have the expected content (body is stored inside the default slot)
	expect( popover.html() ).toContain( '(checkuser-suggestedinvestigations-risk-signals-popover-body-intro)' );
	expect( popover.html() ).toContain( '(checkuser-suggestedinvestigations-risk-signals-popover-body-dev-signal-1)' );

	return wrapper;
};

describe( 'Risk signals popover component', () => {
	beforeEach( () => {
		// Allow stubs to render their slot content, so that we can see the
		// content inside the stubbed CdxPopup
		utils.config.global.renderStubDefaultSlot = true;
	} );

	afterEach( () => {
		utils.config.global.renderStubDefaultSlot = false;
	} );

	const anchor = utils.shallowMount( CdxToggleButton, {
		props: { modelValue: false },
		attachTo: 'body'
	} );

	it( 'Renders correctly when mounted', async () => {
		await commonComponentTest( { anchor: anchor.vm.$el } );
	} );

	it( 'Methods allow closing and opening popover', async () => {
		const wrapper = await commonComponentTest( { anchor: anchor.vm.$el } );
		expect( wrapper.vm.isPopoverOpen() ).toEqual( true );

		wrapper.vm.closePopover();
		expect( wrapper.vm.isPopoverOpen() ).toEqual( false );

		wrapper.vm.openPopover();
		expect( wrapper.vm.isPopoverOpen() ).toEqual( true );
	} );
} );
