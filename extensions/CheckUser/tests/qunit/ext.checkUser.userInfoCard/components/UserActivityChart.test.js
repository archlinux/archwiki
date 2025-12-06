'use strict';

const { mount } = require( 'vue-test-utils' );
const UserActivityChart = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/UserActivityChart.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.UserActivityChart', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.sandbox.stub( mw, 'msg' ).callsFake( ( key, totalEdits ) => `${ key }: ${ totalEdits }` );
	}
} ) );

// Sample data for testing
const sampleRecentEdits = [
	{ date: new Date( '2025-01-01' ), count: 5 },
	{ date: new Date( '2025-01-02' ), count: 3 },
	{ date: new Date( '2025-01-03' ), count: 7 }
];

// Reusable mount helper
function mountComponent( props = {} ) {
	return mount( UserActivityChart, {
		propsData: {
			username: 'username',
			recentLocalEdits: sampleRecentEdits,
			totalLocalEdits: 15,
			...props
		}
	} );
}

QUnit.test( 'renders correctly with required props', ( assert ) => {
	const wrapper = mountComponent();

	assert.true( wrapper.exists(), 'Component renders' );
	assert.true(
		wrapper.find( '.ext-checkuser-userinfocard-activity-chart' ).exists(),
		'Activity chart container exists'
	);
} );

QUnit.test( 'uses CSparkline component with correct props', ( assert ) => {
	const wrapper = mountComponent();

	const sparkline = wrapper.findComponent( { name: 'CSparkline' } );
	assert.true( sparkline.exists(), 'CSparkline component exists' );

	assert.strictEqual(
		sparkline.props( 'id' ),
		'user-activity-1umww5y',
		'CSparkline has correct id'
	);

	assert.strictEqual(
		sparkline.props( 'title' ),
		'checkuser-userinfocard-activity-chart-label: 15',
		'CSparkline has correct title'
	);

	assert.deepEqual(
		sparkline.props( 'data' ),
		sampleRecentEdits,
		'CSparkline has correct data'
	);

	assert.deepEqual(
		sparkline.props( 'dimensions' ),
		{ width: 300, height: 24 },
		'CSparkline has correct dimensions'
	);

	assert.strictEqual(
		sparkline.props( 'xAccessor' ),
		'date',
		'CSparkline has correct x-accessor'
	);

	assert.strictEqual(
		sparkline.props( 'yAccessor' ),
		'count',
		'CSparkline has correct y-accessor'
	);
} );

QUnit.test( 'displays the correct activity chart label', ( assert ) => {
	const wrapper = mountComponent();

	assert.strictEqual(
		wrapper.find( 'p' ).text(),
		'checkuser-userinfocard-activity-chart-label: 15',
		'Paragraph displays the correct activity chart label'
	);
} );

QUnit.test( 'setup function returns the correct activityChartLabel', ( assert ) => {
	const wrapper = mountComponent();

	assert.strictEqual(
		wrapper.vm.activityChartLabel,
		'checkuser-userinfocard-activity-chart-label: 15',
		'activityChartLabel is set correctly from mw.msg'
	);
} );

QUnit.test( 'Renders the timestamp of the last edit if provided', ( assert ) => {
	const wrapper = mountComponent( {
		lastEditTimestamp: '10:42, 17 (september) 2025'
	} );

	const lastEditTimestampTag = wrapper.find( '.ext-checkuser-userinfocard-latest-edit-label' );
	assert.true(
		lastEditTimestampTag.exists(),
		'Paragraph holding the last edit timestamp exists'
	);
	assert.strictEqual(
		lastEditTimestampTag.text(),
		'checkuser-userinfocard-last-edit-timestamp-label: 10:42, 17 (september) 2025',
		'Paragraph holding the last edit timestamp displays correct information'
	);
} );

QUnit.test( 'Does not render the timestamp of the last edit if not provided', ( assert ) => {
	const wrapper = mountComponent( {
		lastEditTimestamp: ''
	} );

	const lastEditTimestampTag = wrapper.findComponent( '.ext-checkuser-userinfocard-latest-edit-label' );
	assert.false(
		lastEditTimestampTag.exists(),
		'Paragraph holding the last edit timestamp does not exist'
	);
} );
