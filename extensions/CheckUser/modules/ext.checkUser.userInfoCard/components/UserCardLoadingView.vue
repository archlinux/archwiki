<template>
	<div class="ext-checkuser-userinfocard-loading-indicator">
		<cdx-progress-indicator>
			{{ loadingLabel }}
		</cdx-progress-indicator>
	</div>
</template>

<script>
const { CdxProgressIndicator } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'UserCardLoadingView',
	components: {
		CdxProgressIndicator
	},
	setup() {
		const loadingLabel = mw.msg( 'checkuser-userinfocard-loading-label' );
		return { loadingLabel };
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-loading-indicator {
	overflow: hidden;
	display: flex;
	justify-content: center;
	margin-bottom: @spacing-150;
}
</style>
