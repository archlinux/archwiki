'use strict';

QUnit.module( 'ext.math.popup', () => {
	QUnit.test( 'fetchPreviewForTitle sends only a valid QID to the REST API', function ( assert ) {
		const restPaths = [];
		this.sandbox.stub( mw.Rest.prototype, 'ajax' ).callsFake( ( path ) => {
			restPaths.push( path );
			return $.Deferred().resolve( { title: 'Formula', extract: 'Formula extract' } ).promise();
		} );
		this.sandbox.stub( mw.Api.prototype, 'get' ).returns(
			$.Deferred().resolve( { query: { pages: [ { title: 'Fallback', extract: 'Text' } ] } } ).promise()
		);
		const makeFormula = ( qid ) => {
			const $formula = $( '<span>' )
				.addClass( 'mwe-math-element' )
				.attr( 'data-qid', qid )
				.append( $( '<img>' ) );
			// The module looks for formulas on the page only when it first loads
			$( '#qunit-fixture' ).append( $formula );
			return $formula.find( 'img' )[ 0 ];
		};
		const validFormula = makeFormula( 'Q42' );
		const invalidFormula = makeFormula( 'Q1/../../../index.php?title=X&action=raw' );
		const title = mw.Title.newFromText( 'Dummy' );

		return mw.loader.using( 'ext.math.popup' ).then( ( require ) => {
			const plugin = require( 'ext.math.popup' );
			assert.notStrictEqual( plugin, null, 'Plugin is active (module must not load before this test)' );
			const gateway = plugin.gateway;
			return gateway.fetchPreviewForTitle( title, validFormula ).then( ( validModel ) => {
				assert.strictEqual( validModel.title, 'Formula', 'A valid QID uses the formula preview' );
				return gateway.fetchPreviewForTitle( title, invalidFormula );
			} );
		} ).then( ( invalidModel ) => {
			assert.strictEqual( invalidModel.title, 'Fallback', 'An invalid QID uses the page preview' );
			assert.deepEqual( restPaths, [ '/math/v0/popup/html/42' ], 'Only the valid QID reaches the REST API' );
		} );
	} );
} );
