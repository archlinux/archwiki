<template>
	<div class="ext-checkuser-userinfocard-activity-chart">
		<c-sparkline
			:id="componentId"
			:title="activityChartLabel"
			:data="recentLocalEdits"
			:dimensions="{ width: 300, height: 24 }"
			x-accessor="date"
			y-accessor="count"
		></c-sparkline>
	</div>
	<p>
		<span
			class="ext-checkuser-userinfocard-activity-chart-label">
			{{ activityChartLabel }}
		</span>
		<span
			v-if="latestEditMessage"
			class="ext-checkuser-userinfocard-latest-edit-label">
			{{ latestEditMessage }}
		</span>
	</p>
</template>

<script>
const CSparkline = require( '../../vue-components/CSparkline.vue' );
const { hashUsername } = require( '../util.js' );

// @vue/component
module.exports = exports = {
	name: 'UserActivityChart',
	components: { CSparkline },
	props: {
		username: {
			type: [ String, Number ],
			required: true
		},
		recentLocalEdits: {
			// Expected format: [ { date: Date, count: number }, ... ]
			type: Array,
			required: true
		},
		totalLocalEdits: {
			type: Number,
			required: true
		},
		lastEditTimestamp: {
			type: String,
			required: true
		}
	},
	setup( props ) {
		const componentId = `user-activity-${ hashUsername( props.username ) }`;
		const activityChartLabel = mw.msg(
			'checkuser-userinfocard-activity-chart-label', props.totalLocalEdits
		);
		const latestEditMessage = props.lastEditTimestamp ?
			mw.msg(
				'checkuser-userinfocard-last-edit-timestamp-label',
				props.lastEditTimestamp
			) :
			'';

		return {
			activityChartLabel,
			componentId,
			latestEditMessage
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-activity-chart {
	margin-top: @spacing-75;
}

.ext-checkuser-userinfocard-activity-chart-label {
	display: block;
	font-size: @font-size-x-small;
}

.ext-checkuser-userinfocard-latest-edit-label {
	font-size: @font-size-x-small;
}
</style>
