/*
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Represents a UI element.
 *
 * @abstract
 */
class UiElement {
	/**
	 * @param {jQuery} $container
	 */
	constructor( $container ) {
		OO.EventEmitter.call( this );

		/** @property {jQuery} $container The element that contains the UI element. */
		this.$container = $container;

		/** @property {Object.<string, string[]>} eventsRegistered Events that this element has registered with the DOM. */
		this.eventsRegistered = {};
	}

	/**
	 * Sets the data for the element.
	 *
	 * @abstract
	 */
	set() { }

	/**
	 * Empties the element.
	 *
	 * @abstract
	 */
	empty() { }

	/**
	 * Registers listeners.
	 *
	 * @abstract
	 */
	attach() { }

	/**
	 * Clears listeners.
	 *
	 * @abstract
	 */
	unattach() {
		this.clearEvents();
	}

	/**
	 * Add event handler in a way that will be auto-cleared on lightbox close
	 *
	 * TODO: Unit tests
	 *
	 * @param {string} name Name of event, like 'keydown'
	 * @param {Function} handler Callback for the event
	 */
	handleEvent( name, handler ) {
		if ( this.eventsRegistered[ name ] === undefined ) {
			this.eventsRegistered[ name ] = [];
		}
		this.eventsRegistered[ name ].push( handler );
		$( document ).on( name, handler );
	}

	/**
	 * Remove all events that have been registered on this element.
	 *
	 * TODO: Unit tests
	 */
	clearEvents() {
		for ( const ev in this.eventsRegistered ) {
			const handlers = this.eventsRegistered[ ev ];
			while ( handlers.length > 0 ) {
				$( document ).off( ev, handlers.pop() );
			}
		}
	}
}

OO.mixinClass( UiElement, OO.EventEmitter );

module.exports = UiElement;
