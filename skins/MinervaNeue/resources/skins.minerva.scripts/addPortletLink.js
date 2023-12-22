/**
 * @param {jQuery.Object} $item The added list item, or null if no element was added.
 * @return {Object} of arrays with mandatory class names for list item elements.
 */
function getClassesForItem( $item ) {
	var $parent = $item.parent(),
		// eslint-disable-next-line no-jquery/no-class-state
		isPageActionList = $parent.hasClass( 'page-actions-menu__list' ),
		// eslint-disable-next-line no-jquery/no-class-state
		isToggleList = $parent.hasClass( 'toggle-list__list' );

	if ( isToggleList ) {
		return {
			li: [ 'toggle-list-item' ],
			span: [ 'toggle-list-item__label' ],
			a: [ 'toggle-list-item__anchor' ]
		};
	} else if ( isPageActionList ) {
		return {
			li: [ 'page-actions-menu__list-item' ],
			span: [],
			a: [
				'cdx-button',
				'cdx-button--size-large',
				'cdx-button--fake-button',
				'cdx-button--fake-button--enabled',
				'cdx-button--icon-only',
				'cdx-button--weight-quiet'
			]
		};
	} else {
		return {
			li: [],
			span: [],
			a: []
		};
	}
}

/**
 * Insert icon into the portlet link.
 *
 * @param {jQuery.Object} $link
 * @param {string|undefined} id for icon
 */
function insertIcon( $link, id ) {
	var icon = document.createElement( 'span' ),
		classes = 'minerva-icon';
	if ( id ) {
		classes += ` minerva-icon-portletlink-${id}`;
		// FIXME: Please remove when following URL returns zero results:
		// https://global-search.toolforge.org/?q=mw-ui-icon-portletlink&regex=1&namespaces=&title=
		classes += ` mw-ui-icon-portletlink-${id}`;
	}
	icon.setAttribute( 'class', classes );
	$link.prepend( icon );
}

/**
 * @param {HTMLElement|null} listItem The added list item, or null if no element was added.
 * @param {Object} data
 */
function hookHandler( listItem, data ) {
	var $item, $a, classes,
		id = data.id;

	if ( listItem && !listItem.dataset.minervaPortlet ) {
		$item = $( listItem );
		classes = getClassesForItem( $item );
		$item.addClass( classes.li );
		$a = $item.find( 'a' );
		$a.addClass( classes.a );
		$item.find( 'a > span' ).addClass( classes.span );
		listItem.dataset.minervaPortlet = true;
		if ( classes.span.indexOf( 'minerva-icon' ) === -1 ) {
			insertIcon( $a, id );
		}
	}
}

/**
 * Init portlet link items added by gadgets prior to Minerva
 * loading.
 */
function init() {
	Array.prototype.forEach.call(
		document.querySelectorAll( '.mw-list-item-js' ),
		function ( item ) {
			hookHandler( item, {
				id: item.getAttribute( 'id' )
			} );
		}
	);
}
module.exports = {
	init: init,
	hookHandler: hookHandler
};
