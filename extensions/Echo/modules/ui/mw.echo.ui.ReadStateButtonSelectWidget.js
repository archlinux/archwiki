( function () {
	/**
	 * A select widget for notification read state: 'all', 'read' or 'unread'
	 *
	 * @class
	 * @extends OO.ui.ButtonSelectWidget
	 *
	 * @constructor
	 * @param {Object} [config] Configuration object
	 */
	mw.echo.ui.ReadStateButtonSelectWidget = function MwEchoUiReadStateButtonSelectWidget( config ) {
		// Parent constructor
		mw.echo.ui.ReadStateButtonSelectWidget.super.call( this, Object.assign( {}, config, {
			items: [
				new OO.ui.ButtonOptionWidget( {
					data: 'all',
					label: mw.msg( 'notification-inbox-filter-all' )
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'read',
					label: mw.msg( 'notification-inbox-filter-read' )
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'unread',
					label: mw.msg( 'notification-inbox-filter-unread' )
				} )
			]
		} ) );

		this.connect( this, { choose: 'onChoose' } );

		this.$element
			.addClass( 'mw-echo-ui-readStateButtonSelectWidget' );
	};

	/* Initialization */

	OO.inheritClass( mw.echo.ui.ReadStateButtonSelectWidget, OO.ui.ButtonSelectWidget );

	/* Events */

	/**
	 * @event mw.echo.ui.ReadStateButtonSelectWidget#filter
	 * @param {string} readState The chosen read state
	 */

	/* Methods */

	/**
	 * Respond to choose event
	 *
	 * @param {OO.ui.ButtonOptionWidget} item Chosen item
	 * @fires mw.echo.ui.ReadStateButtonSelectWidget#filter
	 */
	mw.echo.ui.ReadStateButtonSelectWidget.prototype.onChoose = function ( item ) {
		const data = item && item.getData();

		if ( data ) {
			this.emit( 'filter', data );
		}
	};
}() );
