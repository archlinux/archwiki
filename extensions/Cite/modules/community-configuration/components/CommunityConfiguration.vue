<template>
	<div class="ext-cite-configuration">
		<backlink-settings
			:cldr-alphabet="cldrAlphabet"
			:backlink-alphabet="backlinkAlphabet"
			@update="updateBacklinkAlphabet"></backlink-settings>
	</div>
	<cdx-button
		action="progressive"
		weight="primary"
		@click="onSubmit"
	>
		{{ $i18n( 'cite-configuration-submit' ).text() }}
	</cdx-button>
</template>

<script>
const BacklinkSettings = require( './BacklinkSettings.vue' );
const { CdxButton } = require( '../codex.js' );
let backlinkAlphabetText = mw.config.get( 'wgCiteBacklinkAlphabet' );

/**
 * Cite special page providing a front-end to community configuration.
 */
// @vue/component
module.exports = {
	name: 'CommunityConfiguration',
	components: {
		BacklinkSettings,
		CdxButton
	},
	setup() {
		return {
			backlinkAlphabet: backlinkAlphabetText,
			cldrAlphabet: mw.config.get( 'wgCldrAlphabet' ),
			providerId: mw.config.get( 'wgCiteProviderId' ),
			mwApi: new mw.Api()
		};
	},
	methods: {
		updateBacklinkAlphabet( value ) {
			backlinkAlphabetText = value;
		},
		onSubmit() {
			// TODO: Reuse admin check from community config extension
			// TODO: Handle input validation and errors
			// TODO: Handle API errors
			// TODO: Show success message
			this.mwApi.postWithToken( 'csrf', {
				action: 'communityconfigurationedit',
				provider: this.providerId,
				content: JSON.stringify( {
					// eslint-disable-next-line camelcase
					Cite_Settings: {
						backlinkAlphabet: backlinkAlphabetText
					}
				} ),
				// TODO: Let users set the edit summary message
				summary: 'CommunityConfig Edit',
				formatversion: 2,
				errorformat: 'html'
			} );
		}
	}
};
</script>

<style lang="less">
// To access Codex design tokens and mixins inside Vue files, import MediaWiki skin variables.
@import 'mediawiki.skin.variables.less';
</style>
