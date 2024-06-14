/*!
 * MediaWiki Widgets - UserInputWidget class.
 *
 * @copyright 2011-2015 MediaWiki Widgets Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */
( function () {

	/**
	 * Creates a mw.widgets.UserInputWidget object.
	 *
	 * @class
	 * @extends OO.ui.TextInputWidget
	 * @mixes OO.ui.mixin.LookupElement
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @param {number} [config.limit=10] Number of results to show
	 * @param {mw.Api} [config.api] API object to use, creates a default mw.Api instance if not specified
	 */
	mw.widgets.UserInputWidget = function MwWidgetsUserInputWidget( config ) {
		// Config initialization
		config = config || {};

		// Parent constructor
		mw.widgets.UserInputWidget.super.call( this, $.extend( {}, config, { autocomplete: false } ) );

		// Mixin constructors
		OO.ui.mixin.LookupElement.call( this, config );

		// Properties
		this.limit = config.limit || 10;
		this.api = config.api || new mw.Api();

		// Initialization
		this.$element.addClass( 'mw-widget-userInputWidget' );
		this.lookupMenu.$element.addClass( 'mw-widget-userInputWidget-menu' );
	};

	/* Setup */

	OO.inheritClass( mw.widgets.UserInputWidget, OO.ui.TextInputWidget );
	OO.mixinClass( mw.widgets.UserInputWidget, OO.ui.mixin.LookupElement );

	/* Methods */

	/**
	 * Handle menu item 'choose' event, updating the text input value to the value of the clicked item.
	 *
	 * @param {OO.ui.MenuOptionWidget} item Selected item
	 */
	mw.widgets.UserInputWidget.prototype.onLookupMenuChoose = function ( item ) {
		this.closeLookupMenu();
		this.setLookupsDisabled( true );
		this.setValue( item.getData() );
		this.setLookupsDisabled( false );
	};

	/**
	 * @inheritdoc
	 */
	mw.widgets.UserInputWidget.prototype.focus = function () {
		// Prevent programmatic focus from opening the menu
		this.setLookupsDisabled( true );

		// Parent method
		var retval = mw.widgets.UserInputWidget.super.prototype.focus.apply( this, arguments );

		this.setLookupsDisabled( false );

		return retval;
	};

	/**
	 * @inheritdoc
	 */
	mw.widgets.UserInputWidget.prototype.getLookupRequest = function () {
		return this.api.get( {
			action: 'query',
			list: 'allusers',
			auprefix: this.value,
			aulimit: this.limit
		} );
	};

	/**
	 * Get lookup cache item from server response data.
	 *
	 * @method
	 * @param {any} response Response from server
	 * @return {Object}
	 */
	mw.widgets.UserInputWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
		return response.query.allusers || {};
	};

	/**
	 * Get list of menu items from a server response.
	 *
	 * @param {Object} data Query result
	 * @return {OO.ui.MenuOptionWidget[]} Menu items
	 */
	mw.widgets.UserInputWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
		var items = [];

		for ( var i = 0, len = data.length; i < len; i++ ) {
			var user = data[ i ] || {};
			items.push( new OO.ui.MenuOptionWidget( {
				label: user.name,
				data: user.name
			} ) );
		}

		return items;
	};

}() );
