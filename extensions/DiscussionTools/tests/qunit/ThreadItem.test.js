const
	CommentItem = require( 'ext.discussionTools.init' ).CommentItem,
	HeadingItem = require( 'ext.discussionTools.init' ).HeadingItem;

QUnit.module( 'mw.dt.ThreadItem', QUnit.newMwEnvironment() );

QUnit.test( '#getAuthorsBelow/#getThreadItemsBelow', ( assert ) => {
	const cases = require( '../cases/authors.json' );

	function newFromJSON( json ) {
		let item;
		if ( json.type === 'heading' ) {
			item = new HeadingItem();
		} else {
			item = new CommentItem();
			item.author = json.author;
			item.displayName = json.displayName;
		}
		item.id = json.id;
		item.replies = json.replies.map( newFromJSON );
		return item;
	}

	cases.forEach( ( caseItem ) => {
		const threadItem = newFromJSON( caseItem.thread ),
			authors = threadItem.getAuthorsBelow();

		assert.deepEqual(
			authors,
			caseItem.expectedAuthorsBelow,
			'getAuthorsBelow'
		);

		assert.deepEqual(
			threadItem.getThreadItemsBelow().map( ( item ) => item.id ),
			caseItem.expectedThreadItemIdsBelow
		);
	} );
} );

// TODO:
// * getHeading (CommentItem+HeadingItem)
// * getLinkableTitle (HeadingItem)
// * newFromJSON (ThreadItem)
