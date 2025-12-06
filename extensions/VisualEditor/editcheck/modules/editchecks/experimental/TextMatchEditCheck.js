mw.editcheck.TextMatchEditCheck = function MWTextMatchEditCheck() {
	// Parent constructor
	mw.editcheck.TextMatchEditCheck.super.apply( this, arguments );

	this.lang = mw.config.get( 'wgContentLanguage' );
	this.sensitivity = 'accent'; // TODO figure out how to determine this on an editcheck level
	this.collator = new Intl.Collator( this.lang, { sensitivity: this.sensitivity } );

	const rawMatchItems = [
		...this.constructor.static.matchItems,
		...( this.config.matchItems || [] )
	];

	// Create matchItem instances
	this.matchItems = rawMatchItems.map(
		( matchItem, index ) => new mw.editcheck.TextMatchItem( matchItem, index, this.collator )
	);

	// Initialize lookup maps
	this.matchItemsSensitiveByTerm = {};
	this.matchItemsInsensitiveByTerm = {};

	this.matchItems.forEach( ( matchItem ) => {
		const targetMap = matchItem.isCaseSensitive() ?
			this.matchItemsSensitiveByTerm :
			this.matchItemsInsensitiveByTerm;

		Object.keys( matchItem.query ).forEach( ( key ) => {
			if ( !targetMap[ key ] ) {
				targetMap[ key ] = [];
			}
			targetMap[ key ].push( matchItem );
		} );
	} );

};

OO.inheritClass( mw.editcheck.TextMatchEditCheck, mw.editcheck.BaseEditCheck );

mw.editcheck.TextMatchEditCheck.static.name = 'textMatch';

/**
 * The configs of TextMatchEditCheck take priority over individual matchItem configs.
 * So we make TextMatch’s defaults nonrestrictive,
 * and let the finer limitations be handled by individual matchItems.
 */
mw.editcheck.TextMatchEditCheck.static.defaultConfig = ve.extendObject( {}, mw.editcheck.BaseEditCheck.static.defaultConfig, {
	maximumEditcount: null
} );

mw.editcheck.TextMatchEditCheck.static.choices = [
	{
		action: 'dismiss',
		label: ve.msg( 'ooui-dialog-process-dismiss' ),
		modes: [ '', 'info', 'replace', 'delete' ]
	},
	{
		action: 'accept',
		label: ve.msg( 'ooui-dialog-message-accept' ),
		modes: [ 'replace' ]
	},
	{
		action: 'delete',
		label: ve.msg( 'visualeditor-contextitemwidget-label-remove' ),
		modes: [ 'delete' ]
	}
];

mw.editcheck.TextMatchEditCheck.static.matchItems = [];

/**
 * Given a term, find all the equivalent keys that exist in case-insensitive matchItem queries
 *
 * @param {string} term Term to find keys for
 * @return {string} Array of keys that match
 */
mw.editcheck.TextMatchEditCheck.prototype.getMatchingKeys = function ( term ) {
	const matches = Object.keys( this.matchItemsInsensitiveByTerm ).filter(
		( key ) => this.collator.compare( key, term ) === 0
	);
	return matches;
};

mw.editcheck.TextMatchEditCheck.prototype.handleListener = function ( surfaceModel, listener ) {
	const actions = [];
	const document = surfaceModel.getDocument();
	const modified = this.getModifiedContentRanges( document );

	const matchConfigs = [
		{
			caseSensitive: true,
			terms: Object.keys( this.matchItemsSensitiveByTerm ),
			lookup: ( term ) => this.matchItemsSensitiveByTerm[ term ] || [ ]
		},
		{
			caseSensitive: false,
			terms: Object.keys( this.matchItemsInsensitiveByTerm ),
			lookup: ( term ) => {
				const keys = this.getMatchingKeys( term );
				return keys
					.map( ( key ) => this.matchItemsInsensitiveByTerm[ key ] || [] )
					.reduce( ( acc, arr ) => acc.concat( arr ), [] );
			}
		}
	];

	for ( const { caseSensitive, terms, lookup } of matchConfigs ) {
		const ranges = document.findText(
			new Set( terms ),
			{
				caseSensitiveString: caseSensitive,
				wholeWord: true
			}
		);

		for ( const range of ranges ) {
			if ( !modified.some( ( modRange ) => range.touchesRange( modRange ) ) ) {
				continue;
			}
			if ( !this.isRangeInValidSection( range, surfaceModel.documentModel ) ) {
				continue;
			}
			const term = surfaceModel.getLinearFragment( range ).getText();

			const relevantMatchItems = lookup( term );
			if ( !relevantMatchItems ) {
				continue;
			}
			for ( const matchItem of relevantMatchItems ) {
				const name = this.getTagNameByMatchItem( matchItem, term );
				if ( this.isDismissedRange( range, name ) ) {
					continue;
				}
				if ( matchItem.listener && matchItem.listener !== listener ) {
					continue;
				}
				if ( matchItem.config && !this.constructor.static.doesConfigMatch( matchItem.config ) ) {
					continue;
				}

				let fragment = surfaceModel.getLinearFragment( range );
				fragment = matchItem.getExpandedFragment( fragment );
				const isValidMode = this.constructor.static.choices.some(
					( choice ) => choice.modes.includes( matchItem.mode )
				);
				const mode = isValidMode ? matchItem.mode : '';
				actions.push(
					new mw.editcheck.TextMatchEditCheckAction( {
						fragments: [ fragment ],
						title: matchItem.title,
						message: matchItem.message,
						check: this,
						mode: mode,
						matchItem: matchItem
					} )
				);
			}
		}
	}
	return actions;
};

mw.editcheck.TextMatchEditCheck.prototype.onDocumentChange = function ( surfaceModel ) {
	return this.handleListener( surfaceModel, 'onDocumentChange' );
};

/**
 * Get a unique tag name for a given matchItem-term pair.
 * Builds the tag name from:
 * - the name of this editcheck
 * - and the unique subtag of this matchitem-term pair
 *
 * @param {Object} matchItem
 * @param {string} term
 * @return {string} A tag name in the format 'textMatch-{subtag}'
 */
mw.editcheck.TextMatchEditCheck.prototype.getTagNameByMatchItem = function ( matchItem, term ) {
	return this.constructor.static.name + matchItem.getSubTag( term );
};

// For now it doesn't make sense to run a TextMatchEditCheck in review mode
// as there isn't a way to edit the text.
mw.editcheck.TextMatchEditCheck.prototype.onBeforeSave = null;

mw.editcheck.TextMatchEditCheck.prototype.act = function ( choice, action /* , surface */ ) {
	switch ( choice ) {
		case 'dismiss':
			this.dismiss( action );
			break;
		case 'delete':
			action.fragments[ 0 ].removeContent();
			break;
		case 'accept': {
			const fragment = action.fragments[ 0 ];
			const oldWord = fragment.getText();
			const matchItem = action.matchItem;
			if ( !matchItem ) {
				ve.log( `mw.editcheck.TextMatchEditCheck.prototype.act(): did not find matchItem for ${ oldWord }` );
				return;
			}
			const newWord = matchItem.getReplacement( oldWord );
			// TODO match case of old word
			if ( !newWord ) {
				ve.log( `mw.editcheck.TextMatchEditCheck.prototype.act(): did not find replacement for ${ oldWord }` );
				return;
			}
			fragment.removeContent().insertContent( newWord );
		}

	}
	return ve.createDeferred().resolve( {} );
};

mw.editcheck.editCheckFactory.register( mw.editcheck.TextMatchEditCheck );

/**
 * TextMatchEditCheckAction
 *
 * Subclass of EditCheckAction to include information
 * about the matchItem associated with this action
 *
 * @class
 *
 * @param {Object} config Configuration options
 * @param {Object} config.matchItem the associated matchItem for this action
 */
mw.editcheck.TextMatchEditCheckAction = function MWTextMatchEditCheckAction( config ) {
	mw.editcheck.TextMatchEditCheckAction.super.call( this, config );
	this.matchItem = config.matchItem;
};

/* Inheritance */

OO.inheritClass( mw.editcheck.TextMatchEditCheckAction, mw.editcheck.EditCheckAction );

/* Events */

/**
 * Fired when the user selects an action (e.g., clicks a suggestion button).
 *
 * @event mw.editcheck.EditCheckAction#act
 * @param {jQuery.Promise} promise A promise that resolves when the action is complete
 */

/* Methods */

/**
 * Compare to another action
 *
 * @param {mw.editcheck.EditCheckAction} other Other action
 * @return {boolean}
 */
mw.editcheck.TextMatchEditCheckAction.prototype.equals = function ( other ) {
	if ( !( other instanceof mw.editcheck.TextMatchEditCheckAction ) ||
		this.check.constructor !== other.check.constructor ) {
		return false;
	}
	if ( this.matchItem !== other.matchItem ) {
		return false;
	}
	if ( this.fragments.length !== other.fragments.length ) {
		return false;
	}
	return this.fragments.every( ( fragment ) => {
		const selection = fragment.getSelection();
		return other.fragments.some( ( otherFragment ) => otherFragment.getSelection().equals( selection ) );
	} );
};

/**
 * Get unique tag name for this action
 *
 * @return {string} unique tag
 */
mw.editcheck.TextMatchEditCheckAction.prototype.getTagName = function () {
	if ( !this.matchItem ) {
		return this.check.getName();
	}
	return this.check.getTagNameByMatchItem( this.matchItem, this.originalText[ 0 ] );
};

/**
 * TextMatchItem
 *
 * Class to represent a single matchItem for TextMatchEditCheck
 *
 * @class
 *
 * @param {Object} matchItem
 * @param item
 * @param {number} index of this matchitem in the TextMatchEditCheck's collection of all match items
 * @param {Collator} collator to use for comparisons
 */
mw.editcheck.TextMatchItem = function MWTextMatchItem( item, index, collator ) {
	this.title = item.title;
	this.mode = item.mode || '';
	this.message = item.message;
	this.config = item.config || {};
	this.expand = item.expand;
	this.listener = item.listener || null;

	this.index = index;
	this.collator = collator;

	// Normalize queries to allow support for both objects and arrays
	this.query = this.normalizeQuery( item.query );
};

/* Methods */

/**
 * Transform any query type into a dictionary of terms and their replacements,
 * with a null replacement if none exists
 *
 * @param {Object.<string,string>|string[]} query
 * @return {Object.<string,string>} Dictionary of each term and its replacement
 */
mw.editcheck.TextMatchItem.prototype.normalizeQuery = function ( query ) {
	if ( Array.isArray( query ) ) {
		const normalized = Object.create( null );
		for ( const word of query ) {
			normalized[ word ] = null;
		}
		return normalized;
	}
	return query || Object.create( null );
};

/**
 * @return {boolean} if this matchItem is configured to be case sensitive
 */
mw.editcheck.TextMatchItem.prototype.isCaseSensitive = function () {
	return this.config && this.config.caseSensitive;
};

/**
 * Return the corresponding replacement word,
 * as defined for the given word in this matchItem's query
 *
 * @param {string} term to get replacement for
 * @return {string} replacement term
 */
mw.editcheck.TextMatchItem.prototype.getReplacement = function ( term ) {
	if ( this.isCaseSensitive() ) {
		return this.query[ term ];
	}
	const key = Object.keys( this.query ).find(
		( k ) => this.collator.compare( k, term ) === 0
	);
	return key ? this.query[ key ] : null;
};

/**
 * Expand a fragment given the match item's config
 *
 * @param {ve.dm.SurfaceFragment} fragment
 * @return {ve.dm.SurfaceFragment} Expanded fragment
 */
mw.editcheck.TextMatchItem.prototype.getExpandedFragment = function ( fragment ) {
	switch ( this.expand ) {
		case 'sentence':
			// TODO: implement once unicodejs support is added
			break;
		case 'paragraph':
			fragment = fragment.expandLinearSelection( 'closest', ve.dm.ContentBranchNode )
				// …but that covered the entire CBN, we only want the contents
				.adjustLinearSelection( 1, -1 );
			break;
		case 'word':
		case 'siblings':
		case 'parent':
			fragment = fragment.expandLinearSelection( this.expand );
			break;
	}
	return fragment;
};

/**
 * Get a unique subtag for this matchitem-term pair.
 * Builds the subtag from:
 * - the index of the matchItem when created
 * - and the index of the term in the list of keys from the matchItem's query
 *
 * @param {string} term
 * @return {string} A subtag in the format '-{matchIndex}-{termIndex}'
 */
mw.editcheck.TextMatchItem.prototype.getSubTag = function ( term ) {
	const queries = Object.keys( this.query );
	let termIndex;
	if ( this.caseSensitive ) {
		termIndex = queries.indexOf( term );
	} else {
		termIndex = queries.findIndex( ( q ) => this.collator.compare( q, term ) === 0 );
	}
	if ( this.index === -1 || termIndex === -1 ) {
		return '';
	}
	return `-${ this.index }-${ termIndex }`;
};
