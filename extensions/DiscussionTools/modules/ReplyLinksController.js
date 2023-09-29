var
	// LanguageData::getLocalData()
	parserData = require( './parser/data.json' ),
	utils = require( './utils.js' );
var featuresEnabled = mw.config.get( 'wgDiscussionToolsFeaturesEnabled' ) || {};

function ReplyLinksController( $pageContainer ) {
	var controller = this;

	// Mixin constructors
	OO.EventEmitter.call( this );

	this.$pageContainer = $pageContainer;
	this.$body = $( document.body );
	this.onReplyLinkClickHandler = this.onReplyLinkClick.bind( this );
	this.onReplyButtonClickHandler = this.onReplyButtonClick.bind( this );
	this.onAddSectionLinkClickHandler = this.onAddSectionLinkClick.bind( this );
	this.onAnyLinkClickHandler = this.onAnyLinkClick.bind( this );

	// Reply links
	this.$replyLinkSets = $pageContainer.find( '.ext-discussiontools-init-replylink-buttons[data-mw-thread-id]:not(:empty)' );

	this.$replyLinkSets.each( function () {
		var replyButton = OO.ui.infuse( $( this ).find( '.ext-discussiontools-init-replybutton' ) );
		var $replyLink = $( this ).find( '.ext-discussiontools-init-replylink-reply' );
		$replyLink.on( 'click keypress', controller.onReplyLinkClickHandler );
		replyButton.on( 'click', controller.onReplyButtonClickHandler, [ replyButton ] );
	} );

	this.$replyLinkSets.on( 'focusin mouseover touchstart', function () {
		controller.emit( 'link-interact' );
	} );

	// "Add topic" link in the skin interface
	if ( featuresEnabled.newtopictool ) {
		// eslint-disable-next-line no-jquery/no-global-selector
		var $addSectionTab = $( '#ca-addsection' );
		if ( $addSectionTab.length ) {
			this.$addSectionLink = $addSectionTab.find( 'a' );
			this.$addSectionLink.on( 'click keypress', this.onAddSectionLinkClickHandler );

			this.$addSectionLink.on( 'focusin mouseover touchstart', function () {
				controller.emit( 'link-interact' );
			} );
		}
		// Handle events on all links that potentially open the new section interface,
		// including links in the page content (from templates) or from gadgets.
		this.$body.on( 'click keypress', 'a', this.onAnyLinkClickHandler );
	}
}

OO.initClass( ReplyLinksController );
OO.mixinClass( ReplyLinksController, OO.EventEmitter );

/**
 * @event link-click
 * @param {string} id
 * @param {jQuery} $linkSet
 * @param {jQuery} $link
 * @param {Object} [data]
 */

/* Methods */

ReplyLinksController.prototype.onReplyLinkClick = function ( e ) {
	if ( !this.isActivationEvent( e ) ) {
		return;
	}
	e.preventDefault();

	// Browser plugins (such as Google Translate) may add extra tags inside
	// the link, so find the containing link tag with the data we need.
	var $linkSet = $( e.target ).closest( '[data-mw-thread-id]' );
	if ( !$linkSet.length ) {
		return;
	}
	this.emit( 'link-click', $linkSet.data( 'mw-thread-id' ), $linkSet );
};

ReplyLinksController.prototype.onReplyButtonClick = function ( button ) {
	var $linkSet = button.$element.closest( '[data-mw-thread-id]' );
	this.emit( 'link-click', $linkSet.data( 'mw-thread-id' ), $linkSet );
};

ReplyLinksController.prototype.onAddSectionLinkClick = function ( e ) {
	if ( !this.isActivationEvent( e ) ) {
		return;
	}
	// Disable VisualEditor's new section editor (in wikitext mode / NWE), to allow our own.
	// We do this on first click, because we don't control the order in which our code and NWE code
	// runs, so its event handlers may not be registered yet.
	$( e.target ).closest( '#ca-addsection' ).off( '.ve-target' );

	// onAnyLinkClick() will also handle clicks on this element, so we don't emit() here to avoid
	// doing it twice.
};

ReplyLinksController.prototype.onAnyLinkClick = function ( e ) {
	if ( $( e.currentTarget ).closest( '[data-mw-thread-id]' ).length ) {
		// Handled elsewhere
		return;
	}

	// Check query parameters to see if this is really a new topic link
	var href = e.currentTarget.href;
	if ( !href ) {
		return;
	}

	var data = this.parseNewTopicLink( href );
	if ( !data ) {
		return;
	}

	if ( !this.isActivationEvent( e ) ) {
		return;
	}
	e.preventDefault();

	this.emit( 'link-click', utils.NEW_TOPIC_COMMENT_ID, $( e.currentTarget ), data );
};

/**
 * Check if the given URL is a new topic link, and if so, return parsed parameters.
 *
 * @param {string} href
 * @return {Object|null} `null` if not a new topic link, parameters otherwise
 */
ReplyLinksController.prototype.parseNewTopicLink = function ( href ) {
	var url = new URL( href );

	var title = mw.Title.newFromText( utils.getTitleFromUrl( href ) || '' );
	if ( !title ) {
		return null;
	}

	// Recognize links to add a new topic:
	if (
		// Special:NewSection/...
		title.getNamespaceId() === mw.config.get( 'wgNamespaceIds' ).special &&
		title.getMainText().split( '/' )[ 0 ] === parserData.specialNewSectionName
	) {
		// Get the real title from the subpage parameter
		var param = title.getMainText().slice( parserData.specialNewSectionName.length + 1 );
		title = mw.Title.newFromText( param );
		if ( !title ) {
			return null;
		}

	} else if (
		// ?title=...&action=edit&section=new
		// ?title=...&veaction=editsource&section=new
		( url.searchParams.get( 'action' ) === 'edit' || url.searchParams.get( 'veaction' ) === 'editsource' ) &&
		url.searchParams.get( 'section' ) === 'new' &&
		url.searchParams.get( 'dtenable' ) !== '0'
	) {
		// Do nothing

	} else {
		// Not a link to add a new topic
		return null;
	}

	if ( title.getPrefixedDb() !== mw.config.get( 'wgRelevantPageName' ) ) {
		// Link to add a section on another page, not supported yet (T282205)
		return null;
	}

	var data = {};
	if ( url.searchParams.get( 'editintro' ) ) {
		data.editintro = url.searchParams.get( 'editintro' );
	}
	if ( url.searchParams.get( 'preload' ) ) {
		data.preload = url.searchParams.get( 'preload' );
	}
	if ( url.searchParams.getAll( 'preloadparams[]' ).length ) {
		data.preloadparams = url.searchParams.getAll( 'preloadparams[]' );
	}
	if ( url.searchParams.get( 'preloadtitle' ) ) {
		data.preloadtitle = url.searchParams.get( 'preloadtitle' );
	}

	// Handle new topic with preloaded text only when requested (T269310)
	if ( !url.searchParams.get( 'dtpreload' ) && !$.isEmptyObject( data ) ) {
		return null;
	}

	return data;
};

ReplyLinksController.prototype.isActivationEvent = function ( e ) {
	if ( mw.config.get( 'wgAction' ) !== 'view' ) {
		// Don't do anything when we're editing/previewing
		return false;
	}
	if ( e.type === 'keypress' && e.which !== OO.ui.Keys.ENTER && e.which !== OO.ui.Keys.SPACE ) {
		// Only handle keypresses on the "Enter" or "Space" keys
		return false;
	}
	if ( e.type === 'click' && !utils.isUnmodifiedLeftClick( e ) ) {
		// Only handle unmodified left clicks
		return false;
	}
	return true;
};

ReplyLinksController.prototype.focusLink = function ( $linkSet ) {
	if ( $linkSet.is( this.$replyLinkSets ) ) {
		// Focus whichever is visible, the link or the button
		OO.ui.infuse( $linkSet.find( '.ext-discussiontools-init-replybutton' ) ).focus();
		$linkSet.find( '.ext-discussiontools-init-replylink-reply' ).trigger( 'focus' );
	}
};

ReplyLinksController.prototype.setActiveLink = function ( $linkSet ) {
	this.$activeLink = $linkSet;

	var isNewTopic = false;
	var activeButton;
	if ( this.$activeLink.is( this.$replyLinkSets ) ) {
		this.$activeLink.addClass( 'ext-discussiontools-init-replylink-active' );
		activeButton = OO.ui.infuse( this.$activeLink.find( '.ext-discussiontools-init-replybutton' ) );
	} else if ( this.$addSectionLink && this.$activeLink.is( this.$addSectionLink ) ) {
		isNewTopic = true;
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#ca-addsection' ).addClass( 'selected' );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#ca-addsection-sticky-header' ).addClass( 'ext-discussiontools-fake-disabled' );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#ca-view' ).removeClass( 'selected' );
	}

	this.originalDocumentTitle = document.title;
	var title = mw.Title.newFromText( mw.config.get( 'wgRelevantPageName' ) );
	document.title = mw.msg( 'pagetitle',
		mw.msg(
			isNewTopic ?
				'discussiontools-pagetitle-newtopic' :
				'discussiontools-pagetitle-reply',
			title.getPrefixedText()
		)
	);

	$( document.body ).addClass( 'ext-discussiontools-init-replylink-open' );
	this.$replyLinkSets.each( function () {
		var replyButton = OO.ui.infuse( $( this ).find( '.ext-discussiontools-init-replybutton' ) );
		var $replyLink = $( this ).find( '.ext-discussiontools-init-replylink-reply' );
		$replyLink.attr( 'tabindex', -1 );
		if ( replyButton === activeButton ) {
			replyButton.setFlags( { progressive: false } );
		} else {
			replyButton.setDisabled( true );
		}
	} );

	// Suppress page takeover behavior for VE editing so that our unload
	// handler can warn of data loss.
	// eslint-disable-next-line no-jquery/no-global-selector
	$( '#ca-edit, #ca-ve-edit, .mw-editsection a, #ca-addsection' ).off( '.ve-target' );
};

ReplyLinksController.prototype.clearActiveLink = function () {
	var activeButton;
	if ( this.$activeLink.is( this.$replyLinkSets ) ) {
		this.$activeLink.removeClass( 'ext-discussiontools-init-replylink-active' );
		try {
			activeButton = OO.ui.infuse( this.$activeLink.find( '.ext-discussiontools-init-replybutton' ) );
		} catch ( err ) {
			// $.data() might have already been cleared by jQuery if the elements were removed, ignore
			// TODO: We should keep references to the OO.ui.ButtonWidget objects instead of infusing again,
			// which would avoid this issue too
		}
	} else if ( this.$addSectionLink && this.$activeLink.is( this.$addSectionLink ) ) {
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#ca-addsection' ).removeClass( 'selected' );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#ca-addsection-sticky-header' ).removeClass( 'ext-discussiontools-fake-disabled' );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#ca-view' ).addClass( 'selected' );
	}

	if ( this.originalDocumentTitle ) {
		document.title = this.originalDocumentTitle;
	}

	$( document.body ).removeClass( 'ext-discussiontools-init-replylink-open' );
	this.$replyLinkSets.each( function () {
		var $replyLink = $( this ).find( '.ext-discussiontools-init-replylink-reply' );
		$replyLink.attr( 'tabindex', 0 );
		var replyButton;
		try {
			replyButton = OO.ui.infuse( $( this ).find( '.ext-discussiontools-init-replybutton' ) );
		} catch ( err ) {
			// $.data() might have already been cleared by jQuery if the elements were removed, ignore
			// TODO: We should keep references to the OO.ui.ButtonWidget objects instead of infusing again,
			// which would avoid this issue too
			return;
		}
		if ( replyButton === activeButton ) {
			replyButton.setFlags( { progressive: true } );
		} else {
			replyButton.setDisabled( false );
		}
	} );

	// We deliberately mangled edit links earlier so VE can't steal our page;
	// have it redo setup to fix those.
	if ( mw.libs.ve && mw.libs.ve.setupEditLinks ) {
		mw.libs.ve.setupEditLinks();
	}

	this.$activeLink = null;
};

ReplyLinksController.prototype.teardown = function () {
	var controller = this;

	if ( this.$activeLink ) {
		this.clearActiveLink();
	}

	this.$replyLinkSets.each( function () {
		try {
			var replyButton = OO.ui.infuse( $( this ).find( '.ext-discussiontools-init-replybutton' ) );
			replyButton.off( 'click', controller.onReplyButtonClickHandler );
		} catch ( err ) {
			// $.data() might have already been cleared by jQuery if the elements were removed, ignore
			// TODO: We should keep references to the OO.ui.ButtonWidget objects instead of infusing again,
			// which would avoid this issue too
		}
		var $replyLink = $( this ).find( '.ext-discussiontools-init-replylink-reply' );
		$replyLink.off( 'click keypress', controller.onReplyLinkClickHandler );
	} );

	if ( featuresEnabled.newtopictool ) {
		if ( this.$addSectionLink ) {
			this.$addSectionLink.off( 'click keypress', this.onAddSectionLinkClickHandler );
		}
		this.$body.off( 'click keypress', 'a', this.onAnyLinkClickHandler );
	}
};

module.exports = ReplyLinksController;
