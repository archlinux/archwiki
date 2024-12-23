/*
 * This file is part of the MediaWiki extension MediaViewer.
 *
 * MediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

const { Config } = require( 'mmv.bootstrap' );
const { createLocalStorage, getFakeLocalStorage } = require( './mmv.testhelpers.js' );
const config0 = mw.config;
const storage = mw.storage;
const user = mw.user;
const saveOption = mw.Api.prototype.saveOption;

( function () {
	QUnit.module( 'mmv.Config', QUnit.newMwEnvironment( {
		afterEach: function () {
			mw.config = config0;
			mw.storage = storage;
			mw.user = user;
			mw.Api.prototype.saveOption = saveOption;
		}
	} ) );

	QUnit.test( 'constructor', ( assert ) => {
		const config = new Config();
		assert.true( config instanceof Config );
	} );

	QUnit.test( 'isMediaViewerEnabledOnClick', function ( assert ) {
		mw.storage = createLocalStorage( { getItem: this.sandbox.stub() } );
		mw.config = { get: this.sandbox.stub() };
		mw.user = { isNamed: this.sandbox.stub() };

		mw.user.isNamed.returns( true );
		mw.config.get.withArgs( 'wgMediaViewer' ).returns( true );
		mw.config.get.withArgs( 'wgMediaViewerOnClick' ).returns( true );
		assert.strictEqual( Config.isMediaViewerEnabledOnClick(), true, 'Returns true for logged-in with standard settings' );

		mw.user.isNamed.returns( true );
		mw.config.get.withArgs( 'wgMediaViewer' ).returns( false );
		mw.config.get.withArgs( 'wgMediaViewerOnClick' ).returns( true );
		assert.strictEqual( Config.isMediaViewerEnabledOnClick(), false, 'Returns false if opted out via user JS flag' );

		mw.user.isNamed.returns( true );
		mw.config.get.withArgs( 'wgMediaViewer' ).returns( true );
		mw.config.get.withArgs( 'wgMediaViewerOnClick' ).returns( false );
		assert.strictEqual( Config.isMediaViewerEnabledOnClick(), false, 'Returns false if opted out via preferences' );

		mw.user.isNamed.returns( false );
		mw.config.get.withArgs( 'wgMediaViewer' ).returns( false );
		mw.config.get.withArgs( 'wgMediaViewerOnClick' ).returns( true );
		assert.strictEqual( Config.isMediaViewerEnabledOnClick(), false, 'Returns false if anon user opted out via user JS flag' );

		mw.user.isNamed.returns( false );
		mw.config.get.withArgs( 'wgMediaViewer' ).returns( true );
		mw.config.get.withArgs( 'wgMediaViewerOnClick' ).returns( false );
		assert.strictEqual( Config.isMediaViewerEnabledOnClick(), false, 'Returns false if anon user opted out in some weird way' ); // apparently someone created a browser extension to do this

		mw.user.isNamed.returns( false );
		mw.config.get.withArgs( 'wgMediaViewer' ).returns( true );
		mw.config.get.withArgs( 'wgMediaViewerOnClick' ).returns( true );
		mw.storage.store.getItem.withArgs( 'wgMediaViewerOnClick' ).returns( null );
		assert.strictEqual( Config.isMediaViewerEnabledOnClick(), true, 'Returns true for anon with standard settings' );

		mw.user.isNamed.returns( false );
		mw.config.get.withArgs( 'wgMediaViewer' ).returns( true );
		mw.config.get.withArgs( 'wgMediaViewerOnClick' ).returns( true );
		mw.storage.store.getItem.withArgs( 'wgMediaViewerOnClick' ).returns( '0' );
		assert.strictEqual( Config.isMediaViewerEnabledOnClick(), false, 'Returns true for anon opted out via localSettings' );
	} );

	QUnit.test( 'setMediaViewerEnabledOnClick sense check', function ( assert ) {
		mw.storage = createLocalStorage( {
			getItem: this.sandbox.stub(),
			setItem: this.sandbox.stub(),
			removeItem: this.sandbox.stub()
		} );
		mw.user = { isNamed: this.sandbox.stub() };
		mw.config = new mw.Map();
		mw.config.set( 'wgMediaViewerEnabledByDefault', false );
		mw.Api.prototype.saveOption = this.sandbox.stub().returns( $.Deferred().resolve() );

		mw.user.isNamed.returns( true );
		mw.Api.prototype.saveOption.returns( $.Deferred().resolve() );
		Config.setMediaViewerEnabledOnClick( false );
		assert.true( mw.Api.prototype.saveOption.called, 'For logged-in users, pref change is via API' );

		mw.user.isNamed.returns( false );
		Config.setMediaViewerEnabledOnClick( false );
		assert.true( mw.storage.store.setItem.called, 'For anons, opt-out is set in localStorage' );

		mw.user.isNamed.returns( false );
		Config.setMediaViewerEnabledOnClick( true );
		assert.true( mw.storage.store.removeItem.called, 'For anons, opt-in means clearing localStorage' );
	} );

	QUnit.test( 'shouldShowStatusInfo', function ( assert ) {
		mw.config = new mw.Map();
		mw.storage = getFakeLocalStorage();
		mw.user = { isNamed: this.sandbox.stub() };
		mw.Api.prototype.saveOption = this.sandbox.stub().returns( $.Deferred().resolve() );

		mw.config.set( {
			wgMediaViewer: true,
			wgMediaViewerOnClick: true,
			wgMediaViewerEnabledByDefault: true
		} );
		mw.user.isNamed.returns( true );

		assert.strictEqual( Config.shouldShowStatusInfo(), false, 'Status info is not shown by default' );
		Config.setMediaViewerEnabledOnClick( false );
		assert.strictEqual( Config.shouldShowStatusInfo(), true, 'Status info is shown after MMV is disabled the first time' );
		Config.setMediaViewerEnabledOnClick( true );
		assert.strictEqual( Config.shouldShowStatusInfo(), false, 'Status info is not shown when MMV is enabled' );
		Config.setMediaViewerEnabledOnClick( false );
		assert.strictEqual( Config.shouldShowStatusInfo(), true, 'Status info is shown after MMV is disabled the first time #2' );
		Config.disableStatusInfo();
		assert.strictEqual( Config.shouldShowStatusInfo(), false, 'Status info is not shown when already displayed once' );
		Config.setMediaViewerEnabledOnClick( true );
		assert.strictEqual( Config.shouldShowStatusInfo(), false, 'Further status changes have no effect' );
		Config.setMediaViewerEnabledOnClick( false );
		assert.strictEqual( Config.shouldShowStatusInfo(), false, 'Further status changes have no effect #2' );

		// make sure disabling calls maybeEnableStatusInfo() for logged-in as well
		mw.storage = getFakeLocalStorage();
		mw.user.isNamed.returns( false );
		assert.strictEqual( Config.shouldShowStatusInfo(), false, 'Status info is not shown by default for logged-in users' );
		Config.setMediaViewerEnabledOnClick( false );
		assert.strictEqual( Config.shouldShowStatusInfo(), true, 'Status info is shown after MMV is disabled the first time for logged-in users' );

		// make sure popup is not shown immediately on disabled-by-default sites, but still works otherwise
		mw.storage = getFakeLocalStorage();
		mw.config.set( 'wgMediaViewerEnabledByDefault', false );
		assert.strictEqual( Config.shouldShowStatusInfo(), false, 'Status info is not shown by default #2' );
		Config.setMediaViewerEnabledOnClick( true );
		assert.strictEqual( Config.shouldShowStatusInfo(), false, 'Status info is not shown when MMV is enabled #2' );
		Config.setMediaViewerEnabledOnClick( false );
		assert.strictEqual( Config.shouldShowStatusInfo(), true, 'Status info is shown after MMV is disabled the first time #2' );
	} );
}() );
