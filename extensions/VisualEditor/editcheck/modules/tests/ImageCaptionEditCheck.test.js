QUnit.module( 'mw.editcheck.ImageCaptionEditCheck', ve.test.utils.newEditCheckEnvironment() );

QUnit.test( 'onBranchNodeChange', ( assert ) => {
	const typeFrameImage = ve.copy( ve.dm.mwExample.MWBlockImage.data[ 0 ] );
	typeFrameImage.attributes.type = 'frame';
	const noFrameImage = ve.copy( ve.dm.mwExample.MWBlockImage.data[ 0 ] );
	noFrameImage.attributes.type = 'none';
	const cases = [
		{
			msg: 'Empty caption',
			data: [
				ve.copy( ve.dm.mwExample.MWBlockImage.data[ 0 ] ),
				{ type: 'mwImageCaption' },
				{ type: 'paragraph', internal: { generated: 'wrapper' } },
				{ type: '/paragraph' },
				{ type: '/mwImageCaption' },
				{ type: '/mwBlockImage' }
			],
			expectedActions: 1
		},
		...[ 'frame', 'none', 'frameless' ].map( ( type ) => {
			const image = ve.copy( ve.dm.mwExample.MWBlockImage.data[ 0 ] );
			image.attributes.type = type;
			return {
				msg: `Empty caption, type=${ type }`,
				data: [
					image,
					{ type: 'mwImageCaption' },
					{ type: 'paragraph', internal: { generated: 'wrapper' } },
					{ type: '/paragraph' },
					{ type: '/mwImageCaption' },
					{ type: '/mwBlockImage' }
				],
				expectedActions: 0
			};
		} ),
		{
			msg: 'Non-empty caption',
			data: ve.copy( ve.dm.mwExample.MWBlockImage.data ),
			expectedActions: 0
		},
		{
			msg: 'Non-empty caption, contents are a block transclusion',
			data: [
				ve.copy( ve.dm.mwExample.MWBlockImage.data[ 0 ] ),
				{ type: 'mwImageCaption' },
				ve.copy( ve.dm.mwExample.MWTransclusion.blockData ),
				{ type: '/mwTransclusionBlock' },
				{ type: '/mwImageCaption' },
				{ type: '/mwBlockImage' }
			],
			expectedActions: 0
		}
	];

	cases.forEach( ( caseItem ) => {
		const doc = ve.dm.mwExample.createExampleDocumentFromData( [
			...caseItem.data,
			{ type: 'internalList' },
			{ type: '/internalList' }
		] );
		const surface = new ve.dm.Surface( doc );

		const check = new mw.editcheck.ImageCaptionEditCheck( ve.test.utils.EditCheck.dummyController, {}, true );
		const actions = check.onBranchNodeChange( surface );

		assert.strictEqual( actions.length, caseItem.expectedActions, caseItem.msg );
		if ( actions.length > 0 ) {
			assert.strictEqual( actions[ 0 ].getName(), 'imageCaption', 'Action name' );
		}
	} );
} );
