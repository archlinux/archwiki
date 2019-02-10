var urlPieces, baseUrl;

window.Tutorials = {};
/**
 * @class
 * @extends OO.ui.Widget
 *
 * @constructor
 */
Tutorials.Toolbar = function ( config ) {
	config = config || {};
	Tutorials.Toolbar.parent.call( this, config );

	urlPieces = window.location.pathname.split( 'demos/tutorials/' );
	baseUrl = urlPieces[ 0 ];

	this.demosLink = new OO.ui.ButtonWidget( {
		label: 'Demos',
		classes: [ 'tutorials-toolbar-demos' ],
		icon: 'journal',
		href: baseUrl + 'demos/index.html',
		flags: [ 'progressive' ]
	} );

	this.documentationLink = new OO.ui.ButtonWidget( {
		label: 'Docs',
		classes: [ 'tutorials-toolbar-docs' ],
		icon: 'journal',
		href: baseUrl + 'js/',
		flags: [ 'progressive' ]
	} );

	this.tutorialsDropdown = new OO.ui.DropdownWidget( {
		indicator: 'down',
		label: 'Browse Tutorials...',
		menu: {
			items: [
				new OO.ui.MenuOptionWidget( {
					data: 'demos/tutorials/index.html',
					label: 'Tutorials Index',
					icon: 'article'
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'demos/tutorials/collection/basics1/contents.html',
					label: 'Basics: ToDo App - Part 1',
					icon: 'article'
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'demos/tutorials/collection/basics2/contents.html',
					label: 'Basics: ToDo App - Part 2',
					icon: 'article'
				} )
			]
		},
		classes: [ 'tutorials-toolbar-tutorials' ],
		icon: 'book',
		flags: [ 'progressive' ]
	} );

	this.$element
		.addClass( 'tutorials-toolbar' )
		.attr( 'role', 'navigation' )
		.append(
			this.demosLink.$element,
			this.documentationLink.$element,
			this.tutorialsDropdown.$element
		);

	this.tutorialsDropdown.getMenu().on( 'choose', Tutorials.Toolbar.prototype.urlRedirection );
};

OO.inheritClass( Tutorials.Toolbar, OO.ui.Widget );

Tutorials.Toolbar.prototype.urlRedirection = function ( item ) {
	window.location = baseUrl + item.getData();
};
