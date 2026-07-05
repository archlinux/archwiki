QUnit.module( 'mw.editcheck.BaseEditCheck', ve.test.utils.newEditCheckEnvironment() );

QUnit.test( 'getModifiedRanges', ( assert ) => {
	const cases = [
		{
			name: 'No modifications',
			transactions: [],
			expectedRanges: []
		},
		{
			name: 'Simple insertion',
			transactions: [
				[ 'newFromInsertion', 4, [ ...'def' ] ]
			],
			expectedRanges: [ new ve.Range( 4, 7 ) ]
		},
		{
			name: 'Simple removal',
			transactions: [
				[ 'newFromRemoval', new ve.Range( 1, 3 ) ]
			],
			expectedRanges: []
		},
		{
			name: 'Annotation clear',
			transactions: [
				[ 'newFromAnnotation', new ve.Range( 2, 3 ), 'clear', ve.dm.example.bold ]
			],
			expectedRanges: [ new ve.Range( 2, 3 ) ]
		},
		{
			name: 'Annotation set',
			transactions: [
				[ 'newFromAnnotation', new ve.Range( 1, 2 ), 'set', ve.dm.example.bold ]
			],
			expectedRanges: [ new ve.Range( 1, 2 ) ]
		},
		{
			name: 'Impure insertion with onlyPureInsertions',
			transactions: [
				[ 'newFromRemoval', new ve.Range( 1, 2 ) ],
				[ 'newFromInsertion', 1, [ ...'XYZ' ] ]
			],
			onlyPureInsertions: true,
			expectedRanges: []
		},
		{
			name: 'Impure insertion without onlyPureInsertions',
			transactions: [
				[ 'newFromRemoval', new ve.Range( 1, 2 ) ],
				[ 'newFromInsertion', 1, [ ...'XYZ' ] ]
			],
			onlyPureInsertions: false,
			expectedRanges: [ new ve.Range( 1, 4 ) ]
		},
		{
			name: 'Insertion of new paragraph and list, with onlyPureInsertions',
			transactions: [
				[ 'newFromInsertion', 5, [
					{ type: 'paragraph' },
					'1',
					{ type: '/paragraph' },
					{ type: 'list', attributes: { style: 'bullet' } },
					{ type: 'listItem' },
					{ type: 'paragraph' },
					'2',
					{ type: '/paragraph' },
					{ type: '/listItem' },
					{ type: '/list' }
				] ]
			],
			onlyPureInsertions: true,
			expectedRanges: [ new ve.Range( 5, 15 ) ]
		}
	];

	const docData = [
		//  0 - Beginning of paragraph
		{ type: 'paragraph' },
		//  1 - Plain "a"
		'a',
		//  2 - Bold "b"
		[ 'b', [ ve.dm.example.boldHash ] ],
		//  3 - Italic "c"
		[ 'c', [ ve.dm.example.italicHash ] ],
		//  4 - End of paragraph
		{ type: '/paragraph' },
		{ type: 'internalList' }, { type: '/internalList' }
	];

	cases.forEach( ( caseItem ) => {
		const doc = ve.dm.example.createExampleDocumentFromData( caseItem.data || docData );
		caseItem.transactions.forEach( ( txData ) => {
			doc.commit( ve.dm.TransactionBuilder.static[ txData[ 0 ] ]( doc, ...txData.slice( 1 ) ) );
		} );
		const check = new mw.editcheck.BaseEditCheck( {}, caseItem.config, caseItem.suggestions );
		assert.deepEqual(
			check.getModifiedRanges( doc, caseItem.coveredNodesOnly, caseItem.onlyContentRanges, caseItem.onlyPureInsertions ),
			caseItem.expectedRanges,
			caseItem.name
		);
	} );
} );

QUnit.test( 'isRangeInValidSection', ( assert ) => {
	const cases = [
		{
			name: 'Lead section ignored when ignoreLeadSection=true and later heading exists',
			config: { ignoreSections: [], ignoreLeadSection: true },
			headings: [ { level: 2, text: 'History' } ],
			section: 0,
			expectedHierarchy: [],
			expectedValid: false
		},
		{
			name: 'Lead section ignored when ignoreSections contains an empty string and later heading exists',
			config: { ignoreSections: [ '' ] },
			headings: [ { level: 2, text: 'History' } ],
			section: 0,
			expectedHierarchy: [],
			expectedValid: false
		},
		{
			name: 'Lead section ignored when includeSections is present',
			config: { ignoreSections: [], includeSections: [] },
			headings: [ { level: 2, text: 'History' } ],
			section: 0,
			expectedHierarchy: [],
			expectedValid: false
		},
		{
			name: 'Lead section ignored when includeSections is provided',
			config: { ignoreSections: [], includeSections: [ 'foo' ] },
			headings: [ { level: 2, text: 'History' } ],
			section: 0,
			expectedHierarchy: [],
			expectedValid: false
		},
		{
			name: 'Lead section allowed when includeSections contains an empty string',
			config: { includeSections: [ '' ] },
			headings: [ { level: 2, text: 'History' } ],
			section: 0,
			expectedHierarchy: [],
			expectedValid: true
		},
		{
			name: 'includeSections set to true allows all sections',
			config: { ignoreSections: [], includeSections: true },
			headings: [ { level: 2, text: 'History' } ],
			section: 1,
			expectedHierarchy: [ 'History' ],
			expectedValid: true
		},
		{
			name: 'includeSections set to false also allows all sections',
			config: { ignoreSections: [], includeSections: true },
			headings: [ { level: 2, text: 'History' } ],
			section: 1,
			expectedHierarchy: [ 'History' ],
			expectedValid: true
		},
		{
			name: 'Section allowed when no sections ignored and includeSections not provided',
			config: { ignoreSections: [], ignoreLeadSection: true },
			headings: [ { level: 2, text: 'History' } ],
			section: 1,
			expectedHierarchy: [ 'History' ],
			expectedValid: true
		},
		{
			name: 'Section not allowed when no sections ignored and includeSections is empty',
			config: { ignoreSections: [], ignoreLeadSection: true, includeSections: [] },
			headings: [ { level: 2, text: 'History' } ],
			section: 1,
			expectedHierarchy: [ 'History' ],
			expectedValid: false
		},
		{
			name: 'Section allowed when no sections ignored and includeSections includes that section',
			config: { ignoreSections: [], ignoreLeadSection: true, includeSections: [ 'History' ] },
			headings: [ { level: 2, text: 'History' } ],
			section: 1,
			expectedHierarchy: [ 'History' ],
			expectedValid: true
		},
		{
			name: 'Section not allowed when no sections ignored and includeSections does not include that section',
			config: { ignoreSections: [], ignoreLeadSection: true, includeSections: [ 'Unhistory' ] },
			headings: [ { level: 2, text: 'History' } ],
			section: 1,
			expectedHierarchy: [ 'History' ],
			expectedValid: false
		},
		// Stub articles (without any headings) get special treatment
		{
			name: 'Lead section allowed in stub (no headings) when ignoreLeadSection=true',
			config: { ignoreSections: [], ignoreLeadSection: true },
			headings: [],
			section: 0,
			expectedHierarchy: [],
			expectedValid: true
		},
		{
			name: 'Lead section allowed in stub (no headings) when includeSections is false',
			config: { ignoreSections: [], includeSections: false },
			headings: [],
			section: 0,
			expectedHierarchy: [],
			expectedValid: true
		},
		{
			name: 'Lead section not allowed in stub (no headings) when includeSections contains an empty string',
			config: { ignoreSections: [], includeSections: [ '', 'foo' ] },
			headings: [],
			section: 0,
			expectedHierarchy: [],
			expectedValid: false
		},
		{
			name: 'Lead section not allowed in stub (no headings) when includeSections is empty',
			config: { ignoreSections: [], includeSections: [] },
			headings: [],
			section: 0,
			expectedHierarchy: [],
			expectedValid: false
		},
		{
			name: 'Lead section not allowed in stub (no headings) when includeSections set',
			config: { ignoreSections: [], includeSections: [ 'foo' ] },
			headings: [],
			section: 0,
			expectedHierarchy: [],
			expectedValid: false
		},
		// Interactions with the heading hierarchy
		{
			name: 'Parent section (h3 inside h2) checked',
			config: { ignoreSections: [ 'External links' ], ignoreLeadSection: false },
			headings: [ { level: 2, text: 'External links' }, { level: 3, text: 'Archived' } ],
			section: 2,
			expectedHierarchy: [ 'Archived', 'External links' ],
			expectedValid: false
		},
		{
			name: 'Same-level earlier heading not in scope (previous h2)',
			config: { ignoreSections: [ 'Foo' ], ignoreLeadSection: false },
			headings: [ { level: 2, text: 'Foo' }, { level: 2, text: 'Bar' } ],
			section: 2,
			expectedHierarchy: [ 'Bar' ],
			expectedValid: true
		},
		{
			name: 'Chain stops at same-or-higher heading (sibling h2 closes previous h2)',
			config: { ignoreSections: [ 'Parent' ], ignoreLeadSection: false },
			headings: [
				{ level: 2, text: 'Parent' },
				{ level: 3, text: 'Foo' },
				{ level: 2, text: 'Sibling' },
				{ level: 3, text: 'Bar' }
			],
			section: 4,
			expectedHierarchy: [ 'Bar', 'Sibling' ],
			expectedValid: true
		},
		{
			name: 'Allowed lower-level heading overrides ignored higher-level heading',
			config: { ignoreSections: [ 'Parent' ], includeSections: [ 'Grandchild' ] },
			headings: [
				{ level: 2, text: 'Parent' },
				{ level: 3, text: 'Child' },
				{ level: 4, text: 'Grandchild' }
			],
			section: 3,
			expectedHierarchy: [ 'Grandchild', 'Child', 'Parent' ],
			expectedValid: true
		},
		{
			name: 'Ignored lower-level heading overrides allowed higher-level heading',
			config: { ignoreSections: [ 'Grandchild' ], includeSections: [ 'Parent' ] },
			headings: [
				{ level: 2, text: 'Parent' },
				{ level: 3, text: 'Child' },
				{ level: 4, text: 'Grandchild' }
			],
			section: 3,
			expectedHierarchy: [ 'Grandchild', 'Child', 'Parent' ],
			expectedValid: false
		}
	];

	function makeDocumentWithHeadings( headings ) {
		// Build a ve.dm.Document with:
		// - a lead paragraph
		// - for each heading: an mwHeading followed by a paragraph

		const data = [];
		const sectionOffsets = [];

		// Lead paragraph
		data.push( { type: 'paragraph' }, ...'Lead', { type: '/paragraph' } );
		sectionOffsets.push( data.length - 2 );

		headings.forEach( ( heading ) => {
			data.push(
				{ type: 'mwHeading', attributes: { level: heading.level } }, ...heading.text, { type: '/mwHeading' },
				{ type: 'paragraph' }, ...'Paragraph', { type: '/paragraph' }
			);
			sectionOffsets.push( data.length - 2 );
		} );

		const doc = new ve.dm.Document( data );
		return { doc, sectionOffsets };
	}

	cases.forEach( ( caseItem ) => {
		const check = new mw.editcheck.BaseEditCheck( {}, caseItem.config, false );

		const { doc, sectionOffsets } = makeDocumentWithHeadings( caseItem.headings );
		const start = sectionOffsets[ caseItem.section ];
		assert.deepEqual(
			check.getHeadingHierarchyFromOffset( start, doc ).map(
				( heading ) => doc.data.getText( false, heading.getRange() )
			),
			caseItem.expectedHierarchy,
			caseItem.name + ': hierarchy'
		);
		assert.strictEqual(
			check.isRangeInValidSection( new ve.Range( start ), doc ),
			caseItem.expectedValid,
			caseItem.name + ': validity'
		);
	} );
} );

QUnit.test( 'isOffsetQuoted', ( assert ) => {
	const mixedQuotedText = [
		//  1 - unquoted text
		...'abc',
		//  4 - quote
		'"',
		//  5 - quoted text
		...'def',
		//  8 - quote
		'"',
		//  9 - unquoted text
		...'ghi'
		// 12
	];
	const quoteData = [
		//  0 - Beginning of heading
		{ type: 'mwHeading', attributes: { level: 1 } },
		//  1-11 - mixed quoted text
		...mixedQuotedText,
		//  12 - End of heading
		{ type: '/mwHeading' },
		//  13 - Beginning of paragraph
		{ type: 'paragraph' },
		//  14-24 - mixed quoted text
		...mixedQuotedText,
		//  25 - End of paragraph
		{ type: '/paragraph' },
		//  26 - Beginning of blockquote
		{ type: 'blockquote' },
		//  27 - Beginning of blockquote's internal paragraph
		{ type: 'paragraph' },
		//  28-38 - mixed quoted text
		...mixedQuotedText,
		//  38 - End of blockquote's internal paragraph
		{ type: '/paragraph' },
		//  39 - End of blockquote
		{ type: '/blockquote' }
	];
	const cases = [
		{
			name: 'No quoted text present',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ...'abc', { type: '/paragraph' } ],
			offset: 2,
			expectedQuoted: false,
			expectedValid: true
		},
		{
			name: 'Inside quoted text',
			config: { ignoreQuotedContent: true },
			offset: 5,
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: 'Just before quoted text',
			config: { ignoreQuotedContent: true },
			offset: 4,
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: 'Outside quoted text',
			config: { ignoreQuotedContent: true },
			offset: 3,
			expectedQuoted: false,
			expectedValid: true
		},
		{
			name: 'Inside quoted text, without quotes ignored',
			config: { ignoreQuotedContent: false },
			offset: 5,
			expectedQuoted: true,
			expectedValid: true
		},
		{
			name: 'Outside quoted text, without quotes ignored',
			config: { ignoreQuotedContent: false },
			offset: 2,
			expectedQuoted: false,
			expectedValid: true
		},
		{
			name: 'Referencing specifically the opening quote character',
			config: { ignoreQuotedContent: true },
			offset: 4,
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: 'Referencing specifically the closing quote character',
			config: { ignoreQuotedContent: true },
			offset: 8,
			expectedQuoted: false,
			expectedValid: true
		},
		{
			name: 'Blockquote',
			config: { ignoreQuotedContent: true },
			offset: 29,
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: '"Smart" quotes',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ...'“abc”def“ghi”', { type: '/paragraph' } ],
			offset: 2,
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: '"Smart" quotes, outside quotes',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ...'“abc”def“ghi”', { type: '/paragraph' } ],
			offset: 7,
			expectedQuoted: false,
			expectedValid: true
		},
		{
			name: 'Chinese quotes',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ...'「abc」def「ghi」', { type: '/paragraph' } ],
			offset: 2,
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: 'Chinese quotes, outside quotes',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ...'「abc」def「ghi」', { type: '/paragraph' } ],
			offset: 7,
			expectedQuoted: false,
			expectedValid: true
		},
		{
			name: 'Apostrophes are not quotes',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ..."Don't 'be' quoted", { type: '/paragraph' } ],
			offset: 5, // 't'
			expectedQuoted: false,
			expectedValid: true
		},
		{
			name: 'Apostrophes are not quotes, but single-quotes are',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ..."Don't 'be' quoted", { type: '/paragraph' } ],
			offset: 8, // 'b'
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: 'Apostrophes are not quotes, but single-quotes are, even close to the start',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ..."G'kar", { type: '/paragraph' } ],
			offset: 3, // 'k'
			expectedQuoted: false,
			expectedValid: true
		},
		{
			name: 'Apostrophes are not quotes, but single-quotes are, even close to the start with whitespace',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ..." 'a", { type: '/paragraph' } ],
			offset: 3, // 'a'
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: 'A single-quote at the beginning is not an apostrophe',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ..."'abc'", { type: '/paragraph' } ],
			offset: 2,
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: 'A possessive apostrophe after an s is not an opening single-quote',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ..."the bees' knees", { type: '/paragraph' } ],
			offset: 12,
			expectedQuoted: false,
			expectedValid: true
		},
		{
			name: 'A possessive apostrophe after an s is, unfortunately, a closing single-quote',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ..."'the bees' knees'", { type: '/paragraph' } ],
			offset: 12,
			expectedQuoted: false,
			expectedValid: true
		},
		{
			name: 'Nested quotes of different types work',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ..."A \"B 'c' D\" E", { type: '/paragraph' } ],
			// the 'c' that has two quotation marks before it but is still in a quote:
			offset: 6,
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: 'Annotated text, inside quotes',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ...ve.dm.example.annotateText( 'foo "bar" baz', ve.dm.example.bold ), { type: '/paragraph' } ],
			offset: 6, // 'b' in "bar"
			expectedQuoted: true,
			expectedValid: false
		},
		{
			name: 'Annotated text, outside quotes',
			config: { ignoreQuotedContent: true },
			data: [ { type: 'paragraph' }, ...ve.dm.example.annotateText( 'foo "bar" baz', ve.dm.example.bold ), { type: '/paragraph' } ],
			offset: 1, // 'f' in "foo"
			expectedQuoted: false,
			expectedValid: true
		}
	];

	cases.forEach( ( caseItem ) => {
		const check = new mw.editcheck.BaseEditCheck( {}, caseItem.config, false );

		const doc = ve.dm.example.createExampleDocumentFromData( caseItem.data || quoteData );
		assert.strictEqual(
			check.isOffsetQuoted( caseItem.offset, doc ),
			caseItem.expectedQuoted,
			caseItem.name + ': quoted'
		);
		assert.strictEqual(
			check.isRangeValid( new ve.Range( caseItem.offset ), doc ),
			caseItem.expectedValid,
			caseItem.name + ': validity'
		);
	} );
} );

QUnit.test( 'doesConfigMatch respects inCategory and notInCategory', ( assert ) => {
	const cases = [
		{
			categories: [ 'Foo' ],
			inCategory: [ 'Foo' ],
			notInCategory: [],
			matches: true,
			description: 'matches when page is in required category'
		},
		{
			categories: [ 'Foo' ],
			inCategory: [ 'foo' ],
			notInCategory: [],
			matches: true,
			description: 'matches when inCategory is normalized'
		},
		{
			categories: [ 'Foo' ],
			inCategory: [],
			notInCategory: [ 'Foo' ],
			matches: false,
			description: 'does not match when page is in a forbidden category'
		},
		{
			categories: [ 'Bar' ],
			inCategory: [ 'Foo' ],
			notInCategory: [],
			matches: false,
			description: 'does not match when page is in none of the required categories'
		},
		{
			categories: [ 'Bar' ],
			inCategory: [],
			notInCategory: [ 'Foo' ],
			matches: true,
			description: 'matches when page is not in any forbidden categories'
		},
		{
			categories: [ 'Foo' ],
			inCategory: [ 'Foo', 'Bar' ],
			notInCategory: [],
			matches: true,
			description: 'matches when page is in any one of the required categories'
		},
		{
			categories: [ 'Foo' ],
			inCategory: [ 'Foo' ],
			notInCategory: [ 'Baz' ],
			matches: true,
			description: 'matches when required category present and forbidden absent'
		},
		{
			categories: [ 'Bar' ],
			inCategory: [ 'Foo' ],
			notInCategory: [ 'bar' ],
			matches: false,
			description: 'does not match when forbidden category present (with title normalization)'
		}
	];

	const doc = new ve.dm.Document( [ { type: 'paragraph' }, { type: '/paragraph' } ] );
	const originalCategories = mw.config.values.wgCategories;

	cases.forEach( ( caseItem ) => {
		mw.config.values.wgCategories = caseItem.categories;
		assert.strictEqual(
			mw.editcheck.BaseEditCheck.static.doesConfigMatch(
				ve.extendObject( {}, mw.editcheck.BaseEditCheck.static.defaultConfig, {
					inCategory: caseItem.inCategory,
					notInCategory: caseItem.notInCategory
				} ),
				doc
			),
			caseItem.matches,
			caseItem.description
		);
	} );

	mw.config.values.wgCategories = originalCategories;
} );
