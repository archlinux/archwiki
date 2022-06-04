/**
 * Example A/B test configuration for sticky header:
 *
 * $wgVectorABTestEnrollment = [
 *     'name' => 'vector.sticky_header',
 *     'enabled' => true,
 *     'buckets' => [
 *         'unsampled' => [
 *             'samplingRate' => 0.1,
 *         ],
 *         'control' => [
 *             'samplingRate' => 0.3,
 *         ],
 *         'stickyHeaderDisabled' => [
 *             'samplingRate' => 0.3,
 *         ],
 *         'stickyHeaderEnabled' => [
 *             'samplingRate' => 0.3,
 *         ],
 *     ],
 * ];
 */

/**
 * Functions and variables to implement A/B testing.
 */
const ABTestConfig = require( /** @type {string} */ ( './config.json' ) ).wgVectorWebABTestEnrollment || {};

/**
 * Get the name of the bucket the user is assigned to for A/B testing.
 *
 * @return {string} the name of the bucket the user is assigned.
 */
function getBucketName() {
	/**
	 * Provided config should contain the keys:
	 *  name: the name of the experiment prefixed with the skin name.
	 *  enabled: must be true or all users are assigned to control.
	 *  buckets: dict with bucket name as key and test config as value.
	 *
	 * Bucket test config can contain the keys:
	 *  samplingRate: sampling rates will be summed up and each bucket will receive a proportion
	 *   equal to its value.
	 */
	return mw.experiments.getBucket( {
		name: ABTestConfig.name,
		enabled: ABTestConfig.enabled,
		buckets: {
			// @ts-ignore
			unsampled: ABTestConfig.buckets.unsampled.samplingRate,
			control: ABTestConfig.buckets.control.samplingRate,
			stickyHeaderDisabled: ABTestConfig.buckets.stickyHeaderDisabled.samplingRate,
			stickyHeaderEnabled: ABTestConfig.buckets.stickyHeaderEnabled.samplingRate
		}
	}, mw.user.getId().toString() );
}

/**
 * Get the group and experiment name for an A/B test.
 *
 * @return {Object} data to pass to event logging
 */
function getABTestGroupExperimentName() {
	return {
		group: getBucketName(),
		experimentName: ABTestConfig.name
	};
}

/**
 * Provides A/B test config for the current user.
 *
 * @return {Object} A/B test config data
 */
function getEnabledExperiment() {
	const mergedConfig = {};

	if ( ABTestConfig.enabled ) {
		// Merge all the A/B config to return.
		Object.assign( mergedConfig, getABTestGroupExperimentName(), ABTestConfig );
	}
	return mergedConfig;
}

/**
 * Determine if user is in test group to experience feature.
 *
 * @param {string} bucket the bucket name the user is assigned
 * @param {string} targetGroup the target test group to experience feature
 * @return {boolean} true if the user should experience feature
 */
function isInTestGroup( bucket, targetGroup ) {
	return bucket === targetGroup;
}

/**
 * Fire hook to register A/B test enrollment.
 *
 * @param {string} bucket the bucket user is assigned to
 */
function initAB( bucket ) {
	// Send data to WikimediaEvents to log A/B test initialization if experiment is enabled
	// and if the user is logged in.
	if ( ABTestConfig.enabled && !mw.user.isAnon() && bucket !== 'unsampled' ) {
		// @ts-ignore
		mw.hook( 'mediawiki.web_AB_test_enrollment' ).fire( getABTestGroupExperimentName() );

		// Remove class if present on the html element so that scroll padding isn't undesirably
		// applied to users who don't experience the new treatment.
		if ( bucket !== 'stickyHeaderEnabled' ) {
			document.documentElement.classList.remove( 'vector-sticky-header-enabled' );
		}
	}
}

module.exports = {
	isInTestGroup,
	getEnabledExperiment,
	initAB,
	test: {
		getBucketName,
		getABTestGroupExperimentName
	}
};
