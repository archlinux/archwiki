<template>
	<cdx-message type="error" class="ext-checkuser-userinfocard-error">
		<p><strong>{{ strongMessage }}</strong></p>
		<p>{{ message }}</p>
	</cdx-message>
</template>

<script>
const { CdxMessage } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'UserInfoCardError',
	components: { CdxMessage },
	props: {
		message: {
			type: String,
			required: true
		}
	},
	setup() {
		const strongMessage = mw.msg( 'checkuser-userinfocard-error-title' );
		return { strongMessage };
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-error {
	margin: @spacing-100 @spacing-0;
}
</style>
