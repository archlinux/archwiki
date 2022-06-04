const mockConfig = require( './__mocks__/config.json' );
const ABTestConfig = mockConfig.wgVectorWebABTestEnrollment;

// Mock out virtual config.json file used in AB.js, before importing AB.js
jest.mock( '../../resources/skins.vector.es6/config.json', () => {
	return mockConfig;
}, { virtual: true } );
const AB = require( '../../resources/skins.vector.es6/AB.js' );

describe( 'AB.js', () => {
	const bucket = 'sampled';
	const userId = '1';
	const getBucketMock = jest.fn().mockReturnValue( bucket );
	const toStringMock = jest.fn().mockReturnValue( userId );
	mw.experiments.getBucket = getBucketMock;
	// @ts-ignore
	mw.user.getId = () => ( { toString: toStringMock } );

	const expectedABTestGroupExperimentName = {
		group: bucket,
		experimentName: ABTestConfig.name
	};

	describe( 'getBucketName', () => {
		it( 'calls mw.experiments.getBucket with config data', () => {
			expect( AB.test.getBucketName() ).toBe( bucket );
			expect( getBucketMock ).toBeCalledWith( {
				name: ABTestConfig.name,
				enabled: ABTestConfig.enabled,
				buckets: {
					unsampled: ABTestConfig.buckets.unsampled.samplingRate,
					control: ABTestConfig.buckets.control.samplingRate,
					stickyHeaderDisabled: ABTestConfig.buckets.stickyHeaderDisabled.samplingRate,
					stickyHeaderEnabled: ABTestConfig.buckets.stickyHeaderEnabled.samplingRate
				}
			}, userId );
			expect( toStringMock ).toHaveBeenCalled();
		} );
	} );
	describe( 'getABTestGroupExperimentName', () => {
		it( 'returns group and experiment name object', () => {
			expect( AB.test.getABTestGroupExperimentName() )
				.toEqual( expectedABTestGroupExperimentName );
		} );
	} );
	describe( 'getEnabledExperiment', () => {
		it( 'returns AB config data when enabled', () => {
			expect( AB.getEnabledExperiment() ).toEqual(
				Object.assign( {}, expectedABTestGroupExperimentName, ABTestConfig )
			);
		} );
	} );
	describe( 'initAB(', () => {
		const hookMock = jest.fn().mockReturnValue( { fire: () => {} } );
		const isAnonMock = jest.fn();
		mw.user.isAnon = isAnonMock;
		mw.hook = hookMock;
		it( 'sends data to WikimediaEvents when the AB test is enabled ', () => {
			isAnonMock.mockReturnValueOnce( false );
			AB.initAB( 'sampled' );
			expect( hookMock ).toHaveBeenCalled();
		} );
		it( 'doesnt send data to WikimediaEvents when the user is anon ', () => {
			isAnonMock.mockReturnValueOnce( true );
			AB.initAB( 'sampled' );
			expect( hookMock ).not.toHaveBeenCalled();
		} );
		it( 'doesnt send data to WikimediaEvents when the bucket is unsampled ', () => {
			isAnonMock.mockReturnValueOnce( false );
			AB.initAB( 'unsampled' );
			expect( hookMock ).not.toHaveBeenCalled();
		} );
	} );
} );
