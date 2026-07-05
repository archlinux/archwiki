<template>
	<div v-if="visible" class="ext-checkuser-tempaccount-specialblock-ips">
		<!-- eslint-disable-next-line vue/no-v-html -->
		<p v-html="message"></p>
	</div>
</template>

<script>
const { computed, defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'TempUsersMessage',
	props: {
		targetUser: { type: [ String, null ], required: true }
	},
	setup( props ) {
		const visible = computed( () => mw.util.isIPAddress( props.targetUser, true ) );
		const message = computed( () => {
			if ( visible.value ) {
				const isCidr = !mw.util.isIPAddress( props.targetUser );
				const ipType = isCidr ? 'iprange' : 'ip';
				// Messages used:
				// * checkuser-tempaccount-specialblock-ip-target
				// * checkuser-tempaccount-specialblock-iprange-target
				return mw.message( `checkuser-tempaccount-specialblock-${ ipType }-target`, props.targetUser ).parse();
			}
			return '';
		} );

		return {
			visible,
			message
		};
	}
} );
</script>
