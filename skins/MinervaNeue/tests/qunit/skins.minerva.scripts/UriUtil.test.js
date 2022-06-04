( function () {
	var UriUtil = require( '../../../resources/skins.minerva.scripts/UriUtil.js' );

	QUnit.module( 'Minerva UriUtil', QUnit.newMwEnvironment( {
		setup: function () {
			this.mwUriOrg = mw.Uri;
			mw.Uri = mw.UriRelative( 'https://meta.wikimedia.org/w/index.php' );
		},
		teardown: function () {
			mw.Uri = this.mwUriOrg;
			delete this.mwUriOrg;
		}
	} ) );

	QUnit.test( '.isInternal()', function ( assert ) {
		assert.strictEqual(
			UriUtil.isInternal( new mw.Uri( '/relative' ) ),
			true,
			'relative URLs are internal'
		);
		assert.strictEqual(
			UriUtil.isInternal( new mw.Uri( 'http://meta.wikimedia.org/' ) ),
			true,
			'matching hosts are internal'
		);
		assert.strictEqual(
			UriUtil.isInternal( new mw.Uri( 'https:/meta.wikimedia.org/' ) ),
			true,
			'protocol is irrelevant'
		);
		assert.strictEqual(
			UriUtil.isInternal( new mw.Uri( 'https://meta.wikimedia.org/path' ) ),
			true,
			'path is irrelevant'
		);
		assert.strictEqual(
			UriUtil.isInternal( new mw.Uri( 'https://archive.org/' ) ),
			false,
			'external links are not internal'
		);
		assert.strictEqual(
			UriUtil.isInternal( new mw.Uri( 'https://www.meta.wikimedia.org/' ) ),
			false,
			'differing subdomains are not internal'
		);
	} );
}() );
