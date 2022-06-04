QUnit.module( 've.ui.MWTransclusionOutlineButtonWidget' );

QUnit.test( 'Constructor', ( assert ) => {
	const widget = new ve.ui.MWTransclusionOutlineButtonWidget( {} );

	// eslint-disable-next-line no-jquery/no-class-state
	assert.true( widget.$element.hasClass( 've-ui-mwTransclusionOutlineButtonWidget' ) );
} );
