<template>
	<cdx-message class="mw-special-OATHManage-2fa-groups-notice" type="warning">
		<div
			v-if="singleEntry"
			v-i18n-html:oathauth-2fa-groups-notice-single="[ groupCount, groupsWithLinks[0].groupsText, groupsWithLinks[0].wiki, groupsWithLinks[0].link ]">
		</div>
		<div v-else>
			<div v-i18n-html:oathauth-2fa-groups-notice-multiple="[ groupCount ]"></div>
			<div
				v-i18n-html:oathauth-2fa-groups-notice-multiple-links-intro="[ groupCount ]"
				class="mw-special-OATHManage-2fa-groups-list-intro"></div>
			<ul>
				<li
					v-for="group in groupsWithLinks"
					:key="group.url + group.groupNames"
					v-i18n-html:oathauth-2fa-groups-notice-multiple-links-entry="[ group.groupNames.length, group.groupsText, group.wiki, group.link ]">
				</li>
			</ul>
		</div>
	</cdx-message>
</template>

<script>
const { defineComponent, computed } = require( 'vue' );
const { CdxMessage } = require( './codex.js' );

module.exports = exports = defineComponent( {
	components: {
		CdxMessage
	},
	props: {
		groups: {
			type: Array,
			default: () => []
		}
	},
	setup( props ) {
		const groupCount = computed( () => props.groups.reduce( ( count, group ) => count + group.groupNames.length, 0 ) );
		const singleEntry = computed( () => props.groups.length === 1 );
		const groupsWithLinks = computed( () => props.groups.map( ( group ) => {
			group = Object.assign( {}, group );
			if ( group.url ) {
				const link = document.createElement( 'a' );
				link.href = group.url;
				link.textContent = group.page;
				group.link = link;
			} else {
				group.link = mw.msg( 'oathauth-2fa-groups-notice-unknown-page' );
			}
			group.groupsText = mw.language.listToText( group.groupNames );
			return group;
		} ) );

		return {
			groupsWithLinks,
			groupCount,
			singleEntry
		};
	}
} );
</script>
