const { Config } = require( 'mmv.bootstrap' );
const { MetadataPanel, License } = require( 'mmv' );

const mwMessagesExists = mw.messages.exists;
QUnit.module( 'mmv.ui.metadataPanel', QUnit.newMwEnvironment( {
	beforeEach: () => {
		// mock mw.messages.exists for License.getShortLink (multimediaviewer-license-cc-by-2.0)
		mw.messages.exists = () => true;
	},
	afterEach: () => {
		mw.messages.exists = mwMessagesExists;
	}
} ) );

QUnit.test( '.empty()', ( assert ) => {
	const $qf = $( '#qunit-fixture' );
	const panel = new MetadataPanel(
		$qf,
		$( '<div>' ).appendTo( $qf ),
		new Config()
	);
	panel.empty();

	[
		'$license',
		'$title',
		'$location',
		'$datetimeCreated',
		'$datetimeUpdated'
	].forEach( ( thing ) => {
		assert.strictEqual( panel[ thing ].text(), '', thing + ' empty text' );
	} );

	[
		'$licenseLi',
		'$credit',
		'$locationLi',
		'$datetimeCreatedLi',
		'$datetimeUpdatedLi'
	].forEach( ( thing ) => {
		assert.true( panel[ thing ].hasClass( 'empty' ), thing + ' empty class' );
	} );
} );

QUnit.test( '.setLocationData()', ( assert ) => {
	const $qf = $( '#qunit-fixture' );
	const panel = new MetadataPanel(
		$qf,
		$( '<div>' ).appendTo( $qf ),
		new Config()
	);
	const fileName = 'Foobar.jpg';
	let latitude = 12.3456789;
	let longitude = 98.7654321;
	const imageData = {
		latitude: latitude,
		longitude: longitude,
		hasCoords: () => true,
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
		'https://geohack.toolforge.org/geohack.php?pagename=File:' + fileName + '&params=' + latitude + '_N_' + longitude + '_E_&language=qqx',
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
		'https://geohack.toolforge.org/geohack.php?pagename=File:' + fileName + '&params=' + ( -latitude ) + '_S_' + ( -longitude ) + '_W_&language=qqx',
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
		'https://geohack.toolforge.org/geohack.php?pagename=File:' + fileName + '&params=' + latitude + '_N_' + longitude + '_E_&language=qqx',
		'Location URL is set as expected'
	);
} );

QUnit.test( '.setImageInfo()', function ( assert ) {
	const $qf = $( '#qunit-fixture' );
	const panel = new MetadataPanel(
		$qf,
		$( '<div>' ).appendTo( $qf ),
		new Config()
	);
	const title = 'Foo bar';
	const image = {
		filePageTitle: mw.Title.newFromText( 'File:' + title + '.jpg' ),
		src: 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg'
	};
	const imageData = {
		title: image.filePageTitle,
		url: 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
		descriptionUrl: 'https://commons.wikimedia.org/wiki/File:Foobar.jpg',
		hasCoords: () => false
	};
	const clock = this.sandbox.useFakeTimers();

	panel.setImageInfo( image, imageData );

	assert.strictEqual( panel.$title.text(), title, 'Title is correctly set' );
	assert.notStrictEqual( panel.$credit.text(), '', 'Default credit is shown' );
	assert.strictEqual( panel.$license.prop( 'href' ),
		imageData.descriptionUrl + '?uselang=qqx#(license-header)',
		'User is directed to file page for license information' );
	assert.strictEqual( panel.$license.prop( 'target' ), '', 'License information opens in same window' );
	assert.true( panel.$datetimeCreatedLi.hasClass( 'empty' ), 'Date/Time is empty' );
	assert.true( panel.$datetimeUpdatedLi.hasClass( 'empty' ), 'Date/Time is empty' );
	assert.true( panel.$locationLi.hasClass( 'empty' ), 'Location is empty' );

	imageData.creationDateTime = '2013-08-26T14:41:02Z';
	imageData.uploadDateTime = '2013-08-25T14:41:02Z';
	imageData.source = '<b>Lost</b><a href="foo">Bar</a>';
	imageData.author = 'Bob';
	imageData.license = new License( 'CC-BY-2.0', 'cc-by-2.0',
		'Creative Commons Attribution - Share Alike 2.0',
		'http://creativecommons.org/licenses/by-sa/2.0/' );
	imageData.restrictions = [ 'trademarked', 'default', 'insignia' ];

	panel.setImageInfo( image, imageData );
	const creditPopupText = panel.creditField.$element.attr( 'original-title' );
	clock.tick( 10 );

	assert.strictEqual( panel.$title.text(), title, 'Title is correctly set' );
	assert.false( panel.$credit.hasClass( 'empty' ), 'Credit is not empty' );
	assert.false( panel.$datetimeCreatedLi.hasClass( 'empty' ), 'Date/Time is not empty' );
	assert.strictEqual( panel.creditField.$element.find( '.mw-mmv-author' ).text(), imageData.author, 'Author text is correctly set' );
	assert.strictEqual( panel.creditField.$element.find( '.mw-mmv-source' ).html(), '<b>Lost</b><a href="foo">Bar</a>', 'Source text is correctly set' );
	// Either multimediaviewer-credit-popup-text or multimediaviewer-credit-popup-text-more.
	assert.true( creditPopupText === '(multimediaviewer-credit-popup-text)' || creditPopupText === '(multimediaviewer-credit-popup-text-more)', 'Source tooltip is correctly set' );
	assert.strictEqual( panel.$datetimeCreated.text(), '(multimediaviewer-datetime-created: 26 August 2013)', 'Correct date is displayed' );
	assert.strictEqual( panel.$license.text(), '(multimediaviewer-license-cc-by-2.0)', 'License is correctly set' );
	assert.strictEqual( panel.$license.prop( 'target' ), '_blank', 'License information opens in new window' );
	assert.true( panel.$restrictions.children().last().children().hasClass( 'mw-mmv-restriction-default' ), 'Default restriction is correctly displayed last' );

	imageData.creationDateTime = undefined;
	panel.setImageInfo( image, imageData );
	clock.tick( 10 );

	assert.false( panel.$datetimeUpdatedLi.hasClass( 'empty' ), 'Date/Time is not empty' );
	assert.strictEqual( panel.$datetimeUpdated.text(), '(multimediaviewer-datetime-uploaded: 25 August 2013)', 'Correct date is displayed' );

	clock.restore();
} );

// FIXME: test broken since migrating to require/packageFiles
QUnit.skip( 'Setting permission information works as expected', ( assert ) => {
	const $qf = $( '#qunit-fixture' );
	const panel = new MetadataPanel(
		$qf,
		$( '<div>' ).appendTo( $qf ),
		new Config()
	);

	// make sure license is visible as it contains the permission
	panel.setLicense( null, 'http://example.com' );
	panel.setPermission( 'Look at me, I am a permission!' );
	assert.true( panel.$permissionLink.is( ':visible' ) );
} );

QUnit.test( 'Date formatting', ( assert ) => {
	const $qf = $( '#qunit-fixture' );
	const panel = new MetadataPanel(
		$qf,
		$( '<div>' ).appendTo( $qf ),
		new Config()
	);
	const date1 = 'Garbage';
	const result = panel.formatDate( date1 );

	assert.strictEqual( result, date1, 'Invalid date is correctly ignored' );
} );
