Demo.static.pages.icons = function ( demo ) {
	var i, len, iconSet, iconsFieldset, iconWidget, selector,
		icons = {
			movement: [
				'arrowLast',
				'arrowNext',
				'downTriangle',
				'upTriangle',
				'previous',
				'next',
				'expand',
				'collapse',
				'caretLast',
				'caretNext',
				'caretDown',
				'caretUp',
				'move'
			],
			content: [
				'article',
				'articles',
				'articleCheck',
				'articleSearch',
				'articleRedirect',
				'citeArticle',
				'book',
				'history',
				'info',
				'journal',
				'newspaper',
				'folderPlaceholder',
				'die',
				'download',
				'tag',
				'upload',
				'window'
			],
			alerts: [
				'alert',
				'bell',
				'bellOn',
				'comment',
				'eye',
				'eyeClosed',
				'message',
				'notice',
				'signature',
				'speechBubble',
				'speechBubbleAdd',
				'speechBubbles',
				'tray'
			],
			interactions: [
				'add',
				'advanced',
				'bookmark',
				'browser',
				'cancel',
				'check',
				'clear',
				'clock',
				'close',
				'ellipsis',
				'feedback',
				'funnel',
				'heart',
				'help',
				'key',
				'keyboard',
				'logOut',
				'newWindow',
				'printer',
				'search',
				'settings',
				'subtract',
				'sun',
				'watchlist'
			],
			moderation: [
				'block',
				'unBlock',
				'flag',
				'unFlag',
				'lock',
				'unLock',
				'star',
				'halfStar',
				'unStar',
				'trash',
				'unTrash',
				'ongoingConversation'
			],
			'editing-core': [
				'edit',
				'editLock',
				'editUndo',
				'link',
				'linkExternal',
				'linkSecure',
				'redo',
				'undo'
			],
			'editing-styling': [
				'bigger',
				'smaller',
				'subscript',
				'superscript',
				'bold',
				'highlight',
				'italic',
				'strikethrough',
				'underline',
				'textDirLTR',
				'textDirRTL',
				'textStyle'
			],
			'editing-list': [
				'indent',
				'listBullet',
				'listNumbered',
				'outdent'
			],
			'editing-advanced': [
				'alignCentre',
				'alignLeft',
				'alignRight',
				'attachment',
				'calendar',
				'code',
				'find',
				'language',
				'layout',
				'markup',
				'newline',
				'noWikiText',
				'outline',
				'puzzle',
				'quotes',
				'quotesAdd',
				'searchCaseSensitive',
				'searchDiacritics',
				'searchRegularExpression',
				'specialCharacter',
				'table',
				'tableAddColumnAfter',
				'tableAddColumnBefore',
				'tableAddRowAfter',
				'tableAddRowBefore',
				'tableCaption',
				'tableMergeCells',
				'templateAdd',
				'wikiText'
			],
			media: [
				'fullScreen',
				'image',
				'imageAdd',
				'imageLock',
				'imageGallery',
				'play',
				'stop'
			],
			location: [
				'map',
				'mapPin',
				'mapPinAdd',
				'mapTrail'
			],
			user: [
				'userActive',
				'userAvatar',
				'userInactive',
				'userTalk'
			],
			layout: [
				'menu',
				'stripeFlow',
				'stripeSideMenu',
				'stripeSummary',
				'stripeToC',
				'viewCompact',
				'viewDetails'
			],
			accessibility: [
				'bright',
				'halfBright',
				'notBright',
				'moon',
				'largerText',
				'smallerText',
				'visionSimulator'
			],
			wikimedia: [
				'logoCC',
				'logoWikimediaCommons',
				'logoWikimediaDiscovery',
				'logoWikipedia'
			]
		},
		indicators = [
			'alert',
			'clear',
			'down',
			'next',
			'previous',
			'required',
			'search',
			'up'
		],
		iconsFieldsets = [],
		iconsWidgets = [],
		indicatorsFieldset = new OO.ui.FieldsetLayout( { label: 'Indicators' } );

	for ( i = 0, len = indicators.length; i < len; i++ ) {
		indicatorsFieldset.addItems( [
			new OO.ui.FieldLayout(
				new OO.ui.IndicatorWidget( {
					indicator: indicators[ i ],
					title: indicators[ i ]
				} ),
				{
					align: 'inline',
					label: indicators[ i ]
				}
			)
		] );
	}
	for ( iconSet in icons ) {
		iconsFieldset = new OO.ui.FieldsetLayout( { label: 'Icons – ' + iconSet } );
		iconsFieldsets.push( iconsFieldset );

		for ( i = 0, len = icons[ iconSet ].length; i < len; i++ ) {
			iconWidget = new OO.ui.IconWidget( {
				icon: icons[ iconSet ][ i ],
				title: icons[ iconSet ][ i ]
			} );
			iconsWidgets.push( iconWidget );
			iconsFieldset.addItems( [
				new OO.ui.FieldLayout( iconWidget, {
					label: icons[ iconSet ][ i ],
					align: 'inline'
				} )
			] );
		}
	}

	selector = new OO.ui.ButtonSelectWidget( {
		items: [
			new OO.ui.ButtonOptionWidget( {
				label: 'None',
				flags: [],
				data: {
					progressive: false,
					constructive: false,
					destructive: false
				}
			} ),
			new OO.ui.ButtonOptionWidget( {
				label: 'Progressive',
				flags: [ 'progressive' ],
				data: {
					progressive: true,
					constructive: false,
					destructive: false
				}
			} ),
			new OO.ui.ButtonOptionWidget( {
				label: 'Destructive',
				flags: [ 'destructive' ],
				data: {
					progressive: false,
					constructive: false,
					destructive: true
				}
			} )
		]
	} );

	selector
		.on( 'select', function ( selected ) {
			iconsWidgets.forEach( function ( iconWidget ) {
				iconWidget.setFlags( selected.getData() );
			} );
		} )
		.selectItemByData( {
			progressive: false,
			constructive: false,
			destructive: false
		} );

	demo.$element.append(
		new OO.ui.PanelLayout( {
			expanded: false,
			framed: true
		} ).$element
			.addClass( 'demo-container demo-icons' )
			.append(
				selector.$element,
				indicatorsFieldset.$element,
				iconsFieldsets.map( function ( item ) { return item.$element[ 0 ]; } )
			)
	);
};
