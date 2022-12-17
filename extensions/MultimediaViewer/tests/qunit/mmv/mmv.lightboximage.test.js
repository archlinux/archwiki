QUnit.module( 'mmv.lightboximage', QUnit.newMwEnvironment() );

QUnit.test( 'Sense test', function ( assert ) {
	var lightboxImage = new mw.mmv.LightboxImage( 'foo.png' );

	assert.true( lightboxImage instanceof mw.mmv.LightboxImage, 'Object created' );
} );
