/*!
 * Parsoid utilities.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

mw.libs.ve = mw.libs.ve || {};

/**
 * Resolve a URL relative to a given base.
 *
 * Copied from ve.resolveUrl
 *
 * @param {string} url URL to resolve
 * @param {HTMLDocument} base Document whose base URL to use
 * @return {string} Resolved URL
 */
mw.libs.ve.resolveUrl = function ( url, base ) {
	var node = base.createElement( 'a' );
	node.setAttribute( 'href', url );
	// If doc.baseURI isn't set, node.href will be an empty string
	// This is crazy, returning the original URL is better
	return node.href || url;
};

/**
 * Decode a URI component into a mediawiki article title
 *
 * N.B. Illegal article titles can result from fairly reasonable input (e.g. "100%25beef");
 * see https://phabricator.wikimedia.org/T137847 .
 *
 * @param {string} s String to decode
 * @param {boolean} [preserveUnderscores=false] Don't convert underscores to spaces
 * @return {string} Decoded string, or original string if decodeURIComponent failed
 */
mw.libs.ve.decodeURIComponentIntoArticleTitle = function ( s, preserveUnderscores ) {
	try {
		s = decodeURIComponent( s );
	} catch ( e ) {
		return s;
	}
	if ( preserveUnderscores ) {
		return s;
	}
	return s.replace( /_/g, ' ' );
};

/**
 * Unwrap Parsoid sections
 *
 * @param {HTMLElement} element Parent element, e.g. document body
 * @param {string} [keepSection] Section to keep
 */
mw.libs.ve.unwrapParsoidSections = function ( element, keepSection ) {
	Array.prototype.forEach.call( element.querySelectorAll( 'section[data-mw-section-id]' ), function ( section ) {
		var parent = section.parentNode,
			sectionId = section.getAttribute( 'data-mw-section-id' );
		// Copy section ID to first child (should be a heading)
		// Pseudo-sections (with negative section IDs) may not have a heading
		if ( sectionId !== null && +sectionId > 0 ) {
			section.firstChild.setAttribute( 'data-mw-section-id', sectionId );
		}
		if ( keepSection !== undefined && sectionId === keepSection ) {
			return;
		}
		while ( section.firstChild ) {
			parent.insertBefore( section.firstChild, section );
		}
		parent.removeChild( section );
	} );
};

/**
 * Strip legacy (non-HTML5) IDs; typically found as section IDs inside
 * headings.
 *
 * @param {HTMLElement} element Parent element, e.g. document body
 */
mw.libs.ve.stripParsoidFallbackIds = function ( element ) {
	Array.prototype.forEach.call( element.querySelectorAll( 'span[typeof="mw:FallbackId"][id]:empty' ), function ( legacySpan ) {
		legacySpan.parentNode.removeChild( legacySpan );
	} );
};

mw.libs.ve.restbaseIdRegExp = /^mw[a-zA-Z0-9\-_]{2,6}$/;

mw.libs.ve.stripRestbaseIds = function ( doc ) {
	var restbaseIdRegExp = mw.libs.ve.restbaseIdRegExp;
	Array.prototype.forEach.call( doc.querySelectorAll( '[id^="mw"]' ), function ( element ) {
		if ( element.id.match( restbaseIdRegExp ) ) {
			element.removeAttribute( 'id' );
		}
	} );
};

/**
 * Re-duplicate deduplicated TemplateStyles, for correct rendering when editing a section or
 * when templates are removed during the edit.
 *
 * @param {HTMLElement} element Parent element, e.g. document body
 */
mw.libs.ve.reduplicateStyles = function ( element ) {
	Array.prototype.forEach.call( element.querySelectorAll( 'link[rel="mw-deduplicated-inline-style"]' ), function ( link ) {
		var href = link.getAttribute( 'href' );
		if ( !href || href.slice( 0, 'mw-data:'.length ) !== 'mw-data:' ) {
			return;
		}
		var key = href.slice( 'mw-data:'.length );
		var style = element.querySelector( 'style[data-mw-deduplicate="' + key + '"]' );
		if ( !style ) {
			return;
		}

		var newStyle = link.ownerDocument.createElement( 'style' );
		newStyle.setAttribute( 'data-mw-deduplicate', key );

		// Copy content from the old `style` node (for rendering)
		for ( var i = 0; i < style.childNodes.length; i++ ) {
			newStyle.appendChild( style.childNodes[ i ].cloneNode( true ) );
		}
		// Copy attributes from the old `link` node (for selser)
		Array.prototype.forEach.call( link.attributes, function ( attr ) {
			if ( attr.name !== 'rel' && attr.name !== 'href' ) {
				newStyle.setAttribute( attr.name, attr.value );
			}
		} );

		link.parentNode.replaceChild( newStyle, link );
	} );

	Array.prototype.forEach.call( element.querySelectorAll( 'style[data-mw-deduplicate]:empty' ), function ( style ) {
		var key = style.getAttribute( 'data-mw-deduplicate' );
		var firstStyle = element.querySelector( 'style[data-mw-deduplicate="' + key + '"]' );
		if ( !firstStyle || firstStyle === style ) {
			return;
		}

		// Copy content from the first matching `style` node (for rendering)
		for ( var i = 0; i < firstStyle.childNodes.length; i++ ) {
			style.appendChild( firstStyle.childNodes[ i ].cloneNode( true ) );
		}
	} );
};

/**
 * De-duplicate TemplateStyles, like Parsoid does.
 *
 * @param {HTMLElement} element Parent element, e.g. document body
 */
mw.libs.ve.deduplicateStyles = function ( element ) {
	/**
	 * Check whether `node` is in a fosterable position. (Nodes in these positions may be moved
	 * elsewhere in the DOM by the HTML5 parsing algorithm, if they don't have the right tag name.)
	 * https://html.spec.whatwg.org/#appropriate-place-for-inserting-a-node
	 *
	 * @private
	 * @param {Node|null} node
	 * @return {boolean}
	 */
	function isFosterablePosition( node ) {
		var fosterablePositions = [ 'table', 'thead', 'tbody', 'tfoot', 'tr' ];
		return node && fosterablePositions.indexOf( node.parentNode.nodeName.toLowerCase() ) !== -1;
	}

	var styleTagKeys = {};

	Array.prototype.forEach.call( element.querySelectorAll( 'style[data-mw-deduplicate]' ), function ( style ) {
		var key = style.getAttribute( 'data-mw-deduplicate' );

		if ( !styleTagKeys[ key ] ) {
			// Not a dupe
			styleTagKeys[ key ] = true;
			return;
		}

		if ( !isFosterablePosition( style ) ) {
			// Dupe - replace with a placeholder <link> reference
			var link = style.ownerDocument.createElement( 'link' );
			link.setAttribute( 'rel', 'mw-deduplicated-inline-style' );
			link.setAttribute( 'href', 'mw-data:' + key );

			// Copy attributes from the old `link` node (for selser)
			Array.prototype.forEach.call( style.attributes, function ( attr ) {
				if ( attr.name !== 'rel' && attr.name !== 'data-mw-deduplicate' ) {
					link.setAttribute( attr.name, attr.value );
				}
			} );

			style.parentNode.replaceChild( link, style );
		} else {
			// Duplicate style tag found in fosterable position.
			// Not deduping it (to avoid corruption when the resulting HTML is parsed: T299767),
			// but emptying out the style tag for consistency with Parsoid.
			// Parsoid says it does this for performance reasons.
			style.innerHTML = '';
		}
	} );
};

/**
 * Fix fragment links which should be relative to the current document
 *
 * This prevents these links from trying to navigate to another page,
 * or open in a new window.
 *
 * Call this after ve.targetLinksToNewWindow, as it removes the target attribute.
 * Call this after LinkCache.styleParsoidElements, as it breaks that method by including the query string.
 *
 * @param {HTMLElement} container Parent element, e.g. document body
 * @param {mw.Title} docTitle Current title, only links to this title will be normalized
 * @param {string} [prefix] Prefix to add to fragment and target ID to avoid collisions
 */
mw.libs.ve.fixFragmentLinks = function ( container, docTitle, prefix ) {
	var docTitleText = docTitle.getPrefixedText();
	prefix = prefix || '';
	Array.prototype.forEach.call( container.querySelectorAll( 'a[href*="#"]' ), function ( el ) {
		var fragment = new mw.Uri( el.href ).fragment,
			targetData = mw.libs.ve.getTargetDataFromHref( el.href, el.ownerDocument );

		if ( targetData.isInternal ) {
			var title = mw.Title.newFromText( targetData.title );
			if ( title && title.getPrefixedText() === docTitleText ) {
				if ( !fragment ) {
					// Special case for empty fragment, even if prefix set
					el.setAttribute( 'href', '#' );
				} else {
					if ( prefix ) {
						var target = container.querySelector( '#' + $.escapeSelector( fragment ) );
						// There may be multiple links to a specific target, so check the target
						// hasn't already been fixed (in which case it would be null)
						if ( target ) {
							target.setAttribute( 'id', prefix + fragment );
							target.setAttribute( 'data-mw-id-fixed', '' );
						}
					}
					el.setAttribute( 'href', '#' + prefix + fragment );
				}
				el.removeAttribute( 'target' );

			}
		}
	} );
	// Remove any section heading anchors which weren't fixed above (T218492)
	Array.prototype.forEach.call( container.querySelectorAll( 'h1, h2, h3, h4, h5, h6' ), function ( el ) {
		if ( el.hasAttribute( 'id' ) && !el.hasAttribute( 'data-mw-id-fixed' ) ) {
			el.removeAttribute( 'id' );
		}
	} );
};

/**
 * Parse URL to get title it points to.
 *
 * @param {string} href
 * @param {HTMLDocument|string} doc Document whose base URL to use, or base URL as a string.
 * @return {Object} Information about the given href
 * @return {string} return.title
 *    The title of the internal link, else the original href if href is external
 * @return {string} return.rawTitle
 *    The title without URL decoding and underscore normalization applied
 * @return {boolean} return.isInternal
 *    True if the href pointed to the local wiki, false if href is external
 */
mw.libs.ve.getTargetDataFromHref = function ( href, doc ) {
	function regexEscape( str ) {
		return str.replace( /([.?*+^$[\]\\(){}|-])/g, '\\$1' );
	}

	var isInternal = null;
	// Protocol relative href
	var relativeHref = href.replace( /^https?:/i, '' );

	// Paths without a host portion are assumed to be internal
	if ( !/^\/\//.test( relativeHref ) ) {
		isInternal = true;
	} else {
		// Check if this matches the server's script path (as used by red links)
		var scriptBase = mw.libs.ve.resolveUrl( mw.config.get( 'wgScript' ), doc ).replace( /^https?:/i, '' );
		if ( relativeHref.indexOf( scriptBase ) === 0 ) {
			var uri = new mw.Uri( relativeHref );
			var queryLength = Object.keys( uri.query ).length;
			if (
				( queryLength === 1 && uri.query.title ) ||
				( queryLength === 3 && uri.query.title && uri.query.action === 'edit' && uri.query.redlink === '1' )
			) {
				href = uri.query.title + ( uri.fragment ? '#' + uri.fragment : '' );
				isInternal = true;
			} else if ( queryLength > 1 ) {
				href = relativeHref;
				isInternal = false;
			}
		}
		if ( isInternal === null ) {
			// Check if this matches the server's article path
			var articleBase = mw.libs.ve.resolveUrl( mw.config.get( 'wgArticlePath' ), doc ).replace( /^https?:/i, '' );
			var articleBaseRegex = new RegExp( regexEscape( articleBase ).replace( regexEscape( '$1' ), '(.*)' ) );
			var matches = relativeHref.match( articleBaseRegex );
			if ( matches && matches[ 1 ].split( '#' )[ 0 ].indexOf( '?' ) === -1 ) {
				// Take the relative path
				href = matches[ 1 ];
				isInternal = true;
			}
		}
	}

	// This href doesn't necessarily come from Parsoid (and it might not have the "./" prefix), but
	// this method will work fine.
	var data = mw.libs.ve.parseParsoidResourceName( href );
	data.isInternal = isInternal;
	return data;
};

/**
 * Expand a string of the form jquery.foo,bar|jquery.ui.baz,quux to
 * an array of module names like [ 'jquery.foo', 'jquery.bar',
 * 'jquery.ui.baz', 'jquery.ui.quux' ]
 *
 * Implementation of ResourceLoaderContext::expandModuleNames
 * TODO: Consider upstreaming this to MW core.
 *
 * @param {string} moduleNames Packed module name list
 * @return {string[]} Array of module names
 */
mw.libs.ve.expandModuleNames = function ( moduleNames ) {
	var modules = [];

	moduleNames.split( '|' ).forEach( function ( group ) {
		if ( group.indexOf( ',' ) === -1 ) {
			// This is not a set of modules in foo.bar,baz notation
			// but a single module
			modules.push( group );
		} else {
			// This is a set of modules in foo.bar,baz notation
			var matches = group.match( /(.*)\.([^.]*)/ );
			if ( !matches ) {
				// Prefixless modules, i.e. without dots
				modules = modules.concat( group.split( ',' ) );
			} else {
				// We have a prefix and a bunch of suffixes
				var prefix = matches[ 1 ];
				var suffixes = matches[ 2 ].split( ',' ); // [ 'bar', 'baz' ]
				suffixes.forEach( function ( suffix ) {
					modules.push( prefix + '.' + suffix );
				} );
			}
		}
	} );
	return modules;
};

/**
 * Split Parsoid resource name into the href prefix and the page title.
 *
 * @param {string} resourceName Resource name, from a `href` or `resource` attribute
 * @return {Object} Object with the following properties:
 * @return {string} return.title Full page title in text form (with namespace, and spaces instead of underscores)
 * @return {string} return.rawTitle The title without URL decoding and underscore normalization applied
 */
mw.libs.ve.parseParsoidResourceName = function ( resourceName ) {
	// Resource names are always prefixed with './' to prevent the MediaWiki namespace from being
	// interpreted as a URL protocol, consider e.g. 'href="./File:Foo.png"'.
	// (We accept input without the prefix, so this can also take plain page titles.)
	var matches = resourceName.match( /^(\.\/|)(.*)$/ );
	return {
		// '%' and '?' are valid in page titles, but normally URI-encoded. This also changes underscores
		// to spaces.
		title: mw.libs.ve.decodeURIComponentIntoArticleTitle( matches[ 2 ] ),
		rawTitle: matches[ 2 ]
	};
};

/**
 * Extract the page title from a Parsoid resource name.
 *
 * @param {string} resourceName Resource name, from a `href` or `resource` attribute
 * @return {string} Full page title in text form (with namespace, and spaces instead of underscores)
 */
mw.libs.ve.normalizeParsoidResourceName = function ( resourceName ) {
	return mw.libs.ve.parseParsoidResourceName( resourceName ).title;
};
