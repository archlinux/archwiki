const
	Parser = require( 'ext.discussionTools.init' ).Parser,
	modifier = require( 'ext.discussionTools.init' ).modifier,
	utils = require( 'ext.discussionTools.init' ).utils,
	debugHighlighter = require( './debughighlighter.js' ),
	parser = new Parser( require( 'ext.discussionTools.init' ).parserData ),
	result = parser.parse(
		document.getElementById( 'mw-content-text' ),
		mw.Title.newFromText( mw.config.get( 'wgRelevantPageName' ) )
	),
	comments = result.getCommentItems(),
	threads = result.getThreads(),
	timestampRegexps = parser.getLocalTimestampRegexps(),
	debug = +( new URL( location.href ).searchParams.get( 'dtdebug' ) ),
	DEBUG_HIGHLIGHT = 1,
	DEBUG_VOTE = 2,
	DEBUG_VOTE_PERMISSIVE = 4;

// eslint-disable-next-line no-bitwise
if ( debug & DEBUG_HIGHLIGHT ) {
	debugHighlighter.markThreads( threads );

	comments.forEach( ( comment ) => {
		comment.signatureRanges.forEach( ( signatureRange ) => {
			const node = signatureRange.endContainer;
			const match = parser.findTimestamp( node, timestampRegexps );
			if ( !match ) {
				return;
			}
			const signature = parser.findSignature( node ).nodes;
			const emptySignature = signature.length === 1 && signature[ 0 ] === node;
			// Note that additional content may follow the timestamp (e.g. in some voting formats), but we
			// don't care about it. The code below doesn't mark that due to now the text nodes are sliced,
			// but we might need to take care to use the matched range of node in other cases.
			debugHighlighter.markTimestamp( parser, node, match );
			if ( !emptySignature ) {
				debugHighlighter.markSignature( signature );
			}
		} );
	} );
}

// eslint-disable-next-line no-bitwise
if ( ( debug & DEBUG_VOTE ) || ( debug & DEBUG_VOTE_PERMISSIVE ) ) {
	threads.forEach( ( thread ) => {
		const firstComment = thread.replies[ 0 ];

		if ( firstComment && firstComment.type === 'comment' ) {
			// eslint-disable-next-line no-bitwise
			if ( !( debug & DEBUG_VOTE_PERMISSIVE ) && firstComment.level <= 1 ) {
				// Not in permissive vote mode, and first reply was not indented
				return;
			}

			const firstVote = firstComment.level === 1 ?
				// In permissive mode, the first vote is the replies to the OP
				firstComment.replies[ 0 ] :
				firstComment;

			if ( !firstVote ) {
				return;
			}

			let lastReply;
			const level = firstVote.level;
			firstVote.parent.replies.forEach( ( reply ) => {
				if ( reply.type === 'comment' && reply.level <= level ) {
					lastReply = reply;
				}
			} );

			const listItem = modifier.addSiblingListItem(
				utils.closestElement( lastReply.range.endContainer, [ 'li', 'dd', 'p' ] )
			);
			if ( listItem && listItem.tagName.toLowerCase() === 'li' ) {
				$( listItem )
					// Hide bullet/number
					.css( 'list-style', 'none' )
					.append(
						'[ ',
						$( '<a>' ).text( 'add comment' ),
						' ]'
					);
			}
		}
	} );
}
