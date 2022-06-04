( function () {
	var TitleUtil = require( '../../../resources/skins.minerva.scripts/TitleUtil.js' );

	QUnit.module( 'Minerva TitleUtil', QUnit.newMwEnvironment( {
		setup: function () {
			this.mwUriOrg = mw.Uri;
			mw.Uri = mw.UriRelative( 'https://meta.wikimedia.org/w/index.php' );
		},
		teardown: function () {
			mw.Uri = this.mwUriOrg;
			delete this.mwUriOrg;
		},
		config: {
			wgArticlePath: '/wiki/$1',
			wgScriptPath: '/w'
		}
	} ) );

	QUnit.test( '.newFromUri()', function ( assert ) {
		[ '', 'https://meta.wikimedia.org' ].forEach( function ( authority, index ) {
			var
				indexMsg = 'case ' + index + ' ',
				authorityMsg = ' authority="' + authority + '"',
				validateReadOnlyLink = { validateReadOnlyLink: true };
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/w/index.php?title=Title' ).getPrefixedDb(),
				'Title',
				indexMsg + 'title is in query parameter' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/wiki/Title' ).getPrefixedDb(),
				'Title',
				indexMsg + 'title is in path' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/foo/bar/wiki/Title' ),
				null,
				indexMsg + 'title is not in nonmatching path' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/wiki/%E6%B8%AC%E8%A9%A6' ).getPrefixedDb(),
				'測試',
				indexMsg + 'title is decoded' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/wiki/Foo bar' ).getPrefixedDb(),
				'Foo_bar',
				indexMsg + 'title with space is decoded' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/wiki/Foo%20bar' ).getPrefixedDb(),
				'Foo_bar',
				indexMsg + 'title with encoded space is decoded' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/w/index.php?title=Title#fragment' ).getPrefixedDb(),
				'Title',
				indexMsg + 'fragment is omitted from query title' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/wiki/Title#fragment' ).getPrefixedDb(),
				'Title',
				indexMsg + 'fragment is omitted from path title' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/w/index.php?title=Title#fragment' ).getFragment(),
				'fragment',
				indexMsg + 'fragment is present after query parameter' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/wiki/Title#fragment' ).getFragment(),
				'fragment',
				indexMsg + 'fragment is present after path' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/w/index.php?title=Title#foo%20bar' ).getFragment(),
				'foo bar',
				indexMsg + 'fragment is decoded' + authorityMsg
			);

			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/w/index.php?title=Title', validateReadOnlyLink ).getPrefixedDb(),
				'Title',
				indexMsg + 'query title is read-only' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/wiki/Title', validateReadOnlyLink ).getPrefixedDb(),
				'Title',
				indexMsg + 'path title is read-only' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/w/index.php?title=Title&oldid=123', validateReadOnlyLink ).getPrefixedDb(),
				'Title',
				indexMsg + 'query title with revision is read-only' + authorityMsg
			);
			assert.strictEqual(
				TitleUtil.newFromUri( authority + '/w/index.php?title=Title&param', validateReadOnlyLink ),
				null,
				indexMsg + 'query title with unknown parameter is not read-only' + authorityMsg
			);
		} );

		// Bad or odd inputs.
		[
			'%', null, undefined, '', ' ', '/', {}, '\\', '/wiki/%', '/w/index.php?title=%'
		].forEach( function ( input, index ) {
			assert.strictEqual(
				TitleUtil.newFromUri( input ),
				null,
				'Case ' + index + ' no Title in bad input input="' + input + '"'
			);
		} );

		// Parameters are passed to Uri's constructor.
		assert.strictEqual(
			TitleUtil.newFromUri( { protocol: 'https',
				host: 'meta.wikimedia.org',
				path: '/wiki/Title' } ).getPrefixedDb(),
			'Title',
			'title is in Uri parameters'
		);

		// A Uri itself can be passed.
		assert.strictEqual(
			TitleUtil.newFromUri( new mw.Uri( 'https://meta.wikimedia.org/wiki/Title' ) ).getPrefixedDb(),
			'Title',
			'title is in Uri'
		);

		// JSDoc examples.
		// https://meta.wikimedia.org/wiki/Foo → Foo (path title)
		assert.strictEqual(
			TitleUtil.newFromUri( 'https://meta.wikimedia.org/wiki/Foo' ).getPrefixedDb(),
			'Foo',
			'path title'
		);

		// http://meta.wikimedia.org/wiki/Foo → Foo (mismatching protocol)
		assert.strictEqual(
			TitleUtil.newFromUri( 'http://meta.wikimedia.org/wiki/Foo' ).getPrefixedDb(),
			'Foo',
			'mismatching protocol'
		);

		// /wiki/Foo → Foo (relative URI)
		assert.strictEqual(
			TitleUtil.newFromUri( '/wiki/Foo' ).getPrefixedDb(),
			'Foo',
			'relative URI'
		);

		// /w/index.php?title=Foo → Foo (title query parameter)
		assert.strictEqual(
			TitleUtil.newFromUri( '/w/index.php?title=Foo' ).getPrefixedDb(),
			'Foo',
			'title query parameter'
		);

		// /wiki/Talk:Foo → Talk:Foo (non-main namespace URI)
		assert.strictEqual(
			TitleUtil.newFromUri( '/wiki/Talk:Foo' ).getPrefixedDb(),
			'Talk:Foo',
			'non-main namespace URI'
		);

		// /wiki/Foo bar → Foo_bar (name with spaces)
		assert.strictEqual(
			TitleUtil.newFromUri( '/wiki/Foo bar' ).getPrefixedDb(),
			'Foo_bar',
			'name with spaces'
		);

		// /wiki/Foo%20bar → Foo_bar (name with percent encoded spaces)
		assert.strictEqual(
			TitleUtil.newFromUri( '/wiki/Foo%20bar' ).getPrefixedDb(),
			'Foo_bar',
			'name with percent encoded spaces'
		);

		// /wiki/Foo+bar → Foo+bar (name with +)
		assert.strictEqual(
			TitleUtil.newFromUri( '/wiki/Foo+bar' ).getPrefixedDb(),
			'Foo+bar',
			'name with +'
		);

		// /w/index.php?title=Foo%2bbar → Foo+bar (query parameter with +)
		assert.strictEqual(
			TitleUtil.newFromUri( '/w/index.php?title=Foo%2bbar' ).getPrefixedDb(),
			'Foo+bar',
			'query parameter with +'
		);

		// / → null (mismatching article path)
		assert.strictEqual(
			TitleUtil.newFromUri( '/' ),
			null,
			'mismatching article path'
		);

		// /wiki/index.php?title=Foo → null (mismatching script path)
		assert.strictEqual(
			TitleUtil.newFromUri( '/wiki/index.php?title=Foo' ),
			null,
			'mismatching script path'
		);

		// https://archive.org/ → null (mismatching host)
		assert.strictEqual(
			TitleUtil.newFromUri( 'https://archive.org/' ),
			null,
			'mismatching host (0)'
		);

		// https://foo.wikimedia.org/ → null (mismatching host)
		assert.strictEqual(
			TitleUtil.newFromUri( 'https://foo.wikimedia.org/' ),
			null,
			'mismatching host (1)'
		);

		// https://en.wikipedia.org/wiki/Bar → null (mismatching host)
		assert.strictEqual(
			TitleUtil.newFromUri( 'https://en.wikipedia.org/wiki/Bar' ),
			null,
			'mismatching host (2)'
		);
	} );
}() );
