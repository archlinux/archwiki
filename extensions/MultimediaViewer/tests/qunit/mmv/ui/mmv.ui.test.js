const { UiElement } = require( 'mmv' );

( function () {
	QUnit.module( 'mmv.ui', QUnit.newMwEnvironment( {
		beforeEach: function () {
			this.clock = this.sandbox.useFakeTimers();
		}
	} ) );

	QUnit.test( 'handleEvent()', ( assert ) => {
		const element = new UiElement( $( '<div>' ) );

		element.handleEvent( 'mmv-foo', () => {
			assert.true( true, 'Event is handled' );
		} );

		$( document ).trigger( new $.Event( 'mmv-foo' ) );

		element.clearEvents();

		$( document ).trigger( new $.Event( 'mmv-foo' ) );
	} );

}() );
