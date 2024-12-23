'use strict';

QUnit.module( 've.ui.MWUseExistingReferenceCommand (Cite)', ve.test.utils.newMwEnvironment() );

function getFragmentMock( hasRefs ) {
	const docRefsMock = {
		hasRefs: () => hasRefs
	};

	return {
		getDocument: () => ( {
			getOriginalDocument: () => undefined,
			getStorage: () => docRefsMock,
			setStorage: () => undefined
		} ),
		getSelection: () => ( {
			getName: () => 'linear'
		} )
	};
}

QUnit.test( 'Constructor', ( assert ) => {
	const command = new ve.ui.MWUseExistingReferenceCommand();
	assert.strictEqual( command.name, 'reference/existing' );
	assert.strictEqual( command.action, 'window' );
	assert.strictEqual( command.method, 'open' );
} );

QUnit.test( 'isExecutable', ( assert ) => {
	const command = new ve.ui.MWUseExistingReferenceCommand();

	assert.false( command.isExecutable( getFragmentMock( false ) ) );
	assert.true( command.isExecutable( getFragmentMock( true ) ) );
} );
