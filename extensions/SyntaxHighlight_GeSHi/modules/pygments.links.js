/**
 * Adapted from https://en.wiktionary.org/wiki/MediaWiki:Gadget-CodeLinks.js
 * Original authors: Kephir, Erutuon
 * License: CC-BY-SA 4.0
 */

$( () => {
	// by John Gruber, from https://daringfireball.net/2010/07/improved_regex_for_matching_urls
	const URLRegExp = /\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()[\]{};:'".,<>?«»“”‘’]))/i;

	const wikilinkRegExp = /\[\[([^|{}[\]\n]+)?(?:\|.*?)?]]/;
	const templateRegExp = /\{\{([^|{}[\]\n#]+)(?=\||}})/;

	function processComment( textNode, node ) {
		let wikilinkMatch, templateMatch, URLMatch;

		if (
			( wikilinkMatch = wikilinkRegExp.exec( textNode.data ) ) ||
			( templateMatch = templateRegExp.exec( textNode.data ) ) ||
			( URLMatch = URLRegExp.exec( textNode.data ) )
		) {
			const link = document.createElement( 'a' );
			let start = ( wikilinkMatch || templateMatch || URLMatch ).index;
			let linkText, title;
			link.classList.add( 'code-link' );

			if ( URLMatch ) {
				let url = URLMatch[ 0 ];

				if ( !/^https?:/i.test( url ) ) {
					url = '//' + url;
				}

				link.href = url;
				linkText = URLMatch[ 0 ]; // Preserve visual link text as is
			} else if ( wikilinkMatch && wikilinkMatch[ 1 ] ) {
				linkText = wikilinkMatch[ 0 ];
				title = mw.Title.newFromText( wikilinkMatch[ 1 ] );
			} else if ( templateMatch ) {
				const pageName = templateMatch[ 1 ];
				start += 2; // opening braces "{{"
				linkText = pageName;
				title = mw.Title.newFromText( pageName, 10 );
			}
			if ( title ) {
				link.href = mw.util.getUrl( title.toText() );
				link.title = title.toText();
			}
			if ( link.href ) {
				const textBeforeLink = textNode.data.slice( 0, Math.max( 0, start ) ),
					textAfterLink = textNode.data.slice( Math.max( 0, start + linkText.length ) );

				textNode.data = textAfterLink;
				link.appendChild( document.createTextNode( linkText ) );
				node.insertBefore( link, textNode );
				const beforeTextNode = node.insertBefore( document.createTextNode( textBeforeLink ), link );
				processComment( beforeTextNode, node );
				processComment( textNode, node );
			}
		}
	}

	const commentClasses = [ 'c', 'c1', 'cm' ];
	Array.from( document.getElementsByClassName( 'mw-highlight' ) ).forEach( ( codeBlock ) => {
		commentClasses.forEach( ( commentClass ) => {
			Array.from( codeBlock.getElementsByClassName( commentClass ) ).forEach( ( node ) => {
				processComment( node.firstChild, node );
			} );
		} );
	} );

} );
