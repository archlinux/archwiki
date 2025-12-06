const ErrorWidget = require( 'ext.confirmEdit.hCaptcha/ext.confirmEdit.hCaptcha/ErrorWidget.js' );

QUnit.module( 'ext.confirmEdit.hCaptcha.ErrorWidget', {
	beforeEach() {
		this.widget = new ErrorWidget();

		$( '#qunit-fixture' ).append( this.widget.$element );
	}
} );

QUnit.test( 'should initially be hidden and empty', function ( assert ) {
	assert.strictEqual(
		this.widget.$element.css( 'display' ),
		'none',
		'should be hidden'
	);
	assert.strictEqual(
		this.widget.$element.text(),
		'',
		'message should be empty'
	);
} );

QUnit.test( 'should show after initializing it with content', function ( assert ) {
	const message = 'some error';

	this.widget.show( message );

	assert.notStrictEqual(
		this.widget.$element.css( 'display' ),
		'none',
		'should be shown'
	);
	assert.strictEqual(
		this.widget.$element.text(),
		message,
		'message should match'
	);
} );

QUnit.test( 'should escape HTML', function ( assert ) {
	const message = '<div>some html</div>';

	this.widget.show( message );

	assert.strictEqual(
		this.widget.$element.find( '.cdx-message__content' ).html(),
		'&lt;div&gt;some html&lt;/div&gt;',
		'message should be escaped'
	);
} );

QUnit.test( 'should be hidable via hide()', function ( assert ) {
	const message = 'some error';

	this.widget.show( message );
	this.widget.hide();

	assert.strictEqual(
		this.widget.$element.css( 'display' ),
		'none',
		'should be hidden'
	);
	assert.strictEqual(
		this.widget.$element.text(),
		message,
		'message should match'
	);
} );
