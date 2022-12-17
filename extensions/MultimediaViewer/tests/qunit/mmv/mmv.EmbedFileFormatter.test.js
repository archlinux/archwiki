( function () {
	QUnit.module( 'mmv.EmbedFileFormatter', QUnit.newMwEnvironment() );

	function createEmbedFileInfo( options ) {
		var license = options.licenseShortName ? new mw.mmv.model.License( options.licenseShortName,
				options.licenseInternalName, options.licenseLongName, options.licenseUrl ) : undefined,
			imageInfo = new mw.mmv.model.Image(

				options.title,
				options.title.getNameText(),
				undefined,
				undefined,
				undefined,
				undefined,
				options.imgUrl,
				options.filePageUrl,
				options.shortFilePageUrl,
				42,
				'repo',
				undefined,
				undefined,
				undefined,
				undefined,
				options.source,
				options.author,
				options.authorCount,
				license ),
			repoInfo = { displayName: options.siteName, getSiteLink:
				function () { return options.siteUrl; } };

		return {
			imageInfo: imageInfo,
			repoInfo: repoInfo,
			caption: options.caption
		};
	}

	QUnit.test( 'EmbedFileFormatter constructor sense check', function ( assert ) {
		var formatter = new mw.mmv.EmbedFileFormatter();
		assert.true( formatter instanceof mw.mmv.EmbedFileFormatter, 'constructor with no argument works' );
	} );

	QUnit.test( 'getByline():', function ( assert ) {
		var formatter = new mw.mmv.EmbedFileFormatter(),
			author = '<span class="mw-mmv-author">Homer</span>',
			source = '<span class="mw-mmv-source">Iliad</span>',
			attribution = '<span class="mw-mmv-attr">Cat</span>',
			byline;

		// Works with no arguments
		byline = formatter.getByline();
		assert.strictEqual( byline, undefined, 'No argument case handled correctly.' );

		// Attribution present
		byline = formatter.getByline( author, source, attribution );
		assert.true( /Cat/.test( byline ), 'Attribution found in bylines' );

		// Author and source present
		byline = formatter.getByline( author, source );
		assert.true( /Homer|Iliad/.test( byline ), 'Author and source found in bylines' );

		// Only author present
		byline = formatter.getByline( author );
		assert.true( /Homer/.test( byline ), 'Author found in bylines.' );

		// Only source present
		byline = formatter.getByline( undefined, source );
		assert.true( /Iliad/.test( byline ), 'Source found in bylines.' );
	} );

	QUnit.test( 'getSiteLink():', function ( assert ) {
		var repoInfo = new mw.mmv.model.Repo( 'Wikipedia', '//wikipedia.org/favicon.ico', true ),
			info = { imageInfo: {}, repoInfo: repoInfo },
			formatter = new mw.mmv.EmbedFileFormatter(),
			siteUrl = repoInfo.getSiteLink(),
			siteLink = formatter.getSiteLink( info );

		assert.notStrictEqual( siteLink.indexOf( 'Wikipedia' ), -1, 'Site name is present in site link' );
		assert.notStrictEqual( siteLink.indexOf( siteUrl ), -1, 'Site URL is present in site link' );
	} );

	QUnit.test( 'getThumbnailHtml():', function ( assert ) {
		var formatter = new mw.mmv.EmbedFileFormatter(),
			titleText = 'Music Room',
			title = mw.Title.newFromText( titleText ),
			imgUrl = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
			filePageUrl = 'https://commons.wikimedia.org/wiki/File:Foobar.jpg',
			filePageShortUrl = 'https://commons.wikimedia.org/wiki/index.php?curid=42',
			siteName = 'Site Name',
			siteUrl = '//site.url/',
			licenseShortName = 'Public License',
			licenseInternalName = '-',
			licenseLongName = 'Public Domain, copyrights have lapsed',
			licenseUrl = '//example.com/pd',
			author = '<span class="mw-mmv-author">Homer</span>',
			source = '<span class="mw-mmv-source">Iliad</span>',
			thumbUrl = 'https://upload.wikimedia.org/wikipedia/thumb/Foobar.jpg',
			width = 700,
			height = 500,
			info,
			generatedHtml;

		// Bylines, license and site
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl,
			shortFilePageUrl: filePageShortUrl, siteName: siteName, siteUrl: siteUrl,
			licenseShortName: licenseShortName, licenseInternalName: licenseInternalName,
			licenseLongName: licenseLongName, licenseUrl: licenseUrl, author: author, source: source } );

		generatedHtml = formatter.getThumbnailHtml( info, thumbUrl, width, height );
		assert.notStrictEqual( generatedHtml.match( titleText ), null, 'Title appears in generated HTML.' );
		assert.notStrictEqual( generatedHtml.match( filePageUrl ), null, 'Page url appears in generated HTML.' );
		assert.notStrictEqual( generatedHtml.match( thumbUrl ), null, 'Thumbnail url appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( 'Public License' ), null, 'License appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( 'Homer' ), null, 'Author appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( 'Iliad' ), null, 'Source appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( width ), null, 'Width appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( height ), null, 'Height appears in generated HTML' );
		// .includes() for checking the short url since it contains a ? (bad for regex). Could escape instead.
		// eslint-disable-next-line no-restricted-syntax
		assert.notStrictEqual( generatedHtml.includes( filePageShortUrl ), null, 'Short URL appears in generated HTML' );

		// Bylines, no license and site
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl,
			shortFilePageUrl: filePageShortUrl, siteName: siteName, siteUrl: siteUrl,
			author: author, source: source } );
		generatedHtml = formatter.getThumbnailHtml( info, thumbUrl, width, height );

		assert.notStrictEqual( generatedHtml.match( titleText ), null, 'Title appears in generated HTML.' );
		assert.notStrictEqual( generatedHtml.match( filePageUrl ), null, 'Page url appears in generated HTML.' );
		assert.notStrictEqual( generatedHtml.match( thumbUrl ), null, 'Thumbnail url appears in generated HTML' );
		assert.strictEqual( generatedHtml.match( 'Public License' ), null, 'License should not appear in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( 'Homer' ), null, 'Author appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( 'Iliad' ), null, 'Source appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( width ), null, 'Width appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( height ), null, 'Height appears in generated HTML' );
		// eslint-disable-next-line no-restricted-syntax
		assert.notStrictEqual( generatedHtml.includes( filePageShortUrl ), null, 'Short URL appears in generated HTML' );

		// No bylines, license and site
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl,
			siteName: siteName, siteUrl: siteUrl, licenseShortName: licenseShortName,
			licenseInternalName: licenseInternalName, licenseLongName: licenseLongName,
			licenseUrl: licenseUrl, shortFilePageUrl: filePageShortUrl } );
		generatedHtml = formatter.getThumbnailHtml( info, thumbUrl, width, height );

		assert.notStrictEqual( generatedHtml.match( titleText ), null, 'Title appears in generated HTML.' );
		assert.notStrictEqual( generatedHtml.match( filePageUrl ), null, 'Page url appears in generated HTML.' );
		assert.notStrictEqual( generatedHtml.match( thumbUrl ), null, 'Thumbnail url appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( 'Public License' ), null, 'License appears in generated HTML' );
		assert.strictEqual( generatedHtml.match( 'Homer' ), null, 'Author should not appear in generated HTML' );
		assert.strictEqual( generatedHtml.match( 'Iliad' ), null, 'Source should not appear in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( width ), null, 'Width appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( height ), null, 'Height appears in generated HTML' );
		// eslint-disable-next-line no-restricted-syntax
		assert.notStrictEqual( generatedHtml.includes( filePageShortUrl ), null, 'Short URL appears in generated HTML' );

		// No bylines, no license and site
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl,
			siteName: siteName, siteUrl: siteUrl, shortFilePageUrl: filePageShortUrl } );
		generatedHtml = formatter.getThumbnailHtml( info, thumbUrl, width, height );

		assert.notStrictEqual( generatedHtml.match( titleText ), null, 'Title appears in generated HTML.' );
		assert.notStrictEqual( generatedHtml.match( filePageUrl ), null, 'Page url appears in generated HTML.' );
		assert.notStrictEqual( generatedHtml.match( thumbUrl ), null, 'Thumbnail url appears in generated HTML' );
		assert.strictEqual( generatedHtml.match( 'Public License' ), null, 'License should not appear in generated HTML' );
		assert.strictEqual( generatedHtml.match( 'Homer' ), null, 'Author should not appear in generated HTML' );
		assert.strictEqual( generatedHtml.match( 'Iliad' ), null, 'Source should not appear in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( width ), null, 'Width appears in generated HTML' );
		assert.notStrictEqual( generatedHtml.match( height ), null, 'Height appears in generated HTML' );
		// eslint-disable-next-line no-restricted-syntax
		assert.notStrictEqual( generatedHtml.includes( filePageShortUrl ), null, 'Short URL appears in generated HTML' );

	} );

	QUnit.test( 'getThumbnailWikitext():', function ( assert ) {
		var formatter = new mw.mmv.EmbedFileFormatter(),
			title = mw.Title.newFromText( 'File:Foobar.jpg' ),
			imgUrl = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
			filePageUrl = 'https://commons.wikimedia.org/wiki/File:Foobar.jpg',
			caption = 'Foobar caption.',
			width = 700,
			info,
			wikitext;

		// Title, width and caption
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl,
			caption: caption } );
		wikitext = formatter.getThumbnailWikitextFromEmbedFileInfo( info, width );

		assert.strictEqual(
			wikitext,
			'[[File:Foobar.jpg|700px|thumb|Foobar caption.]]',
			'Wikitext generated correctly.' );

		// Title, width and no caption
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl } );
		wikitext = formatter.getThumbnailWikitextFromEmbedFileInfo( info, width );

		assert.strictEqual(
			wikitext,
			'[[File:Foobar.jpg|700px|thumb|Foobar]]',
			'Wikitext generated correctly.' );

		// Title, no width and no caption
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl } );
		wikitext = formatter.getThumbnailWikitextFromEmbedFileInfo( info );

		assert.strictEqual(
			wikitext,
			'[[File:Foobar.jpg|thumb|Foobar]]',
			'Wikitext generated correctly.' );
	} );

	QUnit.test( 'getCreditText():', function ( assert ) {
		var txt, formatter = new mw.mmv.EmbedFileFormatter();

		txt = formatter.getCreditText( {
			repoInfo: {
				displayName: 'Localcommons'
			},

			imageInfo: {
				author: 'Author',
				source: 'Source',
				descriptionShortUrl: 'link',
				title: {
					getNameText: function () { return 'Image Title'; }
				}
			}
		} );

		assert.strictEqual( txt, '(multimediaviewer-text-embed-credit-text-b: (multimediaviewer-credit: Author, Source), link)', 'Sense check' );

		txt = formatter.getCreditText( {
			repoInfo: {
				displayName: 'Localcommons'
			},

			imageInfo: {
				author: 'Author',
				source: 'Source',
				descriptionShortUrl: 'link',
				title: {
					getNameText: function () { return 'Image Title'; }
				},
				license: {
					getShortName: function () { return 'WTFPL v2'; },
					longName: 'Do What the Fuck You Want Public License Version 2',
					isFree: this.sandbox.stub().returns( true )
				}
			}
		} );

		assert.strictEqual( txt, '(multimediaviewer-text-embed-credit-text-bl: (multimediaviewer-credit: Author, Source), WTFPL v2, link)', 'License message works' );
	} );

	QUnit.test( 'getCreditHtml():', function ( assert ) {
		var html, formatter = new mw.mmv.EmbedFileFormatter();

		html = formatter.getCreditHtml( {
			repoInfo: {
				displayName: 'Localcommons',
				getSiteLink: function () { return 'quux'; }
			},

			imageInfo: {
				author: 'Author',
				source: 'Source',
				descriptionShortUrl: 'some link',
				title: {
					getNameText: function () { return 'Image Title'; }
				}
			}
		} );

		assert.strictEqual(
			html,
			'(multimediaviewer-html-embed-credit-text-b: (multimediaviewer-credit: Author, Source), <a href="some link">(multimediaviewer-html-embed-credit-link-text)</a>)',
			'Sense check'
		);

		html = formatter.getCreditHtml( {
			repoInfo: {
				displayName: 'Localcommons',
				getSiteLink: function () { return 'quux'; }
			},

			imageInfo: {
				author: 'Author',
				source: 'Source',
				descriptionShortUrl: 'some link',
				title: {
					getNameText: function () { return 'Image Title'; }
				},
				license: {
					getShortLink: function () { return '<a href="http://www.wtfpl.net/">WTFPL v2</a>'; },
					longName: 'Do What the Fuck You Want Public License Version 2',
					isFree: this.sandbox.stub().returns( true )
				}
			}
		} );

		assert.strictEqual(
			html,
			'(multimediaviewer-html-embed-credit-text-bl: (multimediaviewer-credit: Author, Source), <a href="http://www.wtfpl.net/">WTFPL v2</a>, <a href="some link">(multimediaviewer-html-embed-credit-link-text)</a>)',
			'Sense check'
		);
	} );
}() );
