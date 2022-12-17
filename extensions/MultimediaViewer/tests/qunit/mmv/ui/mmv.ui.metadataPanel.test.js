QUnit.module( 'mmv.ui.metadataPanel', QUnit.newMwEnvironment() );

QUnit.test( '.empty()', function ( assert ) {
	var $qf = $( '#qunit-fixture' );
	var panel = new mw.mmv.ui.MetadataPanel(
		$qf,
		$( '<div>' ).appendTo( $qf ),
		mw.storage,
		new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage )
	);
	panel.empty();

	[
		'$license',
		'$title',
		'$location',
		'$datetime'
	].forEach( function ( thing ) {
		assert.strictEqual( panel[ thing ].text(), '', thing + ' empty text' );
	} );

	[
		'$licenseLi',
		'$credit',
		'$locationLi',
		'$datetimeLi'
	].forEach( function ( thing ) {
		assert.true( panel[ thing ].hasClass( 'empty' ), thing + ' empty class' );
	} );
} );

QUnit.test( '.setLocationData()', function ( assert ) {
	var $qf = $( '#qunit-fixture' );
	var panel = new mw.mmv.ui.MetadataPanel(
		$qf,
		$( '<div>' ).appendTo( $qf ),
		mw.storage,
		new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage )
	);
	var fileName = 'Foobar.jpg';
	var latitude = 12.3456789;
	var longitude = 98.7654321;
	var imageData = {
		latitude: latitude,
		longitude: longitude,
		hasCoords: function () { return true; },
		title: mw.Title.newFromText( 'File:Foobar.jpg' )
	};

	panel.setLocationData( imageData );

	assert.strictEqual(
		panel.$location.text(),
		'(multimediaviewer-geolocation: (multimediaviewer-geoloc-coords: (multimediaviewer-geoloc-coord: 12, 20, 44.44, (multimediaviewer-geoloc-north)), (multimediaviewer-geoloc-coord: 98, 45, 55.56, (multimediaviewer-geoloc-east))))',
		'Location text is set as expected - if this fails it may be due to i18n issues.'
	);
	assert.strictEqual(
		panel.$location.prop( 'href' ),
		'http://tools.wmflabs.org/geohack/geohack.php?pagename=File:' + fileName + '&params=' + latitude + '_N_' + longitude + '_E_&language=qqx',
		'Location URL is set as expected'
	);

	latitude = -latitude;
	longitude = -longitude;
	imageData.latitude = latitude;
	imageData.longitude = longitude;
	panel.setLocationData( imageData );

	assert.strictEqual(
		panel.$location.text(),
		'(multimediaviewer-geolocation: (multimediaviewer-geoloc-coords: (multimediaviewer-geoloc-coord: 12, 20, 44.44, (multimediaviewer-geoloc-south)), (multimediaviewer-geoloc-coord: 98, 45, 55.56, (multimediaviewer-geoloc-west))))',
		'Location text is set as expected - if this fails it may be due to i18n issues.'
	);
	assert.strictEqual(
		panel.$location.prop( 'href' ),
		'http://tools.wmflabs.org/geohack/geohack.php?pagename=File:' + fileName + '&params=' + ( -latitude ) + '_S_' + ( -longitude ) + '_W_&language=qqx',
		'Location URL is set as expected'
	);

	latitude = 0;
	longitude = 0;
	imageData.latitude = latitude;
	imageData.longitude = longitude;
	panel.setLocationData( imageData );

	assert.strictEqual(
		panel.$location.text(),
		'(multimediaviewer-geolocation: (multimediaviewer-geoloc-coords: (multimediaviewer-geoloc-coord: 0, 0, 0, (multimediaviewer-geoloc-north)), (multimediaviewer-geoloc-coord: 0, 0, 0, (multimediaviewer-geoloc-east))))',
		'Location text is set as expected - if this fails it may be due to i18n issues.'
	);
	assert.strictEqual(
		panel.$location.prop( 'href' ),
		'http://tools.wmflabs.org/geohack/geohack.php?pagename=File:' + fileName + '&params=' + latitude + '_N_' + longitude + '_E_&language=qqx',
		'Location URL is set as expected'
	);
} );

QUnit.test( '.setImageInfo()', function ( assert ) {
	var $qf = $( '#qunit-fixture' );
	var panel = new mw.mmv.ui.MetadataPanel(
		$qf,
		$( '<div>' ).appendTo( $qf ),
		mw.storage,
		new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage )
	);
	var title = 'Foo bar';
	var image = {
		filePageTitle: mw.Title.newFromText( 'File:' + title + '.jpg' )
	};
	var imageData = {
		title: image.filePageTitle,
		url: 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
		descriptionUrl: 'https://commons.wikimedia.org/wiki/File:Foobar.jpg',
		hasCoords: function () { return false; }
	};
	var repoData = {
		getArticlePath: function () { return 'Foo'; },
		isCommons: function () { return false; }
	};
	var clock = this.sandbox.useFakeTimers();

	panel.setImageInfo( image, imageData, repoData );

	assert.strictEqual( panel.$title.text(), title, 'Title is correctly set' );
	assert.notStrictEqual( panel.$credit.text(), '', 'Default credit is shown' );
	assert.strictEqual( panel.$license.prop( 'href' ), imageData.descriptionUrl,
		'User is directed to file page for license information' );
	assert.strictEqual( panel.$license.prop( 'target' ), '', 'License information opens in same window' );
	assert.true( panel.$datetimeLi.hasClass( 'empty' ), 'Date/Time is empty' );
	assert.true( panel.$locationLi.hasClass( 'empty' ), 'Location is empty' );

	imageData.creationDateTime = '2013-08-26T14:41:02Z';
	imageData.uploadDateTime = '2013-08-25T14:41:02Z';
	imageData.source = '<b>Lost</b><a href="foo">Bar</a>';
	imageData.author = 'Bob';
	imageData.license = new mw.mmv.model.License( 'CC-BY-2.0', 'cc-by-2.0',
		'Creative Commons Attribution - Share Alike 2.0',
		'http://creativecommons.org/licenses/by-sa/2.0/' );
	imageData.restrictions = [ 'trademarked', 'default', 'insignia' ];

	panel.setImageInfo( image, imageData, repoData );
	var creditPopupText = panel.creditField.$element.attr( 'original-title' );
	clock.tick( 10 );

	assert.strictEqual( panel.$title.text(), title, 'Title is correctly set' );
	assert.false( panel.$credit.hasClass( 'empty' ), 'Credit is not empty' );
	assert.false( panel.$datetimeLi.hasClass( 'empty' ), 'Date/Time is not empty' );
	assert.strictEqual( panel.creditField.$element.find( '.mw-mmv-author' ).text(), imageData.author, 'Author text is correctly set' );
	assert.strictEqual( panel.creditField.$element.find( '.mw-mmv-source' ).html(), '<b>Lost</b><a href="foo">Bar</a>', 'Source text is correctly set' );
	// Either multimediaviewer-credit-popup-text or multimediaviewer-credit-popup-text-more.
	assert.true( creditPopupText === '(multimediaviewer-credit-popup-text)' || creditPopupText === '(multimediaviewer-credit-popup-text-more)', 'Source tooltip is correctly set' );
	assert.strictEqual( panel.$datetime.text(), '(multimediaviewer-datetime-created: 26 August 2013)', 'Correct date is displayed' );
	assert.strictEqual( panel.$license.text(), '(multimediaviewer-license-cc-by-2.0)', 'License is correctly set' );
	assert.strictEqual( panel.$license.prop( 'target' ), '_blank', 'License information opens in new window' );
	assert.true( panel.$restrictions.children().last().children().hasClass( 'mw-mmv-restriction-default' ), 'Default restriction is correctly displayed last' );

	imageData.creationDateTime = undefined;
	panel.setImageInfo( image, imageData, repoData );
	clock.tick( 10 );

	assert.strictEqual( panel.$datetime.text(), '(multimediaviewer-datetime-uploaded: 25 August 2013)', 'Correct date is displayed' );

	clock.restore();
} );

QUnit.test( 'Setting permission information works as expected', function ( assert ) {
	var $qf = $( '#qunit-fixture' );
	var panel = new mw.mmv.ui.MetadataPanel(
		$qf,
		$( '<div>' ).appendTo( $qf ),
		mw.storage,
		new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage )
	);

	// make sure license is visible as it contains the permission
	panel.setLicense( null, 'http://example.com' );
	panel.setPermission( 'Look at me, I am a permission!' );
	assert.true( panel.$permissionLink.is( ':visible' ) );
} );

QUnit.test( 'Date formatting', function ( assert ) {
	var $qf = $( '#qunit-fixture' );
	var panel = new mw.mmv.ui.MetadataPanel(
		$qf,
		$( '<div>' ).appendTo( $qf ),
		mw.storage,
		new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage )
	);
	var date1 = 'Garbage';
	var result = panel.formatDate( date1 );

	assert.strictEqual( result, date1, 'Invalid date is correctly ignored' );
} );

QUnit.test( 'About links', function ( assert ) {
	var $qf = $( '#qunit-fixture' );

	this.sandbox.stub( mw.user, 'isAnon' );
	// eslint-disable-next-line no-new
	new mw.mmv.ui.MetadataPanel( $qf.empty(), $( '<div>' ).appendTo( $qf ), mw.storage, new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage ) );

	assert.strictEqual( $qf.find( '.mw-mmv-about-link' ).length, 1, 'About link is created.' );
} );
