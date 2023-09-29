var CommentTargetWidget = require( './dt-ve/CommentTargetWidget.js' );

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

OO.inheritClass( ReplyWidgetVisual, require( 'ext.discussionTools.ReplyWidget' ) );

/* Methods */

/**
 * @inheritdoc
 */
ReplyWidgetVisual.prototype.createReplyBodyWidget = function ( config ) {
	return new CommentTargetWidget( this, $.extend( {
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
	var surface = this.replyBodyWidget.target.getSurface();
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
	var widget = this,
		target = this.replyBodyWidget.target;

	data = data || {};

	var htmlOrDoc;
	if ( this.storage.get( this.storagePrefix + '/saveable' ) ) {
		htmlOrDoc = this.storage.get( this.storagePrefix + '/ve-dochtml' );
		target.recovered = true;
	} else {
		htmlOrDoc = data.value;
	}

	htmlOrDoc = htmlOrDoc || ( this.getMode() === 'visual' ? '<p></p>' : '' );

	target.originalHtml = htmlOrDoc instanceof HTMLDocument ? ve.properInnerHtml( htmlOrDoc.body ) : htmlOrDoc;
	target.fromEditedState = !!data.value;

	this.replyBodyWidget.setDocument( htmlOrDoc );

	target.once( 'surfaceReady', function () {
		target.getSurface().getView().connect( widget, {
			focus: [ 'emit', 'bodyFocus' ]
		} );

		var listStorage = ve.init.platform.createConflictableStorage( widget.storage );
		// widget.storage is a MemoryStorage object. Copy over the .data cache so
		// that listStorage reads from/writes to the same in-memory cache.
		listStorage.data = widget.storage.data;

		target.initAutosave( {
			suppressNotifications: suppressNotifications,
			docId: widget.storagePrefix,
			storage: listStorage,
			storageExpiry: 60 * 60 * 24 * 30
		} );
		widget.afterSetup();

		// This needs to bind after surfaceReady so any initial population doesn't trigger it early:
		widget.replyBodyWidget.once( 'change', widget.onFirstChange.bind( widget ) );
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
	var targetWidget = this.replyBodyWidget;
	setTimeout( function () {
		// Check surface still exists after timeout
		if ( targetWidget.getSurface() ) {
			targetWidget.getSurface().getModel().selectLastContentOffset();
			targetWidget.focus();
		}
	} );

	return this;
};

ve.trackSubscribe( 'activity.', function ( topic, data ) {
	mw.track( 'dt.schemaVisualEditorFeatureUse', ve.extendObject( data, {
		feature: topic.split( '.' )[ 1 ]
	} ) );
} );

module.exports = ReplyWidgetVisual;
