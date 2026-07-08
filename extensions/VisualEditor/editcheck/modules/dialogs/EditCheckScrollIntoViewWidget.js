/**
 * EditCheckScrollIntoViewWidget class.
 *
 * @class
 * @extends OO.ui.ButtonGroupWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.EditCheckScrollIntoViewWidget = function VeUiEditCheckScrollIntoViewWidget( config ) {
	this.showButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'editcheck-dialog-scroll-into-view' ),
		flags: [ 'progressive' ],
		size: 'large',
		icon: 'arrowDown'
	} );

	this.closeButton = new OO.ui.ButtonWidget( {
		icon: 'close',
		flags: [ 'progressive' ],
		label: mw.msg( 'ooui-popup-widget-close-button-aria-label' ),
		size: 'large',
		invisibleLabel: true
	} );

	config = Object.assign( {
		classes: [ 've-ui-editCheck-scrollIntoView ve-ce-surface-interface' ],
		items: [ this.showButton, this.closeButton ]
	}, config );

	// Parent constructor
	ve.ui.EditCheckScrollIntoViewWidget.super.call( this, config );

	this.trackedElements = new Map();

	// Events
	this.showButton.connect( this, { click: [ 'emit', 'showClick' ] } );
	this.closeButton.connect( this, { click: [ 'emit', 'closeClick' ] } );

	this.connect( this, {
		showClick: () => ve.track( 'activity.editCheckScrollIntoView', 'click-show' ),
		closeClick: () => ve.track( 'activity.editCheckScrollIntoView', 'click-close' )
	} );

	ve.init.target.on( 'virtualKeyboardChange', this.update.bind( this ) );

	// eslint-disable-next-line compat/compat
	this.observer = window.IntersectionObserver ? new IntersectionObserver(
		( ( entries ) => {
			entries.forEach( ( entry ) => {
				this.trackedElements.set( entry.target, entry );
			} );
			this.update();
		} ),
		{ threshold: [ 0.3 ] }
	) : null;
};

/* Inheritance */

OO.inheritClass( ve.ui.EditCheckScrollIntoViewWidget, OO.ui.ButtonGroupWidget );

/* Methods */

/**
 * Clear tracked elements
 */
ve.ui.EditCheckScrollIntoViewWidget.prototype.clear = function () {
	if ( this.observer ) {
		this.observer.disconnect();
	}
	this.trackedElements.clear();
};

/**
 * Observe an element for visibility changes
 *
 * @param {Element} element
 */
ve.ui.EditCheckScrollIntoViewWidget.prototype.observe = function ( element ) {
	if ( this.observer ) {
		this.observer.observe( element );
	}
};

/**
 * Update classes based on the visibility of the tracked elements.
 */
ve.ui.EditCheckScrollIntoViewWidget.prototype.update = function () {
	if ( !this.observer || !this.trackedElements.size || ve.init.target.isVirtualKeyboardOpen() ) {
		this.$element.removeClass( 've-ui-editCheck-scrollIntoView-visible' );
		return;
	}

	const allNotVisible = Array.from( this.trackedElements.values() ).every(
		( entry ) => !entry.isIntersecting
	);
	if ( allNotVisible ) {
		const firstEntry = this.trackedElements.values().next().value;
		const isUp = firstEntry ? firstEntry.boundingClientRect.top < 0 : true;
		this.showButton.setIcon( isUp ? 'arrowUp' : 'arrowDown' );
		if ( OO.ui.isMobile() ) {
			this.$element.addClass( 've-ui-editCheck-scrollIntoView-bottom' );
		} else {
			this.$element.toggleClass( 've-ui-editCheck-scrollIntoView-top', isUp );
			this.$element.toggleClass( 've-ui-editCheck-scrollIntoView-bottom', !isUp );
		}

		requestAnimationFrame( () => {
			this.$element.addClass( 've-ui-editCheck-scrollIntoView-visible' );
		} );

		if ( !this.loggedShown ) {
			ve.track( 'activity.editCheckScrollIntoView', 'shown' );
			this.loggedShown = true;
		}
	} else {
		requestAnimationFrame( () => {
			this.$element.removeClass( 've-ui-editCheck-scrollIntoView-visible' );
		} );
	}
};
