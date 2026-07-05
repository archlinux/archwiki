'use strict';

/**
 * Runs the JavaScript for the Special:SuggestedInvestigations page, called from the dispatcher.
 *
 * @param {Window} win
 */
module.exports = function ( win ) {
	const $userLists = $( '.mw-checkuser-suggestedinvestigations-users' );
	$userLists.each( function () {
		const $list = $( this );
		const $hiddenByDefault = $list.find( 'li.mw-checkuser-suggestedinvestigations-user-defaulthide' );

		if ( $hiddenByDefault.length === 0 ) {
			return;
		}

		const numUsers = mw.language.convertNumber( $hiddenByDefault.length );

		// If there's a collapsible part, create buttons to show/hide the hidden items
		const $showLessButton = $( '<button>' );
		const $showMoreButton = $( '<button>' )
			.addClass( 'cdx-button cdx-button--weight-quiet cdx-button--action-progressive' )
			.attr( 'type', 'button' )
			.text( mw.msg( 'checkuser-suggestedinvestigations-user-showmore', numUsers ) )
			.on( 'click', () => {
				$hiddenByDefault.removeClass( 'mw-checkuser-suggestedinvestigations-user-defaulthide' );
				$showMoreButton.detach();
				$list.after( $showLessButton );
			} );

		$showLessButton.addClass( 'cdx-button cdx-button--weight-quiet cdx-button--action-progressive' )
			.attr( 'type', 'button' )
			.text( mw.msg( 'checkuser-suggestedinvestigations-user-showless', numUsers ) )
			.on( 'click', () => {
				$hiddenByDefault.addClass( 'mw-checkuser-suggestedinvestigations-user-defaulthide' );
				$showLessButton.detach();
				$list.after( $showMoreButton );
			} );

		$list.after( $showMoreButton );
	} );

	const Vue = require( 'vue' );
	const ChangeInvestigationStatusDialog = require( './components/ChangeInvestigationStatusDialog.vue' );

	let suggestedInvestigationsChangeStatusApp = null;

	$( '.mw-checkuser-suggestedinvestigations-change-status-button' ).on( 'click', function ( event ) {
		event.preventDefault();

		// Unmount the previous instance of the change status dialog, if any
		if ( suggestedInvestigationsChangeStatusApp !== null ) {
			suggestedInvestigationsChangeStatusApp.unmount();
		}

		const args = {
			caseId: Number( $( this ).attr( 'data-case-id' ) ),
			initialStatus: $( this ).attr( 'data-case-status' ),
			initialStatusReason: $( this ).attr( 'data-case-status-reason' ),
			caseSignals: $( this ).attr( 'data-case-signals' ).split( ' ' )
		};

		suggestedInvestigationsChangeStatusApp = Vue.createMwApp(
			ChangeInvestigationStatusDialog, args
		);
		suggestedInvestigationsChangeStatusApp
			.mount( '#ext-suggestedinvestigations-change-status-app' );
	} );

	// Render the signals popover when the popover open icon is clicked
	const SignalsPopover = require( './components/SignalsPopover.vue' );
	let suggestedInvestigationsSignalsPopover = null;

	$( '.ext-checkuser-suggestedinvestigations-signals-popover-icon' ).on( 'click', function ( event ) {
		event.preventDefault();

		if ( suggestedInvestigationsSignalsPopover === null ) {
			const args = { anchor: this };
			suggestedInvestigationsSignalsPopover = Vue.createMwApp( SignalsPopover, args )
				.mount( '#ext-suggestedinvestigations-signals-popover-app' );
		} else {
			if ( suggestedInvestigationsSignalsPopover.isPopoverOpen() ) {
				suggestedInvestigationsSignalsPopover.closePopover();
			} else {
				suggestedInvestigationsSignalsPopover.openPopover();
			}
		}
	} );

	// Render the filter dialog when any of the filter buttons are clicked
	const FilterDialog = require( './components/FilterDialog.vue' );

	let suggestedInvestigationsFilterApp = null;
	const activeFilters = mw.config.get( 'wgCheckUserSuggestedInvestigationsActiveFilters' );

	$( '.mw-checkuser-suggestedinvestigations-filter-button' ).on( 'click', ( event ) => {
		event.preventDefault();

		// Unmount the previous instance of the filter dialog, if any
		if ( suggestedInvestigationsFilterApp !== null ) {
			suggestedInvestigationsFilterApp.unmount();
		}

		suggestedInvestigationsFilterApp = Vue.createMwApp(
			FilterDialog, { initialFilters: activeFilters }
		);
		suggestedInvestigationsFilterApp
			.mount( '#ext-suggestedinvestigations-filter-app' );
	} );

	// Re-implement the Vue.js code to fade out a dismissable Codex message
	// This is done because there is no way to infuse a CSS-only Codex
	// component into a Vue component
	$( '.ext-checkuser-suggestedinvestigations-warning-dismiss' ).on( 'click', function () {
		const $warningMessage = $( this ).parents( '.cdx-message--user-dismissable' );
		$warningMessage.addClass(
			'ext-checkuser-suggestedinvestigations-dismissable-warning--fading'
		);

		win.setTimeout( () => {
			$warningMessage.addClass(
				'ext-checkuser-suggestedinvestigations-dismissable-warning--hidden'
			);
		}, 400 );

		// Hide the "this tool has private data" warning message for future page loads if
		// the user clicks the "x" button on the message
		// eslint-disable-next-line no-jquery/no-class-state
		if ( $warningMessage.hasClass(
			'ext-checkuser-suggestedinvestigations-private-data-warning'
		) ) {
			const api = new mw.Api();
			api.saveOption(
				'checkuser-suggested-investigations-private-data-warning-seen',
				1,
				{
					global: 'update'
				}
			);
		}
	} );
};
