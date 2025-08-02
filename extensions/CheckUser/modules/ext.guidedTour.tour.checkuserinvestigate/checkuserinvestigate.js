/*
 * Special:Invesitgate guided tour
 */
( function ( gt ) {
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'Investigate' ) {
		return;
	}

	if ( $( '.ext-checkuser-investigate-table-compare' ).length === 0 ) {
		return;
	}

	const canBlock = mw.config.get( 'wgCheckUserInvestigateCanBlock' );

	const tour = new gt.TourBuilder( {
		name: 'checkuserinvestigate',
		shouldLog: true,
		isSinglePage: false
	} );

	tour.firstStep( {
		name: 'useragents',
		titlemsg: 'checkuser-investigate-tour-useragents-title',
		descriptionmsg: 'checkuser-investigate-tour-useragents-desc',
		attachTo: '.ext-checkuser-compare-table-cell-user-agent',
		position: 'left',
		closeOnClickOutside: false,
		overlay: true,
		onShow: function () {
			$( this.attachTo ).first().trigger( 'mouseover' );
		},
		onHide: function () {
			$( this.attachTo ).first().trigger( 'mouseout' );

			// Api.saveOption will save a string instead of a bool. :(
			( new mw.Api() ).saveOption( 'checkuser-investigate-tour-seen', 1 );
		}
	} )
		.next( 'addusertargets' );

	function handleIpTargetOnShow() {
		const $cell = $( '.ext-checkuser-compare-table-cell-ip-target' ).first();

		$cell.trigger( 'mouseover' );
		$cell.addClass( 'ext-checkuser-investigate-active' );
		// @TODO This causes a flicker between steps, maybe there is a way to force it to stay open?
		$( '.ext-checkuser-investigate-table-select .oo-ui-buttonElement-button', $cell ).first().trigger(
			$.Event( 'click', { which: OO.ui.MouseButtons.LEFT } )
		);
	}

	function handleIpTargetOnHide() {
		$( '.ext-checkuser-compare-table-cell-ip-target' ).first().trigger( 'mouseout' );
		$( '.ext-checkuser-compare-table-cell-ip-target' ).first().removeClass( 'ext-checkuser-investigate-active' );
	}

	tour.step( {
		name: 'addusertargets',
		titlemsg: 'checkuser-investigate-tour-addusertargets-title',
		descriptionmsg: 'checkuser-investigate-tour-addusertargets-desc',
		attachTo: '.ext-checkuser-investigate-button-add-user-targets',
		position: 'right',
		closeOnClickOutside: false,
		autoFocus: false,
		overlay: true,
		onShow: handleIpTargetOnShow,
		onHide: handleIpTargetOnHide
	} )
		.back( 'useragents' )
		.next( 'filterip' );

	tour.step( {
		name: 'filterip',
		titlemsg: 'checkuser-investigate-tour-filterip-title',
		descriptionmsg: 'checkuser-investigate-tour-filterip-desc',
		attachTo: '.ext-checkuser-investigate-button-filter-ip',
		position: 'right',
		closeOnClickOutside: false,
		autoFocus: false,
		overlay: true,
		onShow: handleIpTargetOnShow,
		onHide: handleIpTargetOnHide,
		buttons: [
			{
				action: 'back'
			},
			{
				action: 'next'
			}
		]
	} )
		.back( 'addusertargets' )
		.next( canBlock ? 'block' : 'copywikitext' );

	tour.step( {
		name: 'block',
		titlemsg: 'checkuser-investigate-tour-block-title',
		descriptionmsg: 'checkuser-investigate-tour-block-desc',
		attachTo: '.ext-checkuser-investigate-subtitle-block-button',
		position: 'bottomLeft',
		closeOnClickOutside: false,
		overlay: true,
		buttons: [
			{
				action: 'back'
			},
			{
				action: 'next'
			}
		]
	} )
		.back( 'filterip' )
		.next( 'copywikitext' );

	tour.step( {
		name: 'copywikitext',
		titlemsg: 'checkuser-investigate-tour-copywikitext-title',
		descriptionmsg: 'checkuser-investigate-tour-copywikitext-desc',
		attachTo: '.ext-checkuser-investigate-copy-button',
		position: 'topLeft',
		closeOnClickOutside: false,
		overlay: true,
		buttons: [
			{
				action: 'back'
			},
			{
				action: 'end'
			}
		]
	} )
		.back( canBlock ? 'block' : 'filterip' );

}( mw.guidedTour ) );
