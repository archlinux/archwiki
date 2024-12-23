const
	CommentController = require( './CommentController.js' ),
	HeadingItem = require( './HeadingItem.js' );

/**
 * Handles setup, save and teardown of new topic widget
 *
 * @param {jQuery} $pageContainer Page container
 * @param {HeadingItem} threadItem
 * @param {ThreadItemSet} threadItemSet
 * @param {MemoryStorage} storage Storage object for autosave
 */
function NewTopicController( $pageContainer, threadItem, threadItemSet, storage ) {
	this.container = new OO.ui.PanelLayout( {
		classes: [ 'ext-discussiontools-ui-newTopic' ],
		expanded: false,
		padded: true,
		framed: true
	} );
	this.$notices = $( '<div>' ).addClass( 'ext-discussiontools-ui-newTopic-notices' );

	this.sectionTitle = new OO.ui.TextInputWidget( {
		// Wrap in a <h2> element to inherit heading font styles
		$element: $( '<h2>' ),
		classes: [ 'ext-discussiontools-ui-newTopic-sectionTitle' ],
		placeholder: mw.msg( 'discussiontools-newtopic-placeholder-title' ),
		spellcheck: true
	} );
	this.sectionTitle.$input.attr( 'aria-label', mw.msg( 'discussiontools-newtopic-placeholder-title' ) );
	this.sectionTitleField = new OO.ui.FieldLayout( this.sectionTitle, {
		classes: [ 'ext-discussiontools-ui-newTopic-sectionTitle-field' ],
		align: 'top'
	} );
	this.prevTitleText = '';

	this.container.$element.append( this.$notices, this.sectionTitleField.$element );

	// HeadingItem representing the heading being added, so that we can pretend we're replying to it
	threadItem.range.startContainer = this.sectionTitleField.$element[ 0 ];
	threadItem.range.startOffset = 0;
	threadItem.range.endContainer = this.sectionTitleField.$element[ 0 ];
	threadItem.range.endOffset = this.sectionTitleField.$element[ 0 ].childNodes.length;

	NewTopicController.super.call( this, $pageContainer, threadItem, threadItemSet, storage );
}

OO.inheritClass( NewTopicController, CommentController );

/* Static properties */

NewTopicController.static.initType = 'section';

NewTopicController.static.suppressedEditNotices = [
	// Ignored because we have a custom warning for non-logged-in users.
	'anoneditwarning',
	// Ignored because it contains mostly instructions for signing comments using tildes.
	// (Does not appear in VE notices right now, but just in case.)
	'talkpagetext',
	// Ignored because the empty state takeover has already explained
	// that this is a new article.
	'newarticletext',
	'newarticletextanon'
];

/* Methods */

/**
 * @inheritdoc
 */
NewTopicController.prototype.setup = function () {
	const rootScrollable = OO.ui.Element.static.getRootScrollableElement( document.body );

	// Insert directly after the page content on already existing pages
	// (.mw-parser-output is missing on non-existent pages)
	const $parserOutput = this.$pageContainer.find( '.mw-parser-output' ).first();
	const $mobileAddTopicWrapper = this.$pageContainer.find( '.ext-discussiontools-init-new-topic' );
	if ( $parserOutput.length ) {
		$parserOutput.after( this.container.$element );
	} else if ( $mobileAddTopicWrapper.length ) {
		$mobileAddTopicWrapper.before( this.container.$element );
	} else {
		this.$pageContainer.append( this.container.$element );
	}

	NewTopicController.super.prototype.setup.apply( this, arguments );

	if ( this.threadItem.preloadtitle ) {
		this.sectionTitle.setValue( this.threadItem.preloadtitle );
	}

	// The section title field is added to the page immediately, we can scroll to the bottom and focus
	// it while the content field is still loading.
	rootScrollable.scrollTop = rootScrollable.scrollHeight;
	this.focus();
};

/**
 * @inheritdoc
 */
NewTopicController.prototype.setupReplyWidget = function ( replyWidget, data ) {
	if ( replyWidget.commentDetails.preloadContent && ( !data || data.value === undefined ) ) {
		if ( replyWidget.commentDetails.preloadContentMode !== replyWidget.getMode() ) {
			// This should never happen
			throw new Error( 'Preload content was loaded for wrong mode' );
		}
		data = Object.assign( {}, data, {
			value: replyWidget.commentDetails.preloadContent
		} );
	}
	NewTopicController.super.prototype.setupReplyWidget.apply( this, arguments );

	this.$notices.empty();
	for ( const noticeName in this.replyWidget.commentDetails.notices ) {
		if ( this.constructor.static.suppressedEditNotices.indexOf( noticeName ) !== -1 ) {
			continue;
		}
		const noticeItem = this.replyWidget.commentDetails.notices[ noticeName ];
		const $noticeElement = $( '<div>' )
			.addClass( 'ext-discussiontools-ui-replyWidget-notice' )
			.html( typeof noticeItem === 'string' ? noticeItem : noticeItem.message );
		this.$notices.append( $noticeElement );
	}
	mw.hook( 'wikipage.content' ).fire( this.$notices );

	const title = this.replyWidget.storage.get( 'title' );
	if ( title && !this.sectionTitle.getValue() ) {
		// Don't overwrite if the user has already typed something in while the widget was loading.
		// TODO This should happen immediately rather than waiting for the reply widget to load,
		// then we wouldn't need this check, but the autosave code is in ReplyWidget.
		this.sectionTitle.setValue( title );
		this.prevTitleText = title;

		if ( this.replyWidget.storage.get( 'summary' ) === null ) {
			const generatedSummary = this.generateSummary( title );
			this.replyWidget.editSummaryInput.setValue( generatedSummary );
		}
	}
	this.replyWidget.storage.set( 'title', this.sectionTitle.getValue() );

	if ( this.replyWidget.modeTabSelect ) {
		// Start with the mode-select widget not-tabbable so focus will go from the title to the body
		this.replyWidget.modeTabSelect.$element.attr( {
			tabindex: '-1'
		} );
	}

	this.sectionTitle.connect( this, { change: 'onSectionTitleChange' } );
	this.replyWidget.connect( this, { bodyFocus: 'onBodyFocus' } );

	replyWidget.connect( this, {
		clear: 'onReplyWidgetClear',
		clearStorage: 'onReplyWidgetClearStorage'
	} );
};

/**
 * @inheritdoc
 */
NewTopicController.prototype.focus = function () {
	this.sectionTitle.focus();
};

/**
 * Restore the widget to its original state
 */
NewTopicController.prototype.onReplyWidgetClear = function () {
	// This is going to get called as part of the teardown chain from replywidget
	this.sectionTitle.setValue( '' );
	this.sectionTitleField.setWarnings( [] );
};

/**
 * Remove any storage that the widget is using
 */
NewTopicController.prototype.onReplyWidgetClearStorage = function () {
	// This is going to get called as part of the teardown chain from replywidget
	if ( this.replyWidget ) {
		this.replyWidget.storage.remove( 'title' );
	}
};

NewTopicController.prototype.storeEditSummary = function () {
	if ( this.replyWidget ) {
		const currentSummary = this.replyWidget.editSummaryInput.getValue();
		const generatedSummary = this.generateSummary( this.sectionTitle.getValue() );
		if ( currentSummary === generatedSummary ) {
			// Do not store generated summaries (T315730)
			this.replyWidget.storage.remove( 'summary' );
			return;
		}
	}

	NewTopicController.super.prototype.storeEditSummary.call( this );
};

/**
 * @inheritdoc
 */
NewTopicController.prototype.onReplyWidgetTeardown = function ( abandoned ) {
	// Parent method
	NewTopicController.super.prototype.onReplyWidgetTeardown.call( this, abandoned );

	this.container.$element.detach();

	if ( mw.config.get( 'wgDiscussionToolsStartNewTopicTool' ) ) {
		const url = new URL( location.href );
		url.searchParams.delete( 'action' );
		url.searchParams.delete( 'veaction' );
		url.searchParams.delete( 'section' );
		url.searchParams.delete( 'dtpreload' );
		url.searchParams.delete( 'editintro' );
		url.searchParams.delete( 'preload' );
		url.searchParams.delete( 'preloadparams[]' );
		url.searchParams.delete( 'preloadtitle' );
		history.replaceState( null, '', url );
		mw.config.set( 'wgDiscussionToolsStartNewTopicTool', false );
	}
};

/**
 * @inheritdoc
 */
NewTopicController.prototype.doIndentReplacements = function ( wikitext ) {
	// No indent replacements when posting new topics
	return wikitext;
};

/**
 * @inheritdoc
 */
NewTopicController.prototype.undoIndentReplacements = function ( wikitext ) {
	// No indent replacements when posting new topics
	return wikitext;
};

/**
 * @inheritdoc
 */
NewTopicController.prototype.getUnsupportedNodeSelectors = function () {
	// No unsupported nodes when posting new topics
	return {};
};

/**
 * @inheritdoc
 */
NewTopicController.prototype.getApiQuery = function ( pageName, checkboxes, extraParams ) {
	let data = NewTopicController.super.prototype.getApiQuery.call( this, pageName, checkboxes, extraParams );

	// Rebuild the tags array and remove the reply tag
	const tags = ( data.dttags || '' ).split( ',' );
	const replyTag = tags.indexOf( 'discussiontools-reply' );
	if ( replyTag !== -1 ) {
		tags.splice( replyTag, 1 );
	}
	// Add the newtopic tag
	tags.push( 'discussiontools-newtopic' );

	data = Object.assign( {}, data, {
		paction: 'addtopic',
		sectiontitle: this.sectionTitle.getValue(),
		dttags: tags.join( ',' )
	} );

	// Allow MediaWiki to generate the summary if it wasn't modified by the user. This avoids
	// inconsistencies in how wiki markup is stripped from section titles when they're used in
	// automatic summaries. (T275178)
	if ( data.summary === this.generateSummary( this.sectionTitle.getValue() ) ) {
		delete data.summary;
	}

	return data;
};

/**
 * @inheritdoc
 */
NewTopicController.prototype.saveFail = function ( code ) {
	if ( code === 'discussiontools-newtopic-missing-title' ) {
		OO.ui.confirm( mw.msg( 'discussiontools-newtopic-missing-title-prompt' ) ).then( ( confirmed ) => {
			if ( confirmed ) {
				this.onReplySubmit( { allownosectiontitle: true } );
			} else {
				this.sectionTitle.focus();
			}
		} );
		return;
	}

	NewTopicController.super.prototype.saveFail.apply( this, arguments );
};

/**
 * Generate a default edit summary based on the section title.
 *
 * @param {string} titleText Section title
 * @return {string}
 */
NewTopicController.prototype.generateSummary = function ( titleText ) {
	return titleText ? mw.msg( 'newsectionsummary', titleText ) : '';
};

/**
 * Handle 'change' events for the section title input.
 *
 * @private
 */
NewTopicController.prototype.onSectionTitleChange = function () {
	const titleText = this.sectionTitle.getValue();
	const prevTitleText = this.prevTitleText;

	if ( prevTitleText !== titleText ) {
		this.replyWidget.storage.set( 'title', titleText );

		const generatedSummary = this.generateSummary( titleText );
		const generatedPrevSummary = this.generateSummary( prevTitleText );

		const currentSummary = this.replyWidget.editSummaryInput.getValue();

		// Fill in edit summary if it was not modified by the user yet
		if ( currentSummary === generatedPrevSummary ) {
			this.replyWidget.editSummaryInput.setValue( generatedSummary );
		}
	}

	this.prevTitleText = titleText;

	this.checkSectionTitleValidity();
};

/**
 * Handle 'focus' events for the description field (regardless of mode).
 *
 * @private
 */
NewTopicController.prototype.onBodyFocus = function () {
	const offsetBefore = this.replyWidget.$element.offset().top;
	const rootScrollable = OO.ui.Element.static.getRootScrollableElement( document.body );
	const scrollBefore = rootScrollable.scrollTop;

	this.checkSectionTitleValidity();

	const offsetChange = this.replyWidget.$element.offset().top - offsetBefore;
	// Ensure the rest of the widget doesn't move when the validation
	// message is triggered by a focus. (T275923)
	// Browsers sometimes also scroll in response to focus events,
	// so use the old scrollTop value for consistent results.
	rootScrollable.scrollTop = scrollBefore + offsetChange;

	if ( this.replyWidget.modeTabSelect ) {
		// Return normal tabbable status to the mode select widget so shift-tab will move focus to it
		// (Similar to how the other toolbar elements only become tabbable once the body has focus)
		this.replyWidget.modeTabSelect.$element.attr( {
			tabindex: '0'
		} );
	}
};

/**
 * Check if the section title is valid, and display a warning message.
 *
 * @private
 */
NewTopicController.prototype.checkSectionTitleValidity = function () {
	if ( !this.sectionTitle.getValue() ) {
		// Show warning about missing title
		this.sectionTitleField.setWarnings( [
			mw.msg( 'discussiontools-newtopic-missing-title' )
		] );
	} else {
		this.sectionTitleField.setWarnings( [] );
	}
};

module.exports = NewTopicController;
