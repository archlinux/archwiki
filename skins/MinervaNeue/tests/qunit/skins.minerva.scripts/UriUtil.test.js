( function () {
	const UriUtil = require( 'skins.minerva.scripts/UriUtil.js' );

	QUnit.module( 'Minerva UriUtil', {
		beforeEach: function () {
			UriUtil.isInternal.base = new URL( 'https://meta.wikimedia.org/w/index.php' );
		},
		afterEach: function () {
			UriUtil.isInternal.base = location;
		}
	} );

	QUnit.test( '.isInternal()', ( assert ) => {
		assert.true(
			UriUtil.isInternal( new URL( '/relative', UriUtil.isInternal.base ) ),
			'relative URLs are internal'
		);
		assert.true(
			UriUtil.isInternal( new URL( 'http://meta.wikimedia.org/' ) ),
			'matching hosts are internal'
		);
		assert.true(
			UriUtil.isInternal( new URL( 'https:/meta.wikimedia.org/' ) ),
			'protocol is irrelevant'
		);
		assert.true(
			UriUtil.isInternal( new URL( 'https://meta.wikimedia.org/path' ) ),
			'path is irrelevant'
		);
		assert.false(
			UriUtil.isInternal( new URL( 'https://archive.org/' ) ),
			'external links are not internal'
		);
		assert.false(
			UriUtil.isInternal( new URL( 'https://www.meta.wikimedia.org/' ) ),
			'differing subdomains are not internal'
		);
	} );
}() );
