const CommentTargetWidget = require( './dt-ve/CommentTargetWidget.js' );

require( './dt-ve/dt.ui.MWSignatureContextItem.js' );
require( './dt-ve/dt.dm.MWSignatureNode.js' );
require( './dt-ve/dt.ce.MWSignatureNode.js' );
require( './dt-ve/dt.ui.UsernameCompletionAction.js' );
require( './dt-ve/dt.ui.UsernameCompletionTool.js' );
require( './dt-ve/dt.dm.PingNode.js' );
require( './dt-ve/dt.ce.PingNode.js' );

/**
 * DiscussionTools ReplyWidgetVisual class
 *
 * @class mw.dt.ReplyWidgetVisual
 * @extends mw.dt.ReplyWidget
 * @constructor
 * @param {CommentController} commentController
 * @param {CommentDetails} commentDetails
 * @param {Object} [config]
 * @param {string} [config.mode] Default edit mode, 'source' or 'visual'
 */
function ReplyWidgetVisual( commentController, commentDetails, config ) {
	this.defaultMode = config.mode;

	// Parent constructor
	ReplyWidgetVisual.super.apply( this, arguments );

	// TODO: Rename this widget to VE, as it isn't just visual mode
	this.$element.addClass( 'ext-discussiontools-ui-replyWidget-ve' );
}

/* Inheritance */

OO.inheritClass( ReplyWidgetVisual, require( './dt.ui.ReplyWidget.js' ) );

/* Methods */

/**
 * @inheritdoc
 */
ReplyWidgetVisual.prototype.createReplyBodyWidget = function ( config ) {
	return new CommentTargetWidget( this, Object.assign( {
		defaultMode: this.defaultMode
	}, config ) );
};

/**
 * @inheritdoc
 */
ReplyWidgetVisual.prototype.getValue = function () {
	if ( this.getMode() === 'source' ) {
		return this.replyBodyWidget.target.getSurface().getModel().getDom();
	} else {
		return this.replyBodyWidget.target.getSurface().getHtml();
	}
};

/**
 * @inheritdoc
 */
ReplyWidgetVisual.prototype.clear = function ( preserveStorage ) {
	if ( !preserveStorage ) {
		this.replyBodyWidget.target.clearDocState();
	}
	// #clear removes all the surfaces, so must be done after #clearDocState
	this.replyBodyWidget.clear();

	// Parent method
	ReplyWidgetVisual.super.prototype.clear.apply( this, arguments );
};

/**
 * @inheritdoc
 */
ReplyWidgetVisual.prototype.isEmpty = function () {
	const surface = this.replyBodyWidget.target.getSurface();
	return !( surface && surface.getModel().getDocument().data.hasContent() );
};

/**
 * @inheritdoc
 */
ReplyWidgetVisual.prototype.getMode = function () {
	return this.replyBodyWidget.target.getSurface() ?
		this.replyBodyWidget.target.getSurface().getMode() :
		this.defaultMode;
};

/**
 * @inheritdoc
 */
ReplyWidgetVisual.prototype.setup = function ( data, suppressNotifications ) {
	const target = this.replyBodyWidget.target;

	data = data || {};

	let htmlOrDoc;
	if ( this.storage.get( 'saveable' ) ) {
		htmlOrDoc = this.storage.get( 've-dochtml' );
		target.recovered = true;
	} else {
		htmlOrDoc = data.value;
	}

	htmlOrDoc = htmlOrDoc || ( this.getMode() === 'visual' ? '<p></p>' : '' );

	target.originalHtml = htmlOrDoc instanceof HTMLDocument ? ve.properInnerHtml( htmlOrDoc.body ) : htmlOrDoc;
	target.fromEditedState = !!data.value;

	this.replyBodyWidget.setDocument( htmlOrDoc );

	target.once( 'surfaceReady', () => {
		target.getSurface().getView().connect( this, {
			focus: [ 'emit', 'bodyFocus' ]
		} );

		target.initAutosave( {
			suppressNotifications: suppressNotifications,
			storage: this.storage
		} );
		this.afterSetup();

		// This needs to bind after surfaceReady so any initial population doesn't trigger it early:
		this.replyBodyWidget.once( 'change', this.onFirstChange.bind( this ) );
	} );

	// Parent method
	ReplyWidgetVisual.super.prototype.setup.apply( this, arguments );

	// Events
	this.replyBodyWidget.connect( this, {
		change: 'onInputChangeThrottled',
		cancel: 'tryTeardown',
		submit: 'onReplyClick'
	} );

	return this;
};

/**
 * @inheritdoc
 */
ReplyWidgetVisual.prototype.teardown = function () {
	this.replyBodyWidget.disconnect( this );
	this.replyBodyWidget.off( 'change' );

	// Parent method
	return ReplyWidgetVisual.super.prototype.teardown.apply( this, arguments );
};

/**
 * @inheritdoc
 */
ReplyWidgetVisual.prototype.focus = function () {
	const targetWidget = this.replyBodyWidget;
	setTimeout( () => {
		// Check surface still exists after timeout
		if ( targetWidget.getSurface() ) {
			targetWidget.getSurface().getView().selectLastSelectableContentOffset();
			targetWidget.focus();
		}
	} );

	return this;
};

module.exports = ReplyWidgetVisual;
