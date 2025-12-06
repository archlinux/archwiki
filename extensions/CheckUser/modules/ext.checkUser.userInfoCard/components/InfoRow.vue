<template>
	<p class="ext-checkuser-userinfocard-short-paragraph">
		<cdx-icon
			v-if="icon"
			:icon="icon"
			:class="iconClass"
		></cdx-icon>
		<slot></slot>
	</p>
</template>

<script>
const { CdxIcon } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'InfoRow',
	components: { CdxIcon },
	props: {
		icon: { type: [ String, Object ], default: null },
		iconClass: { type: String, default: '' }
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

p.ext-checkuser-userinfocard-short-paragraph {
	margin: @spacing-0 @spacing-0 @spacing-25;

	/* stylelint-disable-next-line selector-class-pattern */
	.cdx-icon {
		min-width: @size-100;
		min-height: @size-100;
		width: @size-100;
		height: @size-100;
	}
}
</style>
