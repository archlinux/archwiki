( function () {
	/**
	 * Secondary menu item
	 *
	 * @class
	 * @extends OO.ui.ButtonOptionWidget
	 * @mixes OO.ui.mixin.PendingElement
	 *
	 * @constructor
	 * @param {Object} [config] Configuration object
	 * @param {string} [config.type] Optional action type. Used to note a dynamic action, by setting it to 'dynamic-action'
	 * @param {string} [config.url] Item URL for links
	 * @param {string} [config.tooltip] Tooltip for links
	 * @param {string} [config.description] An optional description for the item
	 * @param {Object} [config.actionData] Action data
	 * @param {boolean} [config.prioritized] The item is prioritized outside the
	 *  popup menu.
	 */
	mw.echo.ui.MenuItemWidget = function MwEchoUiMenuItemWidget( config ) {
		config = config || {};

		this.dynamic = config.type === 'dynamic-action';
		// Needs to be set before parent constructor is called
		// as it changes the value of getTagName.
		this.isLink = config.url && !this.isDynamicAction();

		// Parent constructor
		mw.echo.ui.MenuItemWidget.super.call( this, Object.assign( { framed: false }, config ) );

		// Mixin constructors
		OO.ui.mixin.PendingElement.call( this, config );

		this.prioritized = !!config.prioritized;
		this.messages = this.isDynamicAction() ?
			config.actionData.messages :
			{};

		this.actionData = config.actionData || {};

		// Optional description
		this.descriptionLabel = new OO.ui.LabelWidget( {
			classes: [ 'mw-echo-ui-menuItemWidget-description' ],
			label: config.description || ''
		} );
		this.descriptionLabel.toggle( !this.prioritized && config.description );

		this.$label.append( this.descriptionLabel.$element );

		// Build the option
		this.$element
			.addClass( 'mw-echo-ui-menuItemWidget' )
			.toggleClass( 'mw-echo-ui-menuItemWidget-prioritized', this.prioritized )
			.toggleClass( 'mw-echo-ui-menuItemWidget-dynamic-action', this.isDynamicAction() );

		if ( this.isLink ) {
			this.$element.attr( {
				href: config.url,
				title: config.tooltip
			} );
		}
	};

	/* Initialization */

	OO.inheritClass( mw.echo.ui.MenuItemWidget, OO.ui.ButtonOptionWidget );
	OO.mixinClass( mw.echo.ui.MenuItemWidget, OO.ui.mixin.PendingElement );

	/* Static Properties */

	mw.echo.ui.MenuItemWidget.static.highlightable = false;
	mw.echo.ui.MenuItemWidget.static.pressable = false;

	/* Methods */

	mw.echo.ui.MenuItemWidget.prototype.getTagName = function () {
		return this.isLink ? 'a' : 'div';
	};

	mw.echo.ui.MenuItemWidget.prototype.onClick = function ( e ) {
		// Stop propagation, so that the default dynamic action of the notification isn't triggered
		// (e.g. expanding a bundled notification).
		e.stopPropagation();

		// If this is a dynamic action, also prevent default to disable the native browser behavior,
		// the default link of the notification won't be followed.
		// (If this is a link, default link of the notification is ignored as native browser behavior.)
		if ( !this.isLink ) {
			e.preventDefault();
		}

		return mw.echo.ui.MenuItemWidget.super.prototype.onClick.apply( this, arguments );
	};

	mw.echo.ui.MenuItemWidget.prototype.isSelectable = function () {
		// If we have a link force selectability to false, otherwise defer to parent method
		// Without a link (for dynamic actions or specific internal actions) we need this widget
		// to be selectable so it emits the 'choose' event
		return !this.isLink && mw.echo.ui.MenuItemWidget.super.prototype.isSelectable.apply( this, arguments );
	};

	/**
	 * Check whether this item is prioritized
	 *
	 * @return {boolean} Item is prioritized
	 */
	mw.echo.ui.MenuItemWidget.prototype.isPrioritized = function () {
		return this.prioritized;
	};

	/**
	 * @typedef {Object} ConfirmationMessages
	 * @memberof mw.echo.ui.MenuItemWidget
	 * @property {string} title Title for the confirmation dialog
	 * @property {string} description Description for the confirmation dialog
	 */

	/**
	 * Get the messages for the confirmation dialog
	 * We expect optionally two messages - title and description.
	 *
	 * NOTE: The messages are parsed as HTML. If user-input is expected
	 * please make sure to properly escape it.
	 *
	 * @return {mw.echo.ui.MenuItemWidget.ConfirmationMessages} Messages for the confirmation dialog
	 */
	mw.echo.ui.MenuItemWidget.prototype.getConfirmationMessages = function () {
		return this.messages.confirmation;
	};

	/**
	 * Get the action data associated with this item
	 *
	 * @return {Object} Action data
	 */
	mw.echo.ui.MenuItemWidget.prototype.getActionData = function () {
		return this.actionData;
	};

	/**
	 * This item is a dynamic action
	 *
	 * @return {boolean} Item is a dynamic action
	 */
	mw.echo.ui.MenuItemWidget.prototype.isDynamicAction = function () {
		return this.dynamic;
	};
}() );
