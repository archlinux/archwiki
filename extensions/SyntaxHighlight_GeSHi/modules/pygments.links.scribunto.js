$( () => {

	const classes = {
		singleQuoteString: 's1', doubleQuoteString: 's2'
	};

	function addLink( element, page ) {
		const link = document.createElement( 'a' );
		link.href = mw.util.getUrl( page );
		// put text node from element inside link
		const firstChild = element.firstChild;
		if ( !( firstChild instanceof Text ) ) {
			throw new TypeError( 'Expected Text object' );
		}
		link.appendChild( firstChild );
		element.appendChild( link ); // put link inside syntax-highlighted string
	}

	// List of functions whose parameters should be linked if they meet the given condition
	const parametersToLink = {
		require: ( title ) => title.getNamespaceId() === 828,
		'mw.loadData': ( title ) => title.getNamespaceId() === 828,
		'mw.loadJsonData': () => true
	};

	const stringNodes = Array.from( document.getElementsByClassName( classes.singleQuoteString ) )
		.concat( Array.from( document.getElementsByClassName( classes.doubleQuoteString ) ) );

	stringNodes.forEach( ( node ) => {
		if ( !node.nextElementSibling ||
			!node.nextElementSibling.firstChild ||
			!node.nextElementSibling.firstChild.nodeValue ||
			node.nextElementSibling.firstChild.nodeValue.indexOf( ')' ) !== 0 ) {
			return;
		}
		if ( !node.previousElementSibling || !node.previousElementSibling.firstChild ||
			node.previousElementSibling.firstChild.nodeValue !== '(' ) {
			return;
		}
		Object.keys( parametersToLink ).forEach( ( invocation ) => {
			const parts = invocation.split( '.' );
			let partIdx = parts.length - 1;
			let curNode = node.previousElementSibling && node.previousElementSibling.previousElementSibling;
			while ( partIdx >= 0 ) {
				if ( !curNode || curNode.firstChild.nodeValue !== parts[ partIdx ] ) {
					return;
				}
				if ( partIdx === 0 ) {
					break;
				}
				const prev = curNode.previousElementSibling;
				if ( !prev || prev.firstChild.nodeValue !== '.' ) {
					return;
				}
				curNode = prev.previousElementSibling;
				partIdx--;
			}
			const page = node.firstChild.nodeValue.slice( 1, -1 );
			const condition = parametersToLink[ invocation ];
			const title = mw.Title.newFromText( page );
			if ( title && condition( title ) ) {
				addLink( node, title.toText() );
			}
		} );
	} );

} );
