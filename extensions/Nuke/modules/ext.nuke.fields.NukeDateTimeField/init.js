// eslint-disable-next-line no-jquery/no-global-selector
$( '.ext-nuke-dateTimeField[data-ooui!=""]' )
	.each( ( _index, element ) => {
		const field = OO.ui.FieldLayout.static.infuse( $( element ) );
		const input = field.getField();

		const moment = require( 'moment' );

		function validate() {
			input.getValidity()
				.then( () => {
					field.setErrors( [] );
				} )
				.catch( () => {
					moment.relativeTimeRounding( Math.floor );
					const localDay = moment.utc().local().format( 'DD' );
					const utcDay = moment.utc().format( 'DD' );
					// we don't need to subtract a day when the utc day is different from local day
					// which implies a new day in UTC time and would result in $wgNukeMaxAge - 1
					if ( localDay !== utcDay ) {
						field.setErrors( [
							mw.msg(
								'nuke-date-limited',
								moment()
									.diff( input.mustBeAfter.format( 'YYYY-MM-DD' ), 'days' )
							)
						] );
					} else {
						field.setErrors( [
							mw.msg(
								'nuke-date-limited',
								moment()
									// `mustBeAfter` is set to always be one day before the `max` in
									// DateInputWidget::__construct. We need to remove a day to get
									// the original value back.
									.subtract( 1, 'day' )
									.diff( input.mustBeAfter.format( 'YYYY-MM-DD' ), 'days' )
							)
						] );
					}
				} );
		}

		input.on( 'change', validate );
		validate();
	} );
