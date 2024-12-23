const { LightboxImage } = require( 'mmv.bootstrap' );

QUnit.module( 'mmv.lightboximage', QUnit.newMwEnvironment() );

QUnit.test( 'Sense test', ( assert ) => {
	const lightboxImage = new LightboxImage( 'foo.png' );

	assert.true( lightboxImage instanceof LightboxImage, 'Object created' );
} );
