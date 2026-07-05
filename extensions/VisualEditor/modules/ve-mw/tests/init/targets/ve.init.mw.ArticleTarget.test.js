/*!
 * VisualEditor MediaWiki ArticleTarget tests.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

QUnit.module( 've.init.mw.ArticleTarget', ve.test.utils.newMwEnvironment() );

QUnit.test( 'When onSaveDialogSave is run and getSaveOptionsProcess resolves, startSave is called', ( assert ) => {
	// Create a mock ve.init.mw.ArticleTarget which overrides the
	// startSave method to have custom logic including assertions.
	let mockStartSaveCalled = false;
	const ArticleTargetClassWithMockedStartSave = function VeInitMwMockArticleTarget( config ) {
		// Parent constructor
		ArticleTargetClassWithMockedStartSave.super.call( this, config );
	};
	OO.inheritClass( ArticleTargetClassWithMockedStartSave, ve.init.mw.ArticleTarget );
	ArticleTargetClassWithMockedStartSave.prototype.startSave = function ( saveOptions ) {
		mockStartSaveCalled = true;

		assert.propContains(
			saveOptions,
			{ wpTestField: 'test' },
			'saveField modifications via getSaveOptionsProcess should ' +
			'have been applied to saveOptions provided to startSave'
		);

		this.saveDialog.popPending();
		this.saveDeferred.resolve();
	};

	const target = new ArticleTargetClassWithMockedStartSave(),
		saveDeferred = ve.createDeferred(),
		done = assert.async();

	// Register a step for the save options process which will add a field to
	// the saveFields (which is a supported use-case of this process)
	let optionsProcessExecuted = false;
	target.getSaveOptionsProcess().first( () => {
		optionsProcessExecuted = true;
		target.saveFields.wpTestField = function () {
			return 'test';
		};
	} );

	// Setting up a real saveDialog in the target would require a lot of other
	// code to get working, so mock it as we are only interested in knowing
	// that popPending gets called.
	let popPendingCalled = false;
	target.saveDialog = {
		popPending: () => {
			popPendingCalled = true;
		},
		editSummaryInput: {
			getValue: () => ''
		}
	};

	target.onSaveDialogSave( saveDeferred );

	saveDeferred.then( () => {
		assert.true(
			optionsProcessExecuted,
			'getSaveOptionsProcess.execute is called at least before save completes'
		);
		assert.true(
			mockStartSaveCalled,
			'The mocked ve.init.mw.ArticleTarget.startSave should have been used'
		);
		assert.true(
			popPendingCalled,
			'saveDialog.popPending() has been called'
		);
		done();
	} );
} );

QUnit.test( 'When onSaveDialogSave is run and getSaveOptionsProcess rejects, startSave is not called', ( assert ) => {
	// Create a mock ve.init.mw.ArticleTarget which overrides the
	// startSave method to expect to not be called
	const ArticleTargetClassWithMockedStartSave = function VeInitMwMockArticleTarget( config ) {
		// Parent constructor
		ArticleTargetClassWithMockedStartSave.super.call( this, config );
	};
	OO.inheritClass( ArticleTargetClassWithMockedStartSave, ve.init.mw.ArticleTarget );
	ArticleTargetClassWithMockedStartSave.prototype.startSave = function () {
		// False positive
		// eslint-disable-next-line no-jquery/no-done-fail
		assert.fail(
			'startSave should not have been called if the saveOptionsProcess execution failed'
		);
	};

	const target = new ArticleTargetClassWithMockedStartSave(),
		saveDeferred = ve.createDeferred(),
		done = assert.async();

	// Register a step for the save options process which will add a field to
	// the saveFields (which is a supported use-case of this process)
	let optionsProcessExecuted = false;
	target.getSaveOptionsProcess().first( () => {
		optionsProcessExecuted = true;
		return Promise.reject();
	} );

	// Setting up a real saveDialog in the target would require a lot of other
	// code to get working, so mock it as we are only interested in knowing
	// that popPending gets called.
	target.saveDialog = {
		editSummaryInput: {
			getValue: () => ''
		}
	};

	target.onSaveDialogSave( saveDeferred );

	saveDeferred.then(
		() => {
			assert.true(
				optionsProcessExecuted,
				'getSaveOptionsProcess.execute is called at least before save completes'
			);
			done();
		},
		() => {
			assert.true( false, 'Did not expect save deferred promise to reject' );
			done();
		}
	);
} );

QUnit.test( 'When onSaveDialogSave is run and getSaveOptionsProcess adds an error, saveDeferred rejects', ( assert ) => {
	// Create a mock ve.init.mw.ArticleTarget which overrides the
	// startSave method to expect to not be called
	const ArticleTargetClassWithMockedStartSave = function VeInitMwMockArticleTarget( config ) {
		// Parent constructor
		ArticleTargetClassWithMockedStartSave.super.call( this, config );
	};
	OO.inheritClass( ArticleTargetClassWithMockedStartSave, ve.init.mw.ArticleTarget );
	ArticleTargetClassWithMockedStartSave.prototype.startSave = function () {
		// False positive
		// eslint-disable-next-line no-jquery/no-done-fail
		assert.fail(
			'startSave should not have been called if the saveOptionsProcess execution failed'
		);
	};

	const target = new ArticleTargetClassWithMockedStartSave(),
		saveDeferred = ve.createDeferred(),
		done = assert.async();

	// Register a step for the save options process which will add a field to
	// the saveFields (which is a supported use-case of this process)
	let optionsProcessExecuted = false;
	target.getSaveOptionsProcess().first( () => {
		optionsProcessExecuted = true;
		target.showSaveError( 'This will reject saveDeferred' );
		return Promise.reject();
	} );

	// Setting up a real saveDialog in the target would require a lot of other
	// code to get working, so mock it as we are only interested in knowing
	// that popPending gets called.
	target.saveDialog = {
		editSummaryInput: {
			getValue: () => ''
		}
	};

	target.onSaveDialogSave( saveDeferred );

	saveDeferred.then(
		() => {
			assert.true( false, 'Did not expect save deferred promise to resolve' );
			done();
		},
		() => {
			assert.true(
				optionsProcessExecuted,
				'getSaveOptionsProcess.execute is called at least before save completes'
			);
			done();
		}
	);
} );
