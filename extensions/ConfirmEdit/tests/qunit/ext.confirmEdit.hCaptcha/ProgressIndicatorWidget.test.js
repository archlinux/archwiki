const ProgressIndicatorWidget = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ProgressIndicatorWidget.js' );

QUnit.module( 'ext.confirmEdit.hCaptcha.ProgressIndicatorWidget' );

QUnit.test( 'should render with label', ( assert ) => {
	const label = 'test label';
	const widget = new ProgressIndicatorWidget( label );

	$( '#qunit-fixture' ).append( widget.$element );

	assert.strictEqual(
		widget.$element.find( 'progress' ).attr( 'aria-label' ),
		label,
		'should set label'
	);
} );
