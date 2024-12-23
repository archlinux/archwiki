( function () {
	/**
	 * A filter for cross-wiki unread notifications
	 *
	 * @class
	 * @extends OO.ui.Widget
	 * @mixes OO.ui.mixin.PendingElement
	 *
	 * @constructor
	 * @param {mw.echo.Controller} controller Echo controller
	 * @param {mw.echo.dm.FiltersModel} filtersModel Filter model
	 * @param {Object} [config] Configuration object
	 */
	mw.echo.ui.CrossWikiUnreadFilterWidget = function MwEchoUiCrossWikiUnreadFilterWidget( controller, filtersModel, config ) {
		config = config || {};

		// Parent constructor
		mw.echo.ui.CrossWikiUnreadFilterWidget.super.call( this,
			// Sorting callback
			( a, b ) => {
				// Local source is always first
				if ( a.getSource() === 'local' ) {
					return -1;
				} else if ( b.getSource() === 'local' ) {
					return 1;
				}

				const diff = Number( b.getTotalCount() ) - Number( a.getTotalCount() );
				if ( diff !== 0 ) {
					return diff;
				}

				// Fallback on Source
				return b.getSource() - a.getSource();
			},
			// Config
			config
		);
		// Mixin
		OO.ui.mixin.PendingElement.call( this, config );

		this.controller = controller;
		this.model = filtersModel;
		this.previousPageSelected = null;

		const titleWidget = new OO.ui.LabelWidget( {
			classes: [ 'mw-echo-ui-crossWikiUnreadFilterWidget-title' ],
			label: mw.msg( 'echo-specialpage-pagefilters-title' )
		} );
		const subtitleWidget = new OO.ui.LabelWidget( {
			classes: [ 'mw-echo-ui-crossWikiUnreadFilterWidget-subtitle' ],
			label: mw.msg( 'echo-specialpage-pagefilters-subtitle' )
		} );

		// Events
		this.aggregate( { choose: 'pageFilterChoose' } );
		this.connect( this, { pageFilterChoose: 'onPageFilterChoose' } );

		this.$element
			.addClass( 'mw-echo-ui-crossWikiUnreadFilterWidget' )
			.append(
				titleWidget.$element,
				subtitleWidget.$element,
				this.$group
					.addClass( 'mw-echo-ui-crossWikiUnreadFilterWidget-group' )
			);
	};

	/* Initialization */

	OO.inheritClass( mw.echo.ui.CrossWikiUnreadFilterWidget, mw.echo.ui.SortedListWidget );
	OO.mixinClass( mw.echo.ui.CrossWikiUnreadFilterWidget, OO.ui.mixin.PendingElement );

	/* Events */

	/**
	 * A source page filter was chosen
	 *
	 * @event mw.echo.ui.CrossWikiUnreadFilterWidget#filter
	 * @param {string} source Source symbolic name
	 * @param {number} [pageId] Chosen page ID
	 */

	/* Methods */

	/**
	 * Respond to choose event in one of the page filter widgets
	 *
	 * @param {mw.echo.ui.PageFilterWidget} widget The widget the event originated from
	 * @param {mw.echo.ui.PageNotificationsOptionWidget} item The chosen item
	 * @fires mw.echo.ui.CrossWikiUnreadFilterWidget#filter
	 */
	mw.echo.ui.CrossWikiUnreadFilterWidget.prototype.onPageFilterChoose = function ( widget, item ) {
		const source = widget.getSource(),
			page = item && item.getData();

		if ( item ) {
			this.setItemSelected( item );
			// Emit a choice
			this.emit( 'filter', source, page );
		}
	};

	/**
	 * Set the selected item
	 *
	 * @param {mw.echo.ui.PageNotificationsOptionWidget} item Item to select
	 */
	mw.echo.ui.CrossWikiUnreadFilterWidget.prototype.setItemSelected = function ( item ) {
		// Unselect the previous item
		if ( this.previousPageSelected ) {
			this.previousPageSelected.setSelected( false );
		}
		item.setSelected( true );
		this.previousPageSelected = item;
	};

	/**
	 * Populate the sources
	 */
	mw.echo.ui.CrossWikiUnreadFilterWidget.prototype.populateSources = function () {
		this.pushPending();
		this.controller.fetchUnreadPagesByWiki()
			.then( this.populateDataFromModel.bind( this ) )
			.always( this.popPending.bind( this ) );
	};

	/**
	 * Populate the widget from the model data
	 */
	mw.echo.ui.CrossWikiUnreadFilterWidget.prototype.populateDataFromModel = function () {
		const widgets = [],
			sourcePageModel = this.model.getSourcePagesModel(),
			selectedSource = sourcePageModel.getCurrentSource(),
			selectedPage = sourcePageModel.getCurrentPage(),
			sources = sourcePageModel.getSourcesArray();

		for ( let i = 0; i < sources.length; i++ ) {
			const source = sources[ i ];
			const widget = new mw.echo.ui.PageFilterWidget(
				sourcePageModel,
				source,
				{
					title: sourcePageModel.getSourceTitle( source ),
					unreadCount: sourcePageModel.getSourceTotalCount( source ),
					initialSelection: this.previousPageSelected && this.previousPageSelected.getData()
				}
			);

			widgets.push( widget );
		}

		this.clearItems();
		this.addItems( widgets );

		// Select the current source
		const selectedWidget = this.findItemFromData( selectedSource );
		let item;
		if ( selectedPage ) {
			// Select a specific page
			item = selectedWidget.findItemFromData( selectedPage );
		} else {
			// The wiki title is selected
			item = selectedWidget.getTitleItem();
		}
		this.setItemSelected( item );
	};

}() );
