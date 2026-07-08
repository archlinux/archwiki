'use strict';

/*!
 * VisualEditor MediaWiki Cite initialisation code.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

( function () {
	const modifiedToolbarGroups = [];

	mw.hook( 've.newTarget' ).add( ( target ) => {
		if ( ![ 'article', 'cx' ].includes( target.constructor.static.name ) ) {
			return;
		}
		const toolbarGroups = target.constructor.static.toolbarGroups;

		if ( modifiedToolbarGroups.includes( toolbarGroups ) ) {
			return;
		}

		if (
			mw.config.get( 'wgCiteVisualEditorOtherGroup' ) ||
			// Mobile doesn't have room for a top-level reference group, so place in the 'insert' menu as well
			( ve.init.mw.MobileArticleTarget && target instanceof ve.init.mw.MobileArticleTarget )
		) {
			// Add to the insert group (demoted)
			const insertGroup = toolbarGroups.find(
				( toolbarGroup ) => toolbarGroup.name === 'insert' ||
				// Name used in CX.
				// TODO: Change this to 'insert'
				toolbarGroup.name === 'extra'
			);
			if ( insertGroup ) {
				insertGroup.demote = [
					...( insertGroup.demote || [] ),
					{ group: 'cite' }, 'reference', 'reference/existing'
				];
			}
		} else {
			// Add after the link group
			const index = toolbarGroups.findIndex( ( toolbarGroup ) => toolbarGroup.name === 'link' );
			if ( index !== -1 ) {
				const group = {
					name: 'cite',
					type: 'list',
					title: OO.ui.deferMsg( 'cite-ve-toolbar-group-label' ),
					label: OO.ui.deferMsg( 'cite-ve-toolbar-group-label' ),
					include: [ { group: 'cite' }, 'reference', 'reference/existing' ],
					demote: [ 'reference', 'reference/existing' ]
				};
				toolbarGroups.splice( index + 1, 0, group );
			} else {
				mw.log.warn( 'No link group find in toolbar to place reference tools next to.' );
			}
		}

		modifiedToolbarGroups.push( toolbarGroups );
	} );
}() );
