/*!
 * MediaWiki Widgets - CategoryTagItemWidget class.
 *
 * @copyright 2011-2015 MediaWiki Widgets Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */
( function () {

	const hasOwn = Object.prototype.hasOwnProperty;

	/**
	 * @class mw.widgets.PageExistenceCache
	 * @private
	 * @param {mw.Api} [api]
	 */
	function PageExistenceCache( api ) {
		this.api = api || new mw.Api();
		this.processExistenceCheckQueueDebounced = OO.ui.debounce( this.processExistenceCheckQueue );
		this.currentRequest = null;
		this.existenceCache = {};
		this.existenceCheckQueue = {};
	}

	/**
	 * Check for existence of pages in the queue.
	 *
	 * @private
	 */
	PageExistenceCache.prototype.processExistenceCheckQueue = function () {
		if ( this.currentRequest ) {
			// Don't fire off a million requests at the same time
			this.currentRequest.always( () => {
				this.currentRequest = null;
				this.processExistenceCheckQueueDebounced();
			} );
			return;
		}
		const queue = this.existenceCheckQueue;
		this.existenceCheckQueue = {};
		const titles = Object.keys( queue ).filter( ( title ) => {
			if ( hasOwn.call( this.existenceCache, title ) ) {
				queue[ title ].resolve( this.existenceCache[ title ] );
			}
			return !hasOwn.call( this.existenceCache, title );
		} );
		if ( !titles.length ) {
			return;
		}
		this.currentRequest = this.api.get( {
			formatversion: 2,
			action: 'query',
			prop: [ 'info' ],
			titles: titles
		} ).done( ( response ) => {
			const
				normalized = {},
				pages = {};
			( response.query.normalized || [] ).forEach( ( data ) => {
				normalized[ data.fromencoded ? decodeURIComponent( data.from ) : data.from ] = data.to;
			} );
			response.query.pages.forEach( ( page ) => {
				pages[ page.title ] = !page.missing;
			} );
			titles.forEach( ( title ) => {
				let normalizedTitle = title;
				while ( hasOwn.call( normalized, normalizedTitle ) ) {
					normalizedTitle = normalized[ normalizedTitle ];
				}
				this.existenceCache[ title ] = pages[ normalizedTitle ];
				queue[ title ].resolve( this.existenceCache[ title ] );
			} );
		} );
	};

	/**
	 * Register a request to check whether a page exists.
	 *
	 * @private
	 * @param {mw.Title} title
	 * @return {jQuery.Promise} Promise resolved with true if the page exists or false otherwise
	 */
	PageExistenceCache.prototype.checkPageExistence = function ( title ) {
		const key = title.getPrefixedText();
		if ( !hasOwn.call( this.existenceCheckQueue, key ) ) {
			this.existenceCheckQueue[ key ] = $.Deferred();
		}
		this.processExistenceCheckQueueDebounced();
		return this.existenceCheckQueue[ key ].promise();
	};

	/**
	 * @class mw.widgets.ForeignTitle
	 * @private
	 * @extends mw.Title
	 *
	 * @constructor
	 * @param {string} title
	 * @param {number} [namespace]
	 */
	function ForeignTitle( title, namespace ) {
		// We only need to handle categories here... but we don't know the target language.
		// So assume that any namespace-like prefix is the 'Category' namespace...
		title = title.replace( /^(.+?)_*:_*(.*)$/, 'Category:$2' ); // HACK
		ForeignTitle.super.call( this, title, namespace );
	}
	OO.inheritClass( ForeignTitle, mw.Title );
	ForeignTitle.prototype.getNamespacePrefix = function () {
		// We only need to handle categories here...
		return 'Category:'; // HACK
	};

	/**
	 * @classdesc Extends OO.ui.TagItemWidget with the ability to link to the given page,
	 * and to show its existence status (whether it is a redlink).
	 *
	 * @class mw.widgets.CategoryTagItemWidget
	 * @uses mw.Api
	 * @extends OO.ui.TagItemWidget
	 *
	 * @constructor
	 * @description Create an instance of `mw.widgets.CategoryTagItemWidget`.
	 * @param {Object} config Configuration options
	 * @param {mw.Title} config.title Page title to use (required)
	 * @param {string} [config.apiUrl] API URL, if not the current wiki's API
	 */
	mw.widgets.CategoryTagItemWidget = function MWWCategoryTagItemWidget( config ) {
		// Parent constructor
		mw.widgets.CategoryTagItemWidget.super.call( this, Object.assign( {
			data: config.title.getMainText(),
			label: config.title.getMainText()
		}, config ) );

		// Properties
		this.title = config.title;
		this.apiUrl = config.apiUrl || '';
		this.$link = $( '<a>' )
			.text( this.label )
			.attr( 'target', '_blank' )
			.on( 'click', ( e ) => {
				// TagMultiselectWidget really wants to prevent you from clicking the link, don't let it
				e.stopPropagation();
			} );

		// Initialize
		this.setMissing( false );
		this.$label.replaceWith( this.$link );
		this.setLabelElement( this.$link );

		if ( !this.constructor.static.pageExistenceCaches[ this.apiUrl ] ) {
			this.constructor.static.pageExistenceCaches[ this.apiUrl ] =
				new PageExistenceCache( new mw.ForeignApi( this.apiUrl ) );
		}
		this.constructor.static.pageExistenceCaches[ this.apiUrl ]
			.checkPageExistence( new ForeignTitle( this.title.getPrefixedText() ) )
			.done( ( exists ) => {
				this.setMissing( !exists );
			} );
	};

	/* Setup */

	OO.inheritClass( mw.widgets.CategoryTagItemWidget, OO.ui.TagItemWidget );

	/* Static Properties */

	/**
	 * Map of API URLs to PageExistenceCache objects.
	 *
	 * @static
	 * @inheritable
	 * @type {Object}
	 * @name mw.widgets.CategoryTagItemWidget.pageExistenceCaches
	 */
	mw.widgets.CategoryTagItemWidget.static.pageExistenceCaches = {
		'': new PageExistenceCache()
	};

	/* Methods */

	/**
	 * Update label link href and CSS classes to reflect page existence status.
	 *
	 * @private
	 * @param {boolean} missing Whether the page is missing (does not exist)
	 */
	mw.widgets.CategoryTagItemWidget.prototype.setMissing = function ( missing ) {
		const
			title = new ForeignTitle( this.title.getPrefixedText() ), // HACK
			prefix = this.apiUrl.replace( '/w/api.php', '' ); // HACK

		this.missing = missing;

		if ( !missing ) {
			this.$link
				.attr( 'href', prefix + title.getUrl() )
				.attr( 'title', title.getPrefixedText() )
				.removeClass( 'new' );
		} else {
			this.$link
				.attr( 'href', prefix + title.getUrl( { action: 'edit', redlink: 1 } ) )
				.attr( 'title', mw.msg( 'red-link-title', title.getPrefixedText() ) )
				.addClass( 'new' );
		}
	};
}() );
