/**
 * This is modelled after {@see OO.ui.OutlineSelectWidget}.
 *
 * @class
 * @extends OO.ui.SelectWidget
 *
 * @constructor
 * @param {Object} config
 * @cfg {ve.ui.MWTransclusionOutlineParameterWidget[]} items
 */
ve.ui.MWTransclusionOutlineParameterSelectWidget = function VeUiMWTransclusionOutlineParameterSelectWidget( config ) {
	// Parent constructor
	ve.ui.MWTransclusionOutlineParameterSelectWidget.super.call( this, ve.extendObject( config, {
		classes: [ 've-ui-mwTransclusionOutlineParameterSelectWidget' ],
		multiselect: true
	} ) );

	// Mixin constructors
	OO.ui.mixin.TabIndexedElement.call( this, {
		tabIndex: this.isEmpty() ? -1 : 0
	} );
	ve.ui.MWAriaDescribe.call( this, config );

	this.$element
		.on( {
			focus: this.bindDocumentKeyDownListener.bind( this ),
			blur: this.unbindDocumentKeyDownListener.bind( this )
		} );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionOutlineParameterSelectWidget, OO.ui.SelectWidget );
OO.mixinClass( ve.ui.MWTransclusionOutlineParameterSelectWidget, OO.ui.mixin.TabIndexedElement );
OO.mixinClass( ve.ui.MWTransclusionOutlineParameterSelectWidget, ve.ui.MWAriaDescribe );

/* Events */

/**
 * This is fired instead of the "choose" event from the {@see OO.ui.SelectWidget} base class when
 * pressing space on a parameter to toggle it, without loosing the focus.
 *
 * @event templateParameterSelectionChanged
 * @param {ve.ui.MWTransclusionOutlineParameterWidget} item
 * @param {boolean} selected
 */

/* Static Methods */

/**
 * @param {Object} config
 * @param {string} config.data Parameter name
 * @param {string} config.label
 * @param {boolean} [config.required=false] Required parameters can't be unchecked
 * @param {boolean} [config.selected=false] If the parameter is currently used (checked)
 * @return {ve.ui.MWTransclusionOutlineParameterWidget}
 */
ve.ui.MWTransclusionOutlineParameterSelectWidget.static.createItem = function ( config ) {
	return new ve.ui.MWTransclusionOutlineParameterWidget( config );
};

/* Methods */

/**
 * @inheritDoc OO.ui.mixin.GroupElement
 * @param {ve.ui.MWTransclusionOutlineParameterWidget[]} items
 * @param {number} [index]
 * @return {ve.ui.MWTransclusionOutlineParameterSelectWidget}
 */
ve.ui.MWTransclusionOutlineParameterSelectWidget.prototype.addItems = function ( items, index ) {
	var self = this;
	items.forEach( function ( item ) {
		item.connect( self, {
			change: [ 'onCheckboxChange', item ]
		} );
	} );

	ve.ui.MWTransclusionOutlineParameterSelectWidget.super.prototype.addItems.call( this, items, index );
	this.setTabIndex( this.isEmpty() ? -1 : 0 );
	return this;
};

/**
 * @param {string} [paramName] Parameter name to highlight, e.g. "param1". Omit for no highlight.
 */
ve.ui.MWTransclusionOutlineParameterSelectWidget.prototype.highlightParameter = function ( paramName ) {
	var item = this.findItemFromData( paramName );
	// Intentionally drop any highlighting if the parameter can't be found
	this.highlightItem( item );
	if ( item ) {
		this.scrollItemIntoView( item );
	}
};

/**
 * @param {string} paramName
 */
ve.ui.MWTransclusionOutlineParameterSelectWidget.prototype.markParameterAsUnused = function ( paramName ) {
	// There is no OO.ui.SelectWidget.unselectItemByData(), we need to do this manually
	var item = this.findItemFromData( paramName );
	if ( item ) {
		item.setSelected( false );
	}
};

/**
 * @private
 * @param {ve.ui.MWTransclusionOutlineParameterWidget} item
 * @param {boolean} value
 */
ve.ui.MWTransclusionOutlineParameterSelectWidget.prototype.onCheckboxChange = function ( item, value ) {
	// This extra check shouldn't be necessary, but better be safe than sorry
	if ( item.isSelected() !== value ) {
		// Note: This should have been named `toggle…` as it toggles the item's selection
		this.chooseItem( item );
	}
};

/**
 * @inheritDoc OO.ui.SelectWidget
 */
ve.ui.MWTransclusionOutlineParameterSelectWidget.prototype.onFocus = function () {
	if ( !this.findHighlightedItem() ) {
		this.highlightItem( this.items[ 0 ] );
	}
	// Don't call the parent. It makes assumptions that conflict with how we use selections.
};

/**
 * @inheritDoc OO.ui.SelectWidget
 * @param {jQuery.Event} e
 * @fires choose
 */
ve.ui.MWTransclusionOutlineParameterSelectWidget.prototype.onMouseDown = function ( e ) {
	if ( e.which === OO.ui.MouseButtons.LEFT ) {
		var item = this.findTargetItem( e );
		// Same as pressing enter, see below.
		if ( item && item.isSelected() ) {
			this.emit( 'choose', item, item.isSelected() );

			// Don't call the parent, i.e. can't click to unselect the item
			return false;
		}
	}

	ve.ui.MWTransclusionOutlineParameterSelectWidget.super.prototype.onMouseDown.call( this, e );
};

/**
 * @inheritDoc OO.ui.SelectWidget
 * @param {KeyboardEvent} e
 * @fires choose
 * @fires templateParameterSelectionChanged
 */
ve.ui.MWTransclusionOutlineParameterSelectWidget.prototype.onDocumentKeyDown = function ( e ) {
	var item;

	switch ( e.keyCode ) {
		case OO.ui.Keys.HOME:
			item = this.items[ 0 ];
			if ( item ) {
				this.highlightItem( item );
			}
			break;
		case OO.ui.Keys.END:
			item = this.items[ this.items.length - 1 ];
			if ( item ) {
				this.highlightItem( item );
			}
			break;
		case OO.ui.Keys.SPACE:
			item = this.findHighlightedItem();
			if ( item ) {
				// Warning, this intentionally doesn't call .chooseItem() because we don't want this
				// to fire a "choose" event!
				if ( item.isSelected() ) {
					this.unselectItem( item );
				} else {
					this.selectItem( item );
				}
				this.emit( 'templateParameterSelectionChanged', item, item.isSelected() );
			}
			e.preventDefault();
			break;
		case OO.ui.Keys.ENTER:
			item = this.findHighlightedItem();
			// Same as clicking with the mouse, see above.
			if ( item && item.isSelected() ) {
				this.emit( 'choose', item, item.isSelected() );
				e.preventDefault();

				// Don't call the parent, i.e. can't use enter to unselect the item
				return false;
			}
			break;
	}

	ve.ui.MWTransclusionOutlineParameterSelectWidget.super.prototype.onDocumentKeyDown.call( this, e );
};
